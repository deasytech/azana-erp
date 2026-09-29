<?php

namespace App\Models;

use App\Domain\System\Concerns\Auditable;
use LogicException;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    /** Implicitly holds every module permission, including ones added in later phases. */
    public const OWNER = 'Owner/Director';

    protected static function booted(): void
    {
        // The Owner bypass keys on this name, so it must stay fixed and the role must survive.
        static::updating(function (self $role) {
            if ($role->isDirty('name') && $role->getOriginal('name') === self::OWNER) {
                throw new LogicException('The Owner/Director role cannot be renamed.');
            }
        });

        static::deleting(function (self $role) {
            if ($role->name === self::OWNER || $role->getOriginal('name') === self::OWNER) {
                throw new LogicException('The Owner/Director role cannot be deleted.');
            }
        });
    }

    protected $fillable = ['name', 'guard_name', 'description', 'requires_two_factor'];

    protected function casts(): array
    {
        return ['requires_two_factor' => 'boolean'];
    }
}
