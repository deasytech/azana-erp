<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Customer;
use App\Domain\System\Actions\AssertMayDecide;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CreditStatus;
use App\Enums\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approves, changes, holds or withdraws a customer's credit. Needs sales.approve and, by default, someone other
 * than whoever set the customer up. Without an approved limit a customer pays up front (a deposit or cash).
 */
class SetCustomerCredit
{
    public function __construct(private readonly AssertMayDecide $mayDecide) {}

    public function __invoke(Customer $customer, CreditStatus $status, int $limitMinor, int $termsDays, User $approver): Customer
    {
        if ($limitMinor < 0 || $termsDays < 0 || $termsDays > 365) {
            throw new DomainException('The credit limit cannot be negative, and terms are 0 to 365 days.', 'credit_values');
        }

        if ($status === CreditStatus::Approved && $limitMinor === 0) {
            throw new DomainException('Approved credit needs a limit above zero.', 'credit_limit');
        }

        return DB::transaction(function () use ($customer, $status, $limitMinor, $termsDays, $approver) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            ($this->mayDecide)($approver, $customer->created_by, Module::Sales, 'customer credit change');

            $customer->update([
                'credit_status' => $status, 'credit_limit_minor' => $limitMinor, 'payment_terms_days' => $termsDays,
                'credit_approved_by' => $approver->id, 'credit_approved_at' => now(),
            ]);

            return $customer;
        });
    }
}
