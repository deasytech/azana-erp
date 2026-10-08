<?php

namespace App\Filament\Pages;

use App\Domain\Tasks\Actions\GetPendingApprovals;
use App\Filament\Concerns\HasCachedNavigationBadge;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** What is waiting for this user to approve, across every module that asks for approval. */
class ApprovalInbox extends Page
{
    use HasCachedNavigationBadge;

    protected string $view = 'filament.pages.approval-inbox';

    protected static ?string $navigationLabel = 'Approvals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Tasks & alerts';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Waiting for approval';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Anyone who can approve in a module that feeds the inbox, or who can see tasks.
        return $user && collect(['tasks.view', 'finance.approve', 'procurement.approve', 'inventory.approve', 'semen.approve'])->contains(fn ($permission) => $user->can($permission));
    }

    /** What waits for this user's decision, not counting what they raised themselves (the same figure as the dashboard). */
    protected static function navigationBadgeCount(User $user): int
    {
        return app(GetPendingApprovals::class)($user)->reject(fn ($item) => $item['mine'])->count();
    }

    protected static function navigationBadgeTooltipText(): ?string
    {
        return 'Waiting for your approval';
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getItemsProperty(): Collection
    {
        return app(GetPendingApprovals::class)(auth()->user());
    }
}
