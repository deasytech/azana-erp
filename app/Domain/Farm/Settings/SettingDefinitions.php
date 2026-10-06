<?php

namespace App\Domain\Farm\Settings;

use InvalidArgumentException;

/**
 * Registry of the settings that exist and their default values. The live values are
 * per-farm rows in farm_settings and are edited in the admin UI, never in code.
 * Later phases append their own definitions here.
 */
final class SettingDefinitions
{
    private const BREEDING = 'Breeding';

    private const TARGETS = 'Production targets';

    private const ANIMALS = 'Animals';

    private const HEALTH = 'Health & biosecurity';

    private const INVENTORY = 'Inventory';

    private const PROCUREMENT = 'Purchasing';

    private const FEED_MILL = 'Feed mill';

    private const SEMEN = 'Semen & laboratory';

    private const SALES = 'Sales & credit';

    private const SLAUGHTER = 'Slaughter & meat';

    /** @return array<string, SettingDefinition> keyed by setting key */
    public static function all(): array
    {
        return collect([
            new SettingDefinition('breeding.gestation_days', self::BREEDING, 'Gestation length (days)', 'int', '114', 'Service date to expected farrowing.'),
            new SettingDefinition('breeding.pregnancy_check_days', self::BREEDING, 'Pregnancy check (days after service)', 'int', '28'),
            new SettingDefinition('breeding.weaning_age_days', self::BREEDING, 'Standard weaning age (days)', 'int', '28'),
            new SettingDefinition('breeding.wean_to_service_days', self::BREEDING, 'Weaning-to-service interval (days)', 'int', '5', 'Expected return to heat after weaning.'),
            new SettingDefinition('breeding.heat_cycle_days', self::BREEDING, 'Heat cycle (days)', 'int', '21'),
            new SettingDefinition('breeding.min_first_service_age_days', self::BREEDING, 'Minimum age at first service (days)', 'int', '240'),
            new SettingDefinition('breeding.same_heat_window_days', self::BREEDING, 'Same-heat window (days)', 'int', '3', 'Services this close together count as one mating (double mating) for pregnancy results.'),
            new SettingDefinition('production.target_market_weight_kg', self::TARGETS, 'Target market weight (kg)', 'decimal', '100', 'Used to predict when a batch reaches market weight; a batch may override it.'),
            new SettingDefinition('production.target_weaning_percent', self::TARGETS, 'Target weaning percentage', 'decimal', '90'),
            new SettingDefinition('production.target_dressing_percent', self::TARGETS, 'Target dressing percentage', 'decimal', '75', 'Carcass weight / live weight x 100.'),
            new SettingDefinition('production.target_preweaning_mortality_percent', self::TARGETS, 'Maximum pre-weaning mortality (%)', 'decimal', '10'),
            new SettingDefinition('animals.number_prefix', self::ANIMALS, 'Animal number prefix', 'string', 'IPA', 'Permanent numbers look like PREFIX-SOW-0001.'),
            new SettingDefinition('animals.max_weight_kg', self::ANIMALS, 'Maximum plausible weight (kg)', 'decimal', '500', 'Weights above this are rejected as data-entry errors.'),
            new SettingDefinition('health.mortality_age_band_limits', self::HEALTH, 'Mortality age bands (upper limits in days)', 'string', '7,28,70,150', 'Comma-separated; the last band is open-ended.'),
            new SettingDefinition('health.vaccination_reminder_days', self::HEALTH, 'Vaccination reminder lead time (days)', 'int', '14'),
            new SettingDefinition('health.batch_expiry_warning_days', self::HEALTH, 'Medicine batch expiry warning (days)', 'int', '60'),
            new SettingDefinition('health.open_event_alert_days', self::HEALTH, 'Alert when a health case stays open (days)', 'int', '7'),
            new SettingDefinition('health.quarantine_alert_days', self::HEALTH, 'Alert when quarantine exceeds (days)', 'int', '21'),
            new SettingDefinition('inventory.valuation_method', self::INVENTORY, 'Stock valuation method', 'string', 'fifo', '"fifo" (oldest cost first) or "weighted_average".'),
            new SettingDefinition('inventory.expiry_warning_days', self::INVENTORY, 'Batch expiry warning (days)', 'int', '60'),
            new SettingDefinition('inventory.require_separate_approver', self::INVENTORY, 'Approver must differ from requester', 'bool', '1', 'Stock counts and adjustments cannot be approved by the person who raised them.'),
            new SettingDefinition('procurement.po_approval_threshold_minor', self::PROCUREMENT, 'Purchase orders above this need approval (minor units)', 'int', '0', '0 means every purchase order needs approval; amounts are in minor currency units (kobo).'),
            new SettingDefinition('procurement.payment_approval_threshold_minor', self::PROCUREMENT, 'Supplier payments above this need approval (minor units)', 'int', '100000000', 'Default is 1,000,000.00 in the farm currency.'),
            new SettingDefinition('procurement.over_receipt_tolerance_percent', self::PROCUREMENT, 'Over-receipt tolerance (%)', 'decimal', '0', 'How far above the ordered quantity a delivery may be received.'),
            new SettingDefinition('procurement.require_separate_approver', self::PROCUREMENT, 'Approver must differ from requester', 'bool', '1', 'Requests, orders and large payments cannot be approved by the person who raised them.'),
            new SettingDefinition('feed.bag_weight_kg', self::FEED_MILL, 'Feed bag weight (kg)', 'decimal', '25', 'Used to show the cost of a bag of feed.'),
            new SettingDefinition('feed.finished_feed_shelf_life_days', self::FEED_MILL, 'Finished feed shelf life (days)', 'int', '90', 'Expiry given to a finished-feed batch when its stock item tracks expiry.'),
            new SettingDefinition('semen.shelf_life_days', self::SEMEN, 'Semen shelf life (days)', 'int', '4', 'A batch expires this many days after collection.'),
            new SettingDefinition('semen.min_collection_interval_days', self::SEMEN, 'Minimum days between collections', 'int', '4', 'A boar can override this.'),
            new SettingDefinition('semen.min_motility_percent', self::SEMEN, 'QC: minimum motility (%)', 'decimal', '70'),
            new SettingDefinition('semen.min_concentration_million_per_ml', self::SEMEN, 'QC: minimum concentration (million sperm/ml)', 'decimal', '200'),
            new SettingDefinition('semen.max_abnormal_percent', self::SEMEN, 'QC: maximum abnormal forms (%)', 'decimal', '20'),
            new SettingDefinition('semen.sperm_per_dose_million', self::SEMEN, 'Motile sperm per dose (million)', 'int', '2500', 'Limits how many doses an ejaculate can be made into.'),
            new SettingDefinition('semen.cost_per_dose_minor', self::SEMEN, 'Cost per dose (minor units)', 'int', '0', 'Stock value given to each released dose.'),
            new SettingDefinition('semen.target_doses_per_week', self::SEMEN, 'Target doses per boar per week', 'int', '40', 'Planning target; a boar can override it.'),
            new SettingDefinition('semen.require_separate_approver', self::SEMEN, 'Releaser must differ from the QC analyst', 'bool', '1', 'A batch must be released by someone other than the person who recorded its QC.'),
            new SettingDefinition('sales.credit_enforcement', self::SALES, 'Credit limit rule', 'string', 'block', '"block" refuses an order that takes a customer over their limit, "warn" allows it with a warning, "off" does not check.'),
            new SettingDefinition('sales.block_credit_when_overdue', self::SALES, 'No new credit while a customer is overdue', 'bool', '1', 'Applies when the credit limit rule is "block".'),
            new SettingDefinition('sales.discount_approval_threshold_percent', self::SALES, 'Discounts above this (%) need approval', 'decimal', '10', 'An order with a larger discount can only be confirmed by someone who can approve.'),
            new SettingDefinition('sales.require_separate_approver', self::SALES, 'Credit approver must differ from who set up the customer', 'bool', '1'),
            new SettingDefinition('slaughter.require_separate_approver', self::SLAUGHTER, 'Carcass weight corrections need a different approver', 'bool', '1', 'A corrected carcass weight must be approved by someone other than who recorded the slaughter.'),
            new SettingDefinition('slaughter.min_dressing_percent_alert', self::SLAUGHTER, 'Flag a carcass below this dressing (%)', 'decimal', '65', 'Shown on the yield report when a carcass dresses out below this.'),
            new SettingDefinition('biosecurity.min_pig_contact_free_hours', self::HEALTH, 'Visitor pig-free period (hours)', 'int', '48', 'Visitors with less pig-free time need approval to enter.'),
        ])->keyBy->key->all();
    }

    public static function find(string $key): SettingDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    public static function cast(SettingDefinition $definition, ?string $raw): int|string|bool
    {
        $raw ??= $definition->default;

        return match ($definition->type) {
            'int' => (int) $raw,
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            default => $raw, // decimals stay strings: no floats
        };
    }
}
