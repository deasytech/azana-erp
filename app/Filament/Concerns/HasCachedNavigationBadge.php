<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * A sidebar badge for a page whose count is costly to work out (it reads several modules). The count is kept for a minute per user, so the
 * sidebar costs one calculation a minute however many pages the user opens. The badge is a hint, not the list: it may be up to a minute behind.
 */
trait HasCachedNavigationBadge
{
    /** How many things need the user's attention; 0 hides the badge. */
    abstract protected static function navigationBadgeCount(User $user): int;

    protected static function navigationBadgeTooltipText(): ?string
    {
        return null;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User || ! static::canAccess()) {
            return null;
        }

        $count = Cache::remember('nav-badge:'.static::class.':'.$user->getKey(), 60, fn (): int => static::navigationBadgeCount($user));

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::navigationBadgeTooltipText();
    }
}
