<?php

namespace App\Filament\Resources\Litters\Pages;

use App\Domain\Farm\Models\Pen;
use App\Domain\Litter\Actions\GetLitterKpis;
use App\Domain\Litter\Actions\RecordLitterLoss;
use App\Domain\Litter\Actions\RegisterLitterPiglets;
use App\Domain\Litter\Actions\WeanLitter;
use App\Domain\Litter\Models\Litter;
use App\Enums\AnimalSex;
use App\Enums\IdentifierType;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Litters\LitterResource;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewLitter extends ViewRecord
{
    use NotifiesDomainErrors;

    protected static string $resource = LitterResource::class;

    private const KPIS = [
        'born_alive' => 'Born alive', 'stillborn' => 'Stillborn', 'mummified' => 'Mummified',
        'pre_weaning_losses' => 'Lost before weaning', 'weaned' => 'Weaned',
    ];

    private const PERCENT_KPIS = [
        'stillborn_percent' => 'Stillborn %', 'pre_weaning_mortality_percent' => 'Pre-weaning mortality %', 'weaning_percent' => 'Weaning %',
    ];

    private const WEIGHT_KPIS = ['avg_birth_weight_kg' => 'Avg birth weight (kg)', 'avg_weaning_weight_kg' => 'Avg weaning weight (kg)'];

    public function infolist(Schema $schema): Schema
    {
        $kpi = fn (string $key, string $label, string $suffix = '') => TextEntry::make("kpi_{$key}")->label($label)
            ->state(fn (Litter $r) => ($v = app(GetLitterKpis::class)($r)[$key]) === null ? '-' : $v.$suffix);

        return $schema->components([
            Section::make('Litter')->columns(4)->schema([
                TextEntry::make('litter_number')->weight('bold')->copyable(),
                TextEntry::make('sow.animal_number')->label('Sow'),
                TextEntry::make('sire.animal_number')->label('Sire')->placeholder('Unknown'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('born_on')->date(),
                TextEntry::make('expected_weaning_on')->label('Weaning due')->date(),
                TextEntry::make('weaned_on')->date()->placeholder('-'),
                $kpi('gestation_days', 'Gestation (days)'),
            ]),
            Section::make('Performance')->columns(4)->schema([
                ...collect(self::KPIS)->map(fn ($label, $key) => $kpi($key, $label))->values()->all(),
                ...collect(self::PERCENT_KPIS)->map(fn ($label, $key) => $kpi($key, $label, '%'))->values()->all(),
                ...collect(self::WEIGHT_KPIS)->map(fn ($label, $key) => $kpi($key, $label))->values()->all(),
                $kpi('lactation_days', 'Lactation (days)'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $suckling = fn () => $this->record->isSuckling() && auth()->user()->can('create', Litter::class);

        return [
            Action::make('piglets')->label('Register piglets')->icon('heroicon-o-plus-circle')->visible($suckling)
                ->schema([
                    Repeater::make('piglets')->minItems(1)->columns(3)->addActionLabel('Add piglet')->schema([
                        Select::make('sex')->options(AnimalResource::enumOptions(AnimalSex::cases()))->required(),
                        TextInput::make('birth_weight_kg')->label('Birth weight (kg)')->numeric()->minValue(0.01)->step(0.01),
                        TextInput::make('ear_tag')->maxLength(100),
                    ]),
                    Select::make('pen_id')->label('Pen (optional)')->searchable()->options(fn () => Pen::where('is_active', true)->orderBy('code')->pluck('code', 'id')),
                ])
                ->action(function (array $data, Action $action) {
                    $specs = collect($data['piglets'])->map(fn ($p) => [
                        'sex' => $p['sex'],
                        'birth_weight_kg' => filled($p['birth_weight_kg'] ?? null) ? (string) $p['birth_weight_kg'] : null,
                        'identifiers' => filled($p['ear_tag'] ?? null) ? [['type' => IdentifierType::EarTag->value, 'value' => $p['ear_tag']]] : [],
                    ])->all();

                    $this->run(fn () => app(RegisterLitterPiglets::class)($this->record, $specs, $data['pen_id'] ?? null), $action, 'Piglets registered');
                }),
            Action::make('loss')->label('Record loss')->icon('heroicon-o-minus-circle')->color('warning')->visible($suckling)
                ->schema([
                    TextInput::make('count')->numeric()->integer()->minValue(1)->default(1)->required(),
                    DatePicker::make('occurred_on')->default(now())->maxDate(now())->required(),
                    TextInput::make('cause')->maxLength(255),
                    Select::make('animal_id')->label('Tracked piglet (optional)')->searchable()
                        ->options(fn () => $this->record->piglets()->with('animal')->get()->filter(fn ($p) => $p->animal->isActive())->mapWithKeys(fn ($p) => [$p->animal_id => $p->animal->animal_number])->all()),
                ])
                ->action(fn (array $data, Action $action) => $this->run(fn () => app(RecordLitterLoss::class)(
                    $this->record, (int) $data['count'], Carbon::parse($data['occurred_on']), $data['cause'] ?? null, $data['animal_id'] ?? null,
                ), $action, 'Loss recorded')),
            Action::make('wean')->label('Wean litter')->icon('heroicon-o-arrow-right-circle')->visible($suckling)
                ->schema([
                    DatePicker::make('weaned_on')->default(now())->maxDate(now())->required(),
                    TextInput::make('weaned_count')->numeric()->integer()->minValue(0)->required()
                        ->default(fn () => $this->record->farrowing->born_alive - (int) $this->record->losses()->sum('count')),
                    TextInput::make('total_weight_kg')->label('Total weaning weight (kg)')->numeric()->minValue(0.01)->step(0.01),
                    Select::make('pen_id')->label('Move tracked piglets to pen (optional)')->searchable()->options(fn () => Pen::where('is_active', true)->orderBy('code')->pluck('code', 'id')),
                    Textarea::make('notes'),
                ])
                ->action(fn (array $data, Action $action) => $this->run(fn () => app(WeanLitter::class)(
                    $this->record, Carbon::parse($data['weaned_on']), (int) $data['weaned_count'],
                    filled($data['total_weight_kg'] ?? null) ? (string) $data['total_weight_kg'] : null, $data['pen_id'] ?? null, $data['notes'] ?? null,
                ), $action, 'Litter weaned')),
        ];
    }

    private function run(Closure $callback, Action $action, string $message): void
    {
        $this->attempt($callback, $action);
        $this->record->refresh();
        Notification::make()->title($message)->success()->send();
    }
}
