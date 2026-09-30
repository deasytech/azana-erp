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
