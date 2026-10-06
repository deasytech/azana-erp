<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Sales\Models\Customer;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CreditEnforcement;
use App\Enums\CreditStatus;
use App\Support\Money;

/**
 * Applies the farm's credit rule to an order total: what the customer would owe afterwards (outstanding + this
 * order, less any deposit) must stay within the approved limit. A blocked customer is refused outright. Returns a
 * warning when the rule is "warn" and the limit is exceeded; throws when it is "block".
 */
class CheckCustomerCredit
{
    public function __construct(private readonly GetCustomerAccount $account, private readonly ResolveSettings $settings) {}

    /** @return ?string a warning to keep with the order, or null when all is well */
    public function __invoke(Customer $customer, int $orderTotalMinor, string $currency): ?string
    {
        $mode = CreditEnforcement::tryFrom((string) $this->settings->get('sales.credit_enforcement')) ?? CreditEnforcement::Block;

        if ($mode === CreditEnforcement::Off) {
            return null;
        }

        if ($customer->credit_status === CreditStatus::Blocked) {
            throw new DomainException("{$customer->name} is blocked from buying.", 'customer_blocked');
        }

        $account = ($this->account)($customer);
        $exposure = $account['outstanding'] + $orderTotalMinor - $account['deposit'];
        $money = fn (int $minor) => Money::ofMinor($minor, $currency)->format();
        $problems = [];

        if ($exposure > $account['credit_limit']) {
            $problems[] = $account['credit_limit'] === 0
                ? "{$customer->name} has no approved credit and has not paid in advance ({$money(max(0, $exposure))} would be owed)"
                : "this takes {$customer->name} to {$money($exposure)} against a credit limit of {$money($account['credit_limit'])}";
        }

        if ($account['overdue'] > 0 && $orderTotalMinor > $account['deposit'] && (bool) $this->settings->get('sales.block_credit_when_overdue')) {
            $problems[] = "{$customer->name} has {$money($account['overdue'])} overdue";
        }

        if ($problems === []) {
            return null;
        }

        $message = ucfirst(implode('; ', $problems)).'.';

        return $mode === CreditEnforcement::Block ? throw new DomainException($message, 'credit_limit') : $message;
    }
}
