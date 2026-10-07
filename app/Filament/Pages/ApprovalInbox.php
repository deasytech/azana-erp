<?php

namespace App\Filament\Pages;

use App\Domain\Tasks\Actions\GetPendingApprovals;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** What is waiting for this user to approve, across every module that asks for approval. */
class ApprovalInbox extends Page
{
    protected string $view = 'filament.pages.approval-inbox';

    protected static ?string $navigationLabel = 'Approvals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Tasks & alerts';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Waiting for approval';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('tasks.view') ?? false;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getItemsProperty(): Collection
    {
        return app(GetPendingApprovals::class)(auth()->user());
    }
}
