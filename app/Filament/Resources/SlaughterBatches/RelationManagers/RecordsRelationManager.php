<?php

namespace App\Filament\Resources\SlaughterBatches\RelationManagers;

use App\Domain\Slaughter\Actions\RecordSlaughter;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Enums\PostMortemResult;
use App\Enums\SlaughterRecordStatus;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/** Each pig (or group of pigs) received on the day, its inspection, and its carcass once slaughtered. */
class RecordsRelationManager extends RelationManager
{
    use NotifiesDomainErrors;

    protected static string $relationship = 'records';

    protected static ?string $title = 'Pigs received';

    public function isReadOnly(): bool
    {
        return false;
    }

    #[On('slaughter-day-changed')]
    public function refreshRecords(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'productionBatch', 'carcass']))
            ->columns([
                TextColumn::make('source')->label('Pig')->state(fn (SlaughterRecord $r) => $r->sourceLabel()),
                TextColumn::make('received_at')->label('Received')->dateTime(),
                TextColumn::make('live_weight_kg')->label('Live (kg)'),
                TextColumn::make('ante_mortem')->label('Inspection')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->value === 'passed' ? 'success' : 'danger'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        SlaughterRecordStatus::Slaughtered => 'success', SlaughterRecordStatus::Received => 'warning', default => 'danger',
                    }),
                TextColumn::make('carcass.number')->label('Carcass')->placeholder('-'),
                TextColumn::make('carcass.hot_weight_kg')->label('Carcass (kg)')->placeholder('-'),
                TextColumn::make('carcass.dressing_percent')->label('Dressing')->suffix('%')->placeholder('-'),
                MoneyColumn::make('live_cost_minor', 'Cost raised'),
            ])
            ->recordActions([
                Action::make('slaughter')->label('Record slaughter')->icon('heroicon-o-scissors')
                    ->visible(fn (SlaughterRecord $r) => $r->status === SlaughterRecordStatus::Received && auth()->user()->can('create', SlaughterRecord::class))
                    ->schema([
                        TextInput::make('hot_weight_kg')->label('Hot carcass weight (kg)')->numeric()->minValue(0.01)->step(0.01)->required(),
                        Select::make('post_mortem')->label('Inspection after slaughter')->options(AnimalResource::enumOptions(PostMortemResult::cases()))->default(PostMortemResult::Passed->value)->required()->live(),
                        TextInput::make('condemned_kg')->label('Weight condemned (kg)')->numeric()->minValue(0.01)->step(0.01)
                            ->visible(fn ($get) => $get('post_mortem') === PostMortemResult::Partial->value)->required(fn ($get) => $get('post_mortem') === PostMortemResult::Partial->value),
                        Textarea::make('notes')->label('Inspector\'s notes'),
                    ])
                    ->action(function (SlaughterRecord $record, array $data, Action $action) {
                        $this->attempt(fn () => app(RecordSlaughter::class)($record, (string) $data['hot_weight_kg'], PostMortemResult::from($data['post_mortem']),
                            isset($data['condemned_kg']) ? (string) $data['condemned_kg'] : null, $data['notes'] ?? null), $action);

                        $this->dispatch('slaughter-day-changed');
                        Notification::make()->title('Slaughter recorded')->success()->send();
                    }),
            ])
            ->defaultSort('id')
            ->paginated([25, 50]);
    }
}
