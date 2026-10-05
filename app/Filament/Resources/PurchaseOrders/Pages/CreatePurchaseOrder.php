<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Domain\Procurement\Actions\CreatePurchaseOrder as CreateOrder;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseOrder extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'New purchase order';

    /** `?request=ID` starts the order from an approved purchase request. */
    protected function fillForm(): void
    {
        parent::fillForm();

        $request = PurchaseRequest::with('lines')->where('status', PurchaseRequestStatus::Approved)->find((int) request()->query('request'));

        if ($request) {
            $this->form->fill([
                'purchase_request_id' => $request->id,
                'ordered_on' => now()->toDateString(),
                'notes' => "From {$request->number}",
                'lines' => $request->lines->map(fn ($l) => [
                    'inventory_item_id' => $l->inventory_item_id, 'quantity' => (string) $l->quantity, 'unit_cost_minor' => $l->estimated_unit_cost_minor,
                ])->all(),
            ]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateOrder::class)(
                (int) $data['supplier_id'], $data['lines'], Carbon::parse($data['ordered_on']),
                filled($data['expected_on'] ?? null) ? Carbon::parse($data['expected_on']) : null,
                $data['notes'] ?? null, filled($data['purchase_request_id'] ?? null) ? (int) $data['purchase_request_id'] : null,
            );
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseOrderResource::getUrl('view', ['record' => $this->record]);
    }
}
