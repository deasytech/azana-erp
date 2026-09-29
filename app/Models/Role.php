<?php

namespace App\Models;

use App\Domain\System\Concerns\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    /** Implicitly holds every module permission, including ones added in later phases. */
    public const OWNER = 'Owner/Director';

    protected $fillable = ['name', 'guard_name', 'description', 'requires_two_factor'];

    protected function casts(): array
    {
        return ['requires_two_factor' => 'boolean'];
    }
}
