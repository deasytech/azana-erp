<?php

namespace App\Domain\Import\Importers;

use App\Domain\Farm\Models\LookupValue;
use App\Domain\Import\Support\ImportColumn;
use App\Domain\Import\Support\Importer;
use App\Domain\Sales\Actions\SaveCustomer;
use App\Domain\Sales\Models\Customer;
use App\Enums\LookupCategory;
use App\Models\User;

/** Existing customers, numbered and set up on cash terms like any customer made by hand (credit is approved separately). */
class CustomerImporter extends Importer
{
    public function __construct(private readonly SaveCustomer $save) {}

    public function key(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return 'Customers';
    }

    public function description(): string
    {
        return 'Customers you already sell to. They start on cash terms; approve credit limits afterwards. A customer already in the system (same name, e-mail or phone) is reported, never overwritten.';
    }

    public function module(): string
    {
        return 'sales';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('name', 'The customer or business name.', true, 'Green Valley Farms'),
            new ImportColumn('customer_type', 'A customer type from the lists (its name or code).', true, 'Farmer'),
            ...$this->contactColumns(),
            new ImportColumn('notes', 'Anything worth remembering.'),
        ];
    }

    public function save(array $rows, ?User $actor): void
    {
        $row = $rows[0];
        $type = $this->find(LookupValue::class, $row, 'customer_type', fn ($q) => $q->where('category', LookupCategory::CustomerType->value), true, 'customer type', 'customer_type');

        $same = Customer::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])
            ->when($row['email'] ?? null, fn ($q, $email) => $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]))
            ->when($row['phone'] ?? null, fn ($q, $phone) => $q->orWhere('phone', $phone))
            ->first();

        if ($same) {
            throw $this->problem('name', "matches the existing customer {$same->code} ({$same->name}), by name, e-mail or phone. Existing customers are not overwritten");
        }

        ($this->save)([
            'name' => $row['name'], 'customer_type_id' => $type->id,
            'contact_name' => $row['contact_name'] ?? null, 'phone' => $row['phone'] ?? null, 'email' => $row['email'] ?? null,
            'address' => $row['address'] ?? null, 'tax_number' => $row['tax_number'] ?? null, 'notes' => $row['notes'] ?? null,
        ], null, $actor);
    }
}
