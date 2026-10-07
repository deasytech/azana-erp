<?php

namespace Database\Seeders;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Enums\AccountType as T;
use Illuminate\Database\Seeder;

/** The starting chart of accounts and the ten cost centres. Safe to re-run: existing rows are kept as edited. */
class FinanceSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string}> code => name */
    public const COST_CENTRES = [
        ['BRD', 'Breeding'], ['PIG', 'Piglet production'], ['GRW', 'Grower'], ['FIN', 'Finisher'], ['SEM', 'Semen production'],
        ['FDM', 'Feed mill'], ['SLA', 'Slaughter'], ['MEA', 'Meat processing'], ['SAL', 'Sales and distribution'], ['ADM', 'Administration'],
    ];

    public function run(): void
    {
        foreach (self::COST_CENTRES as [$code, $name]) {
            CostCentre::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $accounts = [
            ['1000', 'Cash on hand', T::Asset, 'cash'], ['1010', 'Bank', T::Asset, 'bank'],
            ['1100', 'Accounts receivable', T::Asset, 'receivables'], ['1200', 'Inventory', T::Asset, null],
            ['2000', 'Accounts payable', T::Liability, 'payables'],
            ['3000', "Owner's equity", T::Equity, null],
            ['4000', 'Pig sales', T::Revenue, 'sales_pigs'], ['4010', 'Semen sales', T::Revenue, 'sales_semen'],
            ['4020', 'Meat sales', T::Revenue, 'sales_meat'], ['4900', 'Other income', T::Revenue, null],
            ['5000', 'Purchases and supplies', T::Expense, 'purchases'], ['5100', 'Feed and nutrition', T::Expense, null],
            ['5200', 'Veterinary and health', T::Expense, null], ['5300', 'Labour', T::Expense, null],
            ['5400', 'Utilities and fuel', T::Expense, null], ['5500', 'Maintenance', T::Expense, null],
            ['5900', 'General and administrative', T::Expense, null],
        ];

        foreach ($accounts as [$code, $name, $type, $key]) {
            Account::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'system_key' => $key]);
        }
    }
}
