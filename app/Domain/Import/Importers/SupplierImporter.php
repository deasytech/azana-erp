<?php

namespace App\Domain\Import\Importers;

use App\Domain\Import\Support\ImportColumn;
use App\Domain\Import\Support\Importer;
use App\Domain\Supplier\Models\Supplier;
use App\Filament\Support\CodeField;
use App\Models\User;

class SupplierImporter extends Importer
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function label(): string
    {
        return 'Suppliers';
    }

    public function description(): string
    {
        return 'The people and companies you buy from. A supplier already in the system (same code, name or e-mail) is reported, never overwritten.';
    }

    public function module(): string
    {
        return 'procurement';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('name', 'The supplier name.', true, 'AgroMix Nigeria Ltd'),
            new ImportColumn('code', 'Leave blank to be given the next SUP- code.', false, 'SUP-0001'),
            ...$this->contactColumns(),
            new ImportColumn('payment_terms_days', 'Days allowed to pay; 0 means pay on delivery.', false, '30'),
            new ImportColumn('notes', 'Anything worth remembering.'),
        ];
    }

    public function save(array $rows, ?User $actor): void
    {
        $row = $rows[0];
        $email = $row['email'] ?? null;

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw $this->problem('email', "\"{$email}\" is not a valid address");
        }

        $terms = $this->whole($row, 'payment_terms_days') ?? 0;
        $terms <= 365 || throw $this->problem('payment_terms_days', 'must be at most 365');

        $same = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])
            ->when($row['code'] ?? null, fn ($q, $code) => $q->orWhere('code', strtoupper($code)))
            ->when($email, fn ($q) => $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]))
            ->first();

        $same && throw $this->problem('name', "matches the existing supplier {$same->code} ({$same->name}), by code, name or e-mail. Existing suppliers are not overwritten");

        Supplier::create([
            'code' => $row['code'] ?? CodeField::next('suppliers', 'SUP'),
            'name' => $row['name'], 'contact_name' => $row['contact_name'] ?? null, 'phone' => $row['phone'] ?? null, 'email' => $email,
            'address' => $row['address'] ?? null, 'tax_number' => $row['tax_number'] ?? null,
            'payment_terms_days' => $terms, 'notes' => $row['notes'] ?? null,
        ]);
    }
}
