<?php

namespace App\Filament\Resources\Roles\Concerns;

use App\Domain\System\Actions\RecordAudit;
use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\Role;

/** Bridges the per-module permission checkboxes on the role form to Spatie permissions. */
trait HandlesPermissionMatrix
{
    /** @var array<string, list<string>> */
    private array $matrix = [];

    /** @var list<string> */
    private array $permissionsBefore = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractMatrix(array $data): array
    {
        $this->matrix = $data['matrix'] ?? [];
        unset($data['matrix']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillMatrix(Role $role, array $data): array
    {
        $granted = $role->permissions->pluck('name');

        foreach (Module::cases() as $module) {
            $data['matrix'][$module->value] = collect($module->actions())
                ->filter(fn ($action) => $granted->contains($module->permission($action)))
                ->map->value->values()->all();
        }

        return $data;
    }

    protected function syncMatrix(Role $role): void
    {
        $names = collect($this->matrix)
            ->flatMap(fn (array $actions, string $module) => array_map(
                fn (string $action) => Module::from($module)->permission(PermissionAction::from($action)),
                $actions,
            ))
            ->values()
            ->all();

        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $role->syncPermissions($names);
        $after = collect($names)->sort()->values()->all();

        if ($before !== $after) {
            app(RecordAudit::class)('permissions_changed', $role, ['permissions' => $before], ['permissions' => $after]);
        }
    }
}
