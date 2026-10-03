<?php

namespace App\Domain\System\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\User;

/**
 * Approval gate shared by the modules: the decider must be an active user holding "{module}.approve" and, when the
 * module's separate-approver setting is on, must not be the person who raised the record.
 */
class AssertMayDecide
{
    public function __construct(private readonly ResolveSettings $settings) {}

    public function __invoke(User $decider, ?int $raisedBy, Module $module, string $what): void
    {
        if (! $decider->is_active || ! $decider->can($module->permission(PermissionAction::Approve))) {
            throw new DomainException("You are not authorised to decide a {$what}.", 'approval_forbidden');
        }

        if ($raisedBy !== null && $raisedBy === $decider->id && $this->settings->get($module->value.'.require_separate_approver')) {
            throw new DomainException("A {$what} must be decided by someone other than the person who raised it.", 'self_approval');
        }
    }
}
