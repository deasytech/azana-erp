<?php

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Domain\Procurement\Actions\DecidePurchaseRequest;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Enums\PurchaseRequestStatus as Status;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Support\ProgressSteps;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPurchaseRequest extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = PurchaseRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Progress')->description('From draft to order.')->schema([
                ViewEntry::make('progress')->hiddenLabel()->view('filament.pages.partials.steps-entry')->state(fn () => $this->progress()),
            ]),
            Section::make('Request')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('needed_by')->date()->placeholder('-'),
                TextEntry::make('requestedBy.name')->label('Requested by')->placeholder('-'),
                TextEntry::make('decidedBy.name')->label('Decided by')->placeholder('-'),
                TextEntry::make('decision_notes')->label('Decision notes')->placeholder('-'),
                TextEntry::make('orders')->label('Orders')->placeholder('-')
                    ->state(fn (PurchaseRequest $r) => $r->orders->map(fn (PurchaseOrder $o) => "{$o->number} ({$o->status->label()})")->implode(', ') ?: null),
                TextEntry::make('notes')->placeholder('-'),
            ]),
        ]);
    }

    private function request(): PurchaseRequest
    {
        assert($this->record instanceof PurchaseRequest);

        return $this->record;
    }

    /** The request's journey, read from its status; the rules live in the domain actions. @return list<array<string, mixed>> */
    private function progress(): array
    {
        $status = $this->record->status;

        if ($status === Status::Rejected || $status === Status::Cancelled) {
            return ProgressSteps::stopped(['Drafted'], $status->label());
        }

        $done = match ($status) {
            Status::Draft => 1,
            Status::Submitted => 2,
            Status::Approved => 3,
            default => 4,
        };

        return ProgressSteps::make(['Drafted', 'Submitted', 'Approved', 'Ordered'], $done, [2 => $status === Status::Submitted ? 'Waiting for approval' : null]);
    }

    protected function getHeaderActions(): array
    {
        $in = fn (Status $status) => $this->request()->status === $status;
        $decide = app(DecidePurchaseRequest::class);

        return [
            $this->step('submit', 'Submit for approval', 'heroicon-o-paper-airplane', 'Request submitted',
                fn () => $decide->submit($this->request()), fn () => $in(Status::Draft) && auth()->user()->can('create', PurchaseRequest::class)),
            $this->step('approve', 'Approve', 'heroicon-o-check-circle', 'Request approved',
                fn (array $d) => $decide->approve($this->request(), auth()->user(), $d['notes'] ?? null),
                fn () => $in(Status::Submitted) && auth()->user()->can('approve', $this->request()), [Textarea::make('notes')], 'success'),
            $this->step('reject', 'Reject', 'heroicon-o-x-circle', 'Request rejected',
                fn (array $d) => $decide->reject($this->request(), auth()->user(), $d['reason']),
                fn () => $in(Status::Submitted) && auth()->user()->can('approve', $this->request()), [Textarea::make('reason')->required()], 'danger'),
            $this->step('cancel', 'Cancel request', 'heroicon-o-trash', 'Request cancelled',
                fn () => $decide->cancel($this->request()),
                fn () => ($in(Status::Draft) || $in(Status::Submitted) || $in(Status::Approved)) && auth()->user()->can('create', PurchaseRequest::class), [], 'gray'),
            Action::make('order')->label('Create purchase order')->icon('heroicon-o-document-plus')
                ->visible(fn () => $in(Status::Approved) && auth()->user()->can('create', PurchaseOrder::class))
                ->url(fn () => PurchaseOrderResource::getUrl('create', ['request' => $this->request()->id])),
        ];
    }
}
