<?php

namespace App\Filament\Pages;

use App\Domain\Tasks\Actions\GetAlerts;
use App\Domain\Tasks\Actions\SendAlertNotifications;
use App\Filament\Concerns\HasCachedNavigationBadge;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Everything the system has noticed in its own data that needs attention, for the areas this user may view. */
class Alerts extends Page
{
    use HasCachedNavigationBadge;

    protected string $view = 'filament.pages.alerts';

    protected static ?string $navigationLabel = 'Alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Tasks & alerts';

    protected static ?int $navigationSort = 20;

    protected static function navigationBadgeCount(User $user): int
    {
        return app(GetAlerts::class)($user)->where('severity', 'danger')->count();
    }

    protected static function navigationBadgeTooltipText(): ?string
    {
        return 'Critical alerts';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('tasks.view') ?? false;
    }

    /** @return Collection<int, array<string, string>> */
    public function getAlertsProperty(): Collection
    {
        return app(GetAlerts::class)(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('notify')->label('Send critical alerts now')->icon('heroicon-o-paper-airplane')->requiresConfirmation()
                ->visible(fn () => auth()->user()->can('tasks.approve'))
                ->modalDescription('Notifies each person about critical alerts in the areas they can see, once a day each.')
                ->action(fn () => Notification::make()->title(app(SendAlertNotifications::class)().' people notified')->success()->send()),
        ];
    }
}
