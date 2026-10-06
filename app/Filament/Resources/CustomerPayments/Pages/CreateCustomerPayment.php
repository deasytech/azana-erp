<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Domain\Sales\Actions\RecordCustomerPayment;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ReceiptMethod;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomerPayment extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = CustomerPaymentResource::class;

    protected static ?string $title = 'Receive payment';

    /** `?customer=ID` (and optionally `&invoice=ID`) opens the form for that customer, with the invoice's balance ready to apply. */
    protected function fillForm(): void
    {
        parent::fillForm();

        $customer = Customer::where('is_active', true)->find((int) request()->query('customer'));

        if ($customer) {
            $invoice = Invoice::where('customer_id', $customer->id)->with('allocations.payment')->find((int) request()->query('invoice'));

            // Filling replaces the form's own defaults, so they are repeated here (what is specific to the invoice comes first and wins).
            $this->form->fill(($invoice && $invoice->balanceMinor() > 0
                ? ['amount_minor' => $invoice->balanceMinor(), 'apply' => 'invoices', 'invoices' => [['invoice_id' => $invoice->id, 'amount_minor' => $invoice->balanceMinor()]]]
                : [])
                + ['customer_id' => $customer->id, 'method' => ReceiptMethod::BankTransfer->value, 'received_on' => now()->toDateString(), 'apply' => 'oldest']);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $allocations = match ($data['apply'] ?? 'oldest') {
            'deposit' => [],
            'invoices' => collect($data['invoices'] ?? [])->mapWithKeys(fn (array $row) => [(int) $row['invoice_id'] => (int) $row['amount_minor']])->all(),
            default => null,
        };

        try {
            return app(RecordCustomerPayment::class)((int) $data['customer_id'], (int) $data['amount_minor'], ReceiptMethod::from($data['method']),
                Carbon::parse($data['received_on']), $data['reference'] ?? null, $allocations, $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
