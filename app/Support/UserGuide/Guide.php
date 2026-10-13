<?php

namespace App\Support\UserGuide;

use Database\Seeders\RoleSeeder;

/** The outline of the user guide: the sidebar is built from it, and a test checks that every entry has a section on the page. */
class Guide
{
    /**
     * Sidebar groups, each a list of [section id, label, words the search also matches].
     *
     * @return array<string, list<array{0: string, 1: string, 2: string}>>
     */
    public static function nav(): array
    {
        return [
            'Getting started' => [
                ['welcome', 'Welcome', 'introduction overview what is the erp azana'],
                ['signin', 'Signing in', 'login password two-factor 2fa authenticator recovery profile'],
                ['layout', 'Finding your way around', 'sidebar menu search notifications dark mode navigation top bar user menu'],
                ['lists', 'Working with lists', 'table filter search sort columns export view edit bulk'],
                ['forms', 'Saving and correcting records', 'form create edit cancel reverse void history audit ledger undo'],
                ['roles', 'Who can see what', 'roles permissions access owner manager worker accountant approvals'],
            ],
            'Module by module' => [
                ['m-home', 'Home', 'dashboard today on the farm widgets'],
                ['m-animals', 'Animals', 'pigs identifiers ear tag rfid qr movement status history register'],
                ['m-structure', 'Farm structure', 'farm buildings rooms pens locations production units'],
                ['m-breeding', 'Breeding', 'heat service insemination pregnancy farrowing litter weaning calendar'],
                ['m-health', 'Health', 'vaccination treatment vet visit mortality culling quarantine withdrawal lab disease medicine'],
                ['m-biosecurity', 'Biosecurity', 'visitor log inspection checklist'],
                ['m-production', 'Production', 'batches growth weigh-in feed records finishing'],
                ['m-feedmill', 'Feed mill', 'formula ingredients production order finished feed'],
                ['m-inventory', 'Inventory', 'stock items stores batches adjustments counts ledger expiry'],
                ['m-purchasing', 'Purchasing', 'supplier purchase request order goods receipt invoice payment'],
                ['m-semen', 'Semen', 'boar collection batch qc release laboratory doses'],
                ['m-sales', 'Sales', 'customers orders invoices payments credit limit receipt balances'],
                ['m-slaughter', 'Slaughter and meat', 'carcass dressing meat products trace yield cuts'],
                ['m-finance', 'Finance', 'journal chart of accounts trial balance cash expenses budget profitability cost'],
                ['m-tasks', 'Tasks and alerts', 'approvals alerts tasks mobile sync log'],
                ['m-management', 'Management', 'dashboards kpi targets monthly report'],
                ['m-website', 'Website', 'listings enquiries public site'],
                ['m-admin', 'Set-up and administration', 'users roles audit log imports backups settings master data price lists'],
            ],
            'Step-by-step jobs' => [
                ['w-feed', 'Buy raw materials and make feed', 'maize purchase order receipt formula production'],
                ['w-pig', 'Follow a pig from service to weaning', 'sow boar service farrowing litter wean'],
                ['w-sell', 'Sell and get paid', 'customer order invoice dispatch payment receipt'],
                ['w-meat', 'From live pig to meat on the invoice', 'slaughter carcass meat cuts trace customer'],
                ['w-month', 'Month-end', 'close period post trial balance reconcile'],
            ],
            'On the phone' => [
                ['mobile', 'The mobile app', 'phone offline sync quick actions scan weights conflict'],
            ],
            'Help' => [
                ['glossary', 'Glossary', 'terms meaning words'],
                ['faq', 'Questions and fixes', 'faq troubleshooting problem cannot see missing button locked out'],
            ],
        ];
    }

    /**
     * The standard roles with what each is for, whether it signs in with two-factor and whether it may use the mobile app.
     *
     * @return list<array{name: string, about: string, two_factor: bool, mobile: bool}>
     */
    public static function roles(): array
    {
        $mobile = ['General Manager', 'Farm Manager', 'Breeding Manager', 'Feed Mill Manager', 'Semen Laboratory Manager', 'Nutritionist', 'Store Officer', 'Sales Officer', 'Slaughter Manager', 'Farm Worker'];
        $roles = [['name' => 'Owner/Director', 'about' => 'Full oversight of the business; passes every permission check.', 'two_factor' => true, 'mobile' => false]];

        foreach (RoleSeeder::ROLES as $name => $role) {
            if ($name === 'Owner/Director') {
                continue;
            }

            $roles[] = ['name' => $name, 'about' => $role['desc'], 'two_factor' => $role['2fa'], 'mobile' => in_array($name, $mobile, true) && ! $role['2fa']];
        }

        return $roles;
    }
}
