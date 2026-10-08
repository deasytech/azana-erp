<?php

namespace App\Domain\Website\Actions;

use App\Domain\Sales\Actions\SaveCustomer;
use App\Domain\Sales\Models\Customer;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Models\Enquiry;
use App\Enums\EnquiryStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Makes the person behind an enquiry a customer, so an order can be raised for them in the usual way. A customer with the same e-mail
 * address or phone number is reused instead of being duplicated. Credit is not granted here: a new customer starts on cash terms.
 */
class ConvertEnquiryToCustomer
{
    public function __construct(private readonly SaveCustomer $saveCustomer) {}

    public function __invoke(Enquiry $enquiry, int $customerTypeId, ?User $actor = null): Customer
    {
        return DB::transaction(function () use ($enquiry, $customerTypeId, $actor) {
            $enquiry = Enquiry::lockForUpdate()->findOrFail($enquiry->id);
            $enquiry->customer_id === null || throw new DomainException("{$enquiry->number} already belongs to a customer.", 'enquiry_converted');

            $customer = $this->existing($enquiry) ?? ($this->saveCustomer)([
                'name' => $enquiry->organisation ?: $enquiry->name,
                'customer_type_id' => $customerTypeId,
                'contact_name' => $enquiry->organisation ? $enquiry->name : null,
                'phone' => $enquiry->phone,
                'email' => $enquiry->email,
                'notes' => "From website enquiry {$enquiry->number}.",
            ], null, $actor);

            $enquiry->update([
                'status' => EnquiryStatus::Converted,
                'customer_id' => $customer->id,
                'handled_by' => ($actor ?? Auth::user())?->getKey(),
                'handled_at' => now(),
            ]);

            return $customer;
        });
    }

    private function existing(Enquiry $enquiry): ?Customer
    {
        return Customer::query()
            ->when(! $enquiry->email && ! $enquiry->phone, fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q
                ->when($enquiry->email, fn ($q) => $q->orWhereRaw('LOWER(email) = ?', [strtolower($enquiry->email)]))
                ->when($enquiry->phone, fn ($q) => $q->orWhere('phone', $enquiry->phone)))
            ->orderBy('id')
            ->first();
    }
}
