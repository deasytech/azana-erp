<?php

namespace App\Filament\Resources\SlaughterBatches\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Slaughter\Actions\ManageSlaughterBatch;
use App\Domain\Slaughter\Actions\RecordSlaughterIntake;
use App\Domain\Slaughter\Models\SlaughterBatch;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Enums\AnteMortemResult;
use App\Enums\BatchStatus;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\SlaughterBatches\SlaughterBatchResource;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewSlaughterBatch extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = SlaughterBatchResource::class;

    private function day(): SlaughterBatch
    {
        assert($this->record instanceof SlaughterBatch);

        return $this->record;
    }

    #[On('slaughter-day-changed')]
    public function refreshDay(): void
    {
        $this->day()->refresh();
    }

    protected function afterStep(): void
    {
        $this->refreshDay();
        $this->dispatch('slaughter-day-changed');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Slaughter day')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('scheduled_on')->date(),
                TextEntry::make('createdBy.name')->label('Scheduled by')->placeholder('-'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $open = fn () => $this->day()->isOpen();
        $may = fn () => auth()->user()->can('create', SlaughterRecord::class);
        $manage = app(ManageSlaughterBatch::class);

        return [
            $this->receiveAction($open, $may),
            $this->step('complete', 'Close the day', 'heroicon-o-check-circle', 'Slaughter day closed',
                fn () => $manage->complete($this->day()), fn () => $this->day()->isOpen() && $may(), [], 'success'),
            $this->step('cancel', 'Cancel the day', 'heroicon-o-trash', 'Slaughter day cancelled',
                fn (array $d) => $manage->cancel($this->day(), $d['reason']), fn () => $this->day()->isOpen() && $may(),
                [Textarea::make('reason')->required()->helperText('Pigs already received are sent back; it cannot be cancelled once any pig is slaughtered.')], 'danger'),
        ];
    }

    private function receiveAction(\Closure $open, \Closure $may): Action
    {
        return Action::make('receive')->label('Receive a pig')->icon('heroicon-o-arrow-down-on-square')
            ->visible(fn () => $open() && $may())
            ->schema([
                Select::make('source')->label('Pig from')->options(['animal' => 'A tracked animal', 'batch' => 'A production batch'])->default('animal')->required()->live(),
                AnimalPicker::any()->label('Pig')->visible(fn ($get) => $get('source') === 'animal')->required(fn ($get) => $get('source') === 'animal'),
                Select::make('production_batch_id')->label('Batch')->searchable()->visible(fn ($get) => $get('source') === 'batch')->required(fn ($get) => $get('source') === 'batch')
                    ->options(fn () => ProductionBatch::where('status', BatchStatus::Active->value)->orderBy('code')->get()->mapWithKeys(fn ($b) => [$b->id => "{$b->code} - {$b->name}"])->all()),
                TextInput::make('heads')->label('Pigs received')->numeric()->integer()->minValue(1)->default(1)->visible(fn ($get) => $get('source') === 'batch')->required(fn ($get) => $get('source') === 'batch'),
                TextInput::make('live_weight_kg')->label('Live weight (kg, all pigs)')->numeric()->minValue(0.01)->step(0.01)->required(),
                Select::make('ante_mortem')->label('Inspection before slaughter')->options(AnimalResource::enumOptions(AnteMortemResult::cases()))->default(AnteMortemResult::Passed->value)->required(),
                Textarea::make('notes')->label('Inspector\'s notes')->helperText('Required when the pig fails inspection.'),
                MoneyInput::make('live_cost_minor', 'Cost of raising the pig(s)')->helperText('Leave empty to take it from the farm\'s feed and batch records.'),
            ])
            ->action(function (array $data, Action $action) {
                $source = $data['source'] === 'batch' ? ProductionBatch::findOrFail($data['production_batch_id']) : Animal::findOrFail($data['animal_id']);

                $this->attempt(fn () => app(RecordSlaughterIntake::class)($this->day(), $source, (string) $data['live_weight_kg'], AnteMortemResult::from($data['ante_mortem']),
                    $data['notes'] ?? null, (int) ($data['heads'] ?? 1), $data['live_cost_minor'] ?? null), $action);

                $this->afterStep();
                Notification::make()->title($data['ante_mortem'] === 'failed' ? 'Pig rejected at inspection' : 'Pig received')->success()->send();
            });
    }
}
