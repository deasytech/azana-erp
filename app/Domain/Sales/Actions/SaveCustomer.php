<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Farm\Models\LookupValue;
use App\Domain\Sales\Models\Customer;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\LookupCategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates a customer (numbered C-000001) or updates their details. Credit is not set here: a limit is approved
 * separately (SetCustomerCredit), so a customer starts on cash terms.
 */
class SaveCustomer
{
    public function __construct(private readonly NextNumber $nextNumber) {}

    /** @param array{name: string, customer_type_id: int, contact_name?: ?string, phone?: ?string, email?: ?string, address?: ?string, tax_number?: ?string, notes?: ?string, is_active?: bool} $data */
    public function __invoke(array $data, ?Customer $customer = null, ?User $actor = null): Customer
    {
        $this->validate($data);

        $fields = array_intersect_key($data, array_flip(['name', 'customer_type_id', 'contact_name', 'phone', 'email', 'address', 'tax_number', 'notes', 'is_active']));
        $fields['name'] = trim($fields['name']);

        return DB::transaction(function () use ($fields, $customer, $actor) {
            if ($customer) {
                $customer->update($fields);

                return $customer;
            }

            return Customer::create($fields + [
                'code' => sprintf('C-%06d', ($this->nextNumber)('customer')),
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new DomainException('Give the customer a name.', 'customer_name');
        }

        if (! LookupValue::where('category', LookupCategory::CustomerType->value)->where('is_active', true)->whereKey($data['customer_type_id'] ?? 0)->exists()) {
            throw new DomainException('Choose a valid customer type.', 'customer_type');
        }

        if (filled($data['email'] ?? null) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('That email address is not valid.', 'customer_email');
        }
    }
}
