<?php

namespace App\Domain\Farm\Actions;

use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\FarmSetting;
use App\Domain\Farm\Settings\SettingDefinition;
use App\Domain\Farm\Settings\SettingDefinitions;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CreditEnforcement;
use App\Enums\ValuationMethod;

/**
 * Typed read/write access to per-farm settings. Reads fall back to the registered default
 * when no row exists, so a missing row never changes behaviour silently.
 */
class ResolveSettings
{
    /** Settings whose value must be one of an enum's cases. */
    private const CHOICE_SETTINGS = [
        'inventory.valuation_method' => ValuationMethod::class,
        'sales.credit_enforcement' => CreditEnforcement::class,
    ];

    /** @var array<int, array<string, string|null>> a farm's stored settings, read once per request (or queue job) */
    private array $loaded = [];

    private ?Farm $farm = null;

    private bool $farmLoaded = false;

    public function get(string $key, ?Farm $farm = null): int|string|bool
    {
        $definition = SettingDefinitions::find($key);
        $farm ??= $this->defaultFarm();

        return SettingDefinitions::cast($definition, $farm ? $this->values($farm)[$key] ?? null : null);
    }

    public function set(string $key, int|string|bool $value, ?Farm $farm = null): FarmSetting
    {
        $definition = SettingDefinitions::find($key);
        $farm ??= $this->defaultFarm() ?? throw new DomainException('No farm exists yet.', 'no_farm');

        $this->assertValid($definition, $value);

        $stored = is_bool($value) ? (string) (int) $value : (string) $value;

        $setting = FarmSetting::updateOrCreate(['farm_id' => $farm->id, 'key' => $key], ['value' => $stored]);
        $this->flush();

        return $setting;
    }

    /** Creates any missing rows with default values (existing values are never touched). */
    public function ensureDefaults(Farm $farm): void
    {
        foreach (SettingDefinitions::all() as $definition) {
            FarmSetting::firstOrCreate(
                ['farm_id' => $farm->id, 'key' => $definition->key],
                ['value' => $definition->default],
            );
        }
    }

    public function assertValid(SettingDefinition $definition, int|string|bool $value): void
    {
        $ok = match ($definition->type) {
            'int' => filter_var((string) $value, FILTER_VALIDATE_INT) !== false && (int) $value >= 0,
            'decimal' => is_numeric((string) $value) && (float) $value >= 0,
            'bool' => is_bool($value) || in_array((string) $value, ['0', '1', 'true', 'false'], true),
            default => true,
        };

        if ($ok && $definition->key === 'finance.books_closed_through') {
            $ok = (string) $value === '' || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) && checkdate((int) substr((string) $value, 5, 2), (int) substr((string) $value, 8, 2), (int) substr((string) $value, 0, 4));
        }

        if ($ok && isset(self::CHOICE_SETTINGS[$definition->key])) {
            $ok = self::CHOICE_SETTINGS[$definition->key]::tryFrom((string) $value) !== null;
        }

        if (! $ok) {
            throw new DomainException("Invalid value for {$definition->label}.", 'invalid_setting');
        }
    }

    /** Forgets what was read; called when a setting or farm changes (the models do it on save and delete). */
    public function flush(): void
    {
        $this->loaded = [];
        $this->farm = null;
        $this->farmLoaded = false;
    }

    /** @return array<string, string|null> */
    private function values(Farm $farm): array
    {
        return $this->loaded[$farm->id] ??= FarmSetting::where('farm_id', $farm->id)->pluck('value', 'key')->all();
    }

    private function defaultFarm(): ?Farm
    {
        if (! $this->farmLoaded) {
            $this->farm = Farm::where('is_active', true)->orderBy('id')->first();
            $this->farmLoaded = true;
        }

        return $this->farm;
    }
}
