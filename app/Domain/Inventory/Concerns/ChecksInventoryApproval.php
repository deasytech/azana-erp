<?php

namespace App\Domain\Inventory\Concerns;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\User;

/** Who may approve stock corrections: someone with inventory.approve, and (by default) not the person who raised it. */
trait ChecksInventoryApproval
{
    private function assertMayDecide(User $decider, ?int $raisedBy, string $what): void
    {
        if (! $decider->is_active || ! $decider->can(Module::Inventory->permission(PermissionAction::Approve))) {
            throw new DomainException("You are not authorised to decide a {$what}.", 'approval_forbidden');
        }

        if ($raisedBy !== null && $raisedBy === $decider->id && app(ResolveSettings::class)->get('inventory.require_separate_approver')) {
            throw new DomainException("A {$what} must be decided by someone other than the person who raised it.", 'self_approval');
        }
    }
}
