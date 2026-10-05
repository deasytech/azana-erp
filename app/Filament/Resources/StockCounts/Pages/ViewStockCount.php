<?php

namespace App\Filament\Resources\StockCounts\Pages;

use App\Domain\Inventory\Actions\ApproveStockCount;
use App\Domain\Inventory\Actions\CancelStockCount;
use App\Domain\Inventory\Actions\RejectStockCount;
use App\Domain\Inventory\Actions\SubmitStockCount;
use App\Domain\Inventory\Models\StockCount;
use App\Enums\StockCountStatus;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\StockCounts\StockCountResource;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewStockCount extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = StockCountResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Count')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('location.name')->label('Store'),
                TextEntry::make('counted_on')->date(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('startedBy.name')->label('Started by')->placeholder('-'),
                TextEntry::make('submittedBy.name')->label('Submitted by')->placeholder('-'),
                TextEntry::make('decidedBy.name')->label('Decided by')->placeholder('-'),
                TextEntry::make('decision_notes')->label('Decision notes')->placeholder('-'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
            ]),
        ]);
    }

    /** Lines changed on the tab below. */
    #[On('stock-count-changed')]
    public function refreshCount(): void
    {
        $this->stockCount()->refresh();
    }

    private function stockCount(): StockCount
    {
        assert($this->record instanceof StockCount);

        return $this->record;
    }

    protected function getHeaderActions(): array
    {
        $is = fn (StockCountStatus $status) => fn () => $this->stockCount()->status === $status;

        return [
            $this->step('submit', 'Submit for approval', 'heroicon-o-paper-airplane', 'Count submitted',
                fn () => app(SubmitStockCount::class)($this->stockCount()), fn () => $is(StockCountStatus::Draft)() && auth()->user()->can('create', StockCount::class)),
            $this->step('approve', 'Approve', 'heroicon-o-check-circle', 'Count approved and stock adjusted',
                fn (array $d) => app(ApproveStockCount::class)($this->stockCount(), auth()->user(), $d['notes'] ?? null),
                fn () => $is(StockCountStatus::Submitted)() && auth()->user()->can('approve', $this->stockCount()), [Textarea::make('notes')], 'success'),
            $this->step('reject', 'Reject', 'heroicon-o-x-circle', 'Count rejected',
                fn (array $d) => app(RejectStockCount::class)($this->stockCount(), auth()->user(), $d['reason']),
                fn () => $is(StockCountStatus::Submitted)() && auth()->user()->can('approve', $this->stockCount()), [Textarea::make('reason')->required()], 'danger'),
            $this->step('cancel', 'Cancel count', 'heroicon-o-trash', 'Count cancelled',
                fn () => app(CancelStockCount::class)($this->stockCount()), fn () => $is(StockCountStatus::Draft)() && auth()->user()->can('create', StockCount::class), [], 'gray'),
        ];
    }

    protected function afterStep(): void
    {
        $this->stockCount()->refresh();
        $this->dispatch('stock-count-changed');
    }
}
