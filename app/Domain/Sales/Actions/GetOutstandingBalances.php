<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Customer;
use Illuminate\Support\Collection;

/** Every customer who owes money or holds a deposit, biggest debt first (minor units). */
class GetOutstandingBalances
{
    public function __construct(private readonly GetCustomerAccount $account) {}

    /** @return Collection<int, array{customer: Customer, outstanding: int, overdue: int, deposit: int, credit_limit: int}> */
    public function __invoke(): Collection
    {
        return Customer::orderBy('name')->get()
            ->map(fn (Customer $c) => ['customer' => $c] + array_intersect_key(($this->account)($c), array_flip(['outstanding', 'overdue', 'deposit', 'credit_limit'])))
            ->filter(fn (array $row) => $row['outstanding'] > 0 || $row['deposit'] > 0)
            ->sortByDesc('outstanding')->values();
    }
}
