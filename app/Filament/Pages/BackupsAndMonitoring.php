<?php

namespace App\Filament\Pages;

use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Backup\Data\StatusCheck;
use App\Domain\Backup\Models\BackupRun;
use App\Jobs\RunBackup;
use App\Jobs\RunBackupRestoreTest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Is the system looking after itself: backups taken and proven, the scheduler and queue running, disk space, services up. */
class BackupsAndMonitoring extends Page
{
    protected string $view = 'filament.pages.backups-and-monitoring';

    protected static ?string $navigationLabel = 'Backups & monitoring';

    protected static ?string $title = 'Backups & monitoring';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', BackupRun::class) ?? false;
    }

    /** @return list<StatusCheck> */
    public function getChecksProperty(): array
    {
        return app(GetOperationsStatus::class)();
    }

    /** @return Collection<int, BackupRun> */
    public function getRunsProperty(): Collection
    {
        return BackupRun::query()->latest('started_at')->limit(20)->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')->label('Back up now')->icon('heroicon-o-circle-stack')->requiresConfirmation()
                ->modalDescription('Takes a full database backup in the background and applies the retention rule. It appears below when it is done.')
                ->visible(fn () => auth()->user()->can('backups.create'))
                ->action(function () {
                    RunBackup::dispatch(auth()->id());
                    Notification::make()->title('Backup started')->body('Refresh in a minute to see the result.')->success()->send();
                }),
            Action::make('restoreTest')->label('Test a restore')->icon('heroicon-o-arrow-path-rounded-square')->requiresConfirmation()
                ->modalDescription('Loads the newest backup into a scratch database to prove it can be restored. Nothing live is touched.')
                ->visible(fn () => auth()->user()->can('backups.edit'))
                ->action(function () {
                    RunBackupRestoreTest::dispatch(auth()->id());
                    Notification::make()->title('Restore test started')->body('Refresh in a minute to see the result.')->success()->send();
                }),
        ];
    }
}
