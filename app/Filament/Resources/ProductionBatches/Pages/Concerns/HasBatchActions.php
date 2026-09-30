<?php

namespace App\Filament\Resources\ProductionBatches\Pages\Concerns;

use App\Domain\Animal\Models\Animal;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Production\Actions\AddAnimalToBatch;
use App\Domain\Production\Actions\AddPigsToBatch;
use App\Domain\Production\Actions\AdjustBatchCount;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Actions\RecordBatchWeighIn;
use App\Domain\Production\Actions\RecordProductionCost;
use App\Domain\Production\Actions\RemovePigsFromBatch;
use App\Domain\Production\Models\ProductionBatch;
use App\Enums\BatchEventType;
use App\Enums\LookupCategory;
use App\Enums\ProductionCostCategory;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\LookupSelect;
use App\Filament\Support\MoneyInput;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;

/** The "Record" menu on a batch. Needs NotifiesDomainErrors (attempt) and a `refreshBatch()` on the page. */
trait HasBatchActions
{
    /** @return list<ActionGroup> */
    protected function batchActions(): array
    {
        $active = fn () => $this->record->isActive() && auth()->user()->can('create', ProductionBatch::class);
        $approver = fn () => $this->record->isActive() && auth()->user()->can('approve', ProductionBatch::class);

        return [ActionGroup::make([
            $this->batchAction('add_pigs', 'Add pigs', 'heroicon-o-plus-circle', [
                Select::make('type')->options([BatchEventType::Placement->value => 'New placement', BatchEventType::TransferIn->value => 'Transfer in'])->required()->default(BatchEventType::Placement->value),
                $this->countField(), $this->dateField('occurred_on'), MoneyInput::make('unit_cost_minor', 'Cost per pig'), Textarea::make('notes'),
            ], fn (array $d) => app(AddPigsToBatch::class)($this->record, (int) $d['count'], Carbon::parse($d['occurred_on']), BatchEventType::from($d['type']), $d['unit_cost_minor'] ?? null, $d['notes'] ?? null), 'Pigs added', $active),
            $this->batchAction('remove_pigs', 'Remove pigs (sold, slaughtered...)', 'heroicon-o-minus-circle', [
                Select::make('type')->options(collect([BatchEventType::Sale, BatchEventType::Slaughter, BatchEventType::Cull, BatchEventType::TransferOut])->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all())->required(),
                $this->countField(), $this->dateField('occurred_on'), Textarea::make('notes'),
            ], fn (array $d) => app(RemovePigsFromBatch::class)($this->record, BatchEventType::from($d['type']), (int) $d['count'], Carbon::parse($d['occurred_on']), $d['notes'] ?? null), 'Pigs removed', $active),
            $this->batchAction('mortality', 'Record deaths', 'heroicon-o-x-circle', [
                $this->countField(), $this->dateField('occurred_on'), LookupSelect::options('cause_id', LookupCategory::MortalityCause, 'Cause of death')->required(), Textarea::make('notes'),
            ], fn (array $d) => app(RecordBatchMortality::class)($this->record, (int) $d['count'], Carbon::parse($d['occurred_on']), (int) $d['cause_id'], $d['notes'] ?? null), 'Deaths recorded', $active),
            $this->batchAction('weigh', 'Record weigh-in', 'heroicon-o-scale', [
                $this->dateField('weighed_on'),
                TextInput::make('sample_size')->label('Pigs weighed')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('average_weight_kg')->label('Average weight (kg)')->numeric()->minValue(0.01)->step(0.01)->required(), Textarea::make('notes'),
            ], fn (array $d) => app(RecordBatchWeighIn::class)($this->record, Carbon::parse($d['weighed_on']), (int) $d['sample_size'], (string) $d['average_weight_kg'], $d['notes'] ?? null), 'Weigh-in recorded', $active),
            $this->batchAction('feed', 'Record feed', 'heroicon-o-cube', [
                Select::make('feed_type_id')->label('Feed type')->required()->options(fn () => FeedType::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                $this->dateField('consumed_on'),
                TextInput::make('quantity_kg')->label('Quantity (kg)')->numeric()->minValue(0.01)->step(0.01)->required(),
                MoneyInput::make('cost_per_kg_minor', 'Cost per kg'), Textarea::make('notes'),
            ], fn (array $d) => app(RecordFeedConsumption::class)($this->record, (int) $d['feed_type_id'], Carbon::parse($d['consumed_on']), (string) $d['quantity_kg'], ['cost_per_kg_minor' => $d['cost_per_kg_minor'] ?? null, 'notes' => $d['notes'] ?? null]), 'Feed recorded', $active),
            $this->batchAction('cost', 'Add cost', 'heroicon-o-banknotes', [
                $this->dateField('incurred_on'),
                Select::make('category')->options(AnimalResource::enumOptions(ProductionCostCategory::cases()))->required(),
                MoneyInput::make('amount_minor', 'Amount')->required(), TextInput::make('description')->required()->maxLength(255),
            ], fn (array $d) => app(RecordProductionCost::class)($this->record, Carbon::parse($d['incurred_on']), ProductionCostCategory::from($d['category']), (int) $d['amount_minor'], $d['description']), 'Cost recorded', $active),
            $this->batchAction('add_animal', 'Add a tracked animal', 'heroicon-o-identification', [
                AnimalPicker::any()->required(), $this->dateField('joined_on'),
            ], fn (array $d) => app(AddAnimalToBatch::class)($this->record, Animal::findOrFail($d['animal_id']), Carbon::parse($d['joined_on'])), 'Animal added to the batch', $active),
            $this->batchAction('adjust', 'Adjust count', 'heroicon-o-adjustments-horizontal', [
                TextInput::make('delta')->label('Change in pigs (+ or -)')->numeric()->integer()->required()->helperText('After a physical count. Needs a reason.'),
                $this->dateField('occurred_on'), Textarea::make('reason')->required(),
            ], fn (array $d) => app(AdjustBatchCount::class)($this->record, (int) $d['delta'], Carbon::parse($d['occurred_on']), $d['reason']), 'Count adjusted', $approver),
        ])->label('Record')->icon('heroicon-o-pencil-square')->button()];
    }

    private function countField(): TextInput
    {
        return TextInput::make('count')->label('Number of pigs')->numeric()->integer()->minValue(1)->required();
    }

    private function dateField(string $name): DatePicker
    {
        return DatePicker::make($name)->label('Date')->default(now())->maxDate(now())->required();
    }

    /**
     * @param  list<Component>  $fields
     * @param  Closure(array<string, mixed>): mixed  $submit
     */
    private function batchAction(string $name, string $label, string $icon, array $fields, Closure $submit, string $message, Closure $visible): Action
    {
        return Action::make($name)->label($label)->icon($icon)->visible($visible)->schema($fields)
            ->action(function (array $data, Action $action) use ($submit, $message) {
                $this->attempt(fn () => $submit($data), $action);
                $this->refreshBatch();
                Notification::make()->title($message)->success()->send();
            });
    }
}
