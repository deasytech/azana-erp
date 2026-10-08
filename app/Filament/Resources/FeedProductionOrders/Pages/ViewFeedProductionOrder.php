<?php

namespace App\Filament\Resources\FeedProductionOrders\Pages;

use App\Domain\Feed\Actions\CompleteFeedProduction;
use App\Domain\Feed\Actions\ConfirmFeedConsumption;
use App\Domain\Feed\Actions\ManageFeedProductionOrder;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Enums\FeedProductionStatus as Status;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\FeedFormulas\FeedFormulaResource;
use App\Filament\Resources\FeedProductionOrders\FeedProductionOrderResource;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\ProgressSteps;
use App\Support\Ratio;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewFeedProductionOrder extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = FeedProductionOrderResource::class;

    private function order(): FeedProductionOrder
    {
        assert($this->record instanceof FeedProductionOrder);

        return $this->record;
    }

    #[On('feed-order-changed')]
    public function refreshOrder(): void
    {
        $this->order()->refresh();
    }

    protected function afterStep(): void
    {
        $this->refreshOrder();
        $this->dispatch('feed-order-changed');
    }

    public function infolist(Schema $schema): Schema
    {
        $made = fn (string $field, ?callable $format = null) => TextEntry::make("batch_{$field}")->state(fn () => ($b = $this->order()->batch) ? ($format ? $format($b->{$field}) : (string) $b->{$field}) : null)->placeholder('-');

        return $schema->components([
            Section::make('Progress')->description('From plan to finished feed.')->schema([
                ViewEntry::make('progress')->hiddenLabel()->view('filament.pages.partials.steps-entry')->state(fn () => $this->progress()),
            ]),
            Section::make('Order')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('formula.name')->label('Formula')->url(fn (FeedProductionOrder $o) => FeedFormulaResource::getUrl('view', ['record' => $o->formula]))
                    ->state(fn (FeedProductionOrder $o) => "{$o->formula->code} v{$o->formula->version} - {$o->formula->name}"),
                TextEntry::make('planned_output_kg')->label('Planned output')->suffix(' kg'),
                TextEntry::make('planned_on')->date(),
                TextEntry::make('sourceLocation.name')->label('Materials from'),
                TextEntry::make('outputLocation.name')->label('Feed goes to'),
                TextEntry::make('createdBy.name')->label('Planned by')->placeholder('-'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('cancel_reason')->label('Cancelled because')->visible(fn (FeedProductionOrder $o) => $o->cancel_reason !== null)->columnSpanFull(),
            ]),
            Section::make('Result')->columns(4)->visible(fn () => $this->order()->batch !== null)->schema([
                $made('output_kg')->label('Feed made (kg)'),
                TextEntry::make('yield')->label('Against plan')->state(fn () => ($b = $this->order()->batch) ? Ratio::percent((string) $b->output_kg, (string) $this->order()->planned_output_kg).'%' : null),
                $made('produced_on', fn ($d) => $d->format('d M Y'))->label('Produced on'),
                TextEntry::make('batch_number')->label('Batch')->state(fn () => $this->order()->batch?->inventoryBatch?->batch_number)->placeholder('-'),
                $made('material_cost_minor', fn ($v) => MoneyColumn::format($v))->label('Materials cost'),
                $made('other_cost_minor', fn ($v) => MoneyColumn::format($v))->label('Other costs'),
                $made('total_cost_minor', fn ($v) => MoneyColumn::format($v))->label('Total cost'),
                $made('cost_per_kg_minor', fn ($v) => MoneyColumn::format($v))->label('Cost per kg'),
                TextEntry::make('reversed')->label('Reversed')->color('danger')->columnSpanFull()->visible(fn () => $this->order()->batch?->isReversed() ?? false)
                    ->state(fn () => $this->order()->batch?->reverse_reason),
            ]),
        ]);
    }

    /** The order's journey, read from its status; the rules live in the domain actions. @return list<array<string, mixed>> */
    private function progress(): array
    {
        return match ($this->order()->status) {
            Status::Planned => ProgressSteps::make(['Planned', 'Completed'], 1),
            Status::Completed => ProgressSteps::make(['Planned', 'Completed'], 2),
            Status::Reversed => ProgressSteps::stopped(['Planned', 'Completed'], 'Reversed'),
            default => ProgressSteps::stopped(['Planned'], $this->order()->status->label()),
        };
    }

    protected function getHeaderActions(): array
    {
        $planned = fn () => $this->order()->status === Status::Planned;
        $mill = app(ManageFeedProductionOrder::class);

        return [
            $this->confirmAction($planned),
            $this->step('as_planned', 'Use planned quantities', 'heroicon-o-clipboard-document-check', 'Quantities confirmed as planned',
                fn () => app(ConfirmFeedConsumption::class)->asPlanned($this->order()), fn () => $planned() && auth()->user()->can('create', FeedProductionOrder::class), [], 'gray'),
            $this->completeAction($planned),
            $this->step('cancel', 'Cancel order', 'heroicon-o-trash', 'Order cancelled',
                fn (array $d) => $mill->cancel($this->order(), $d['reason']), fn () => $planned() && auth()->user()->can('create', FeedProductionOrder::class),
                [Textarea::make('reason')->required()], 'gray'),
            $this->step('reverse', 'Reverse production', 'heroicon-o-arrow-uturn-left', 'Production reversed',
                fn (array $d) => $mill->reverse($this->order(), $d['reason']),
                fn () => $this->order()->status === Status::Completed && auth()->user()->can('approve', $this->order()),
                [Textarea::make('reason')->required()->helperText('The finished feed leaves stock and the materials return at their cost. Refused if any feed has been used.')], 'danger'),
        ];
    }

    private function confirmAction(\Closure $planned): Action
    {
        return Action::make('confirm')->label('Confirm quantities used')->icon('heroicon-o-scale')
            ->visible(fn () => $planned() && auth()->user()->can('create', FeedProductionOrder::class))
            ->fillForm(fn () => ['lines' => $this->order()->lines()->with('item.unit')->get()->map(fn ($l) => [
                'inventory_item_id' => $l->inventory_item_id, 'item' => "{$l->item->name} (planned {$l->planned_quantity} {$l->item->unit->code})",
                'quantity' => (string) ($l->actual_quantity ?? $l->planned_quantity),
            ])->all()])
            ->schema([
                Repeater::make('lines')->label('Materials')->addable(false)->deletable(false)->reorderable(false)->columns(2)
                    ->helperText('Enter what production staff actually used (0 if none).')
                    ->schema([
                        Hidden::make('inventory_item_id'),
                        TextInput::make('item')->label('Material')->disabled()->dehydrated(false),
                        TextInput::make('quantity')->label('Used')->numeric()->minValue(0)->step(0.001)->required(),
                    ]),
            ])
            ->action(function (array $data, Action $action) {
                $actuals = collect($data['lines'])->mapWithKeys(fn ($l) => [(int) $l['inventory_item_id'] => (string) $l['quantity']])->all();

                $this->attempt(fn () => app(ConfirmFeedConsumption::class)($this->order(), $actuals), $action);

                $this->afterStep();
                Notification::make()->title('Quantities confirmed')->success()->send();
            });
    }

    private function completeAction(\Closure $planned): Action
    {
        return Action::make('complete')->label('Complete production')->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn () => $planned() && auth()->user()->can('create', FeedProductionOrder::class))
            ->fillForm(fn () => ['output_kg' => (string) $this->order()->planned_output_kg, 'produced_on' => now()->toDateString()])
            ->schema([
                TextInput::make('output_kg')->label('Finished feed made (kg)')->numeric()->minValue(0.001)->step(0.001)->required(),
                DatePicker::make('produced_on')->maxDate(now())->required(),
                MoneyInput::make('other_cost_minor', 'Other costs (labour, power, bags...)'),
                TextInput::make('batch_number')->maxLength(60)->helperText('Leave empty to use the order number.'),
            ])
            ->action(function (array $data, Action $action) {
                $this->attempt(fn () => app(CompleteFeedProduction::class)($this->order(), (string) $data['output_kg'], Carbon::parse($data['produced_on']),
                    (int) ($data['other_cost_minor'] ?? 0), filled($data['batch_number'] ?? null) ? $data['batch_number'] : null), $action);

                $this->afterStep();
                Notification::make()->title('Production completed')->success()->send();
            });
    }
}
