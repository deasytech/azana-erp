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

    /** The customer who already has this e-mail or phone. If the two belong to different customers it is unclear who this is, so staff must decide. */
    private function existing(Enquiry $enquiry): ?Customer
    {
        $byEmail = $enquiry->email ? Customer::whereRaw('LOWER(email) = ?', [strtolower($enquiry->email)])->orderBy('id')->first() : null;
        $byPhone = $enquiry->phone ? Customer::where('phone', $enquiry->phone)->orderBy('id')->first() : null;

        if ($byEmail && $byPhone && $byEmail->isNot($byPhone)) {
            throw new DomainException("The e-mail address of {$enquiry->number} belongs to {$byEmail->name} but its phone number belongs to {$byPhone->name}. Check which one this is before making a customer.", 'enquiry_customer_ambiguous');
        }

        return $byEmail ?? $byPhone;
    }
}
