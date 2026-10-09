<?php

namespace App\Filament\Pages;

use App\Domain\System\Actions\ResetToLiveData;
use App\Domain\System\Exceptions\DomainException;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Clears the practice records before real data starts, keeping the set-up (users, roles, farm, settings, chart of accounts). */
class GoLiveDataReset extends Page
{
    private const PHRASE = 'RESET';

    protected string $view = 'filament.pages.go-live-data-reset';

    protected static ?string $navigationLabel = 'Go-live data reset';

    protected static ?string $title = 'Go-live data reset';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::OWNER) ?? false;
    }

    public function getResetterProperty(): ResetToLiveData
    {
        return app(ResetToLiveData::class);
    }

    /** @return array<string, int> */
    public function getPreviewProperty(): array
    {
        return $this->resetter->preview();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')->label('Clear practice data')->icon('heroicon-o-trash')->color('danger')
                ->visible(fn () => $this->resetter->allowed())
                ->modalHeading('Clear all practice data?')
                ->modalDescription('Every animal, sale, purchase, stock movement, journal, task and other record is deleted for good. Users, roles, farm settings, the chart of accounts and the standard lists stay. This cannot be undone, except from the backup.')
                ->modalSubmitActionLabel('Delete practice data')
                ->schema([
                    Toggle::make('backup')->label('Take a database backup first')->default(true)->helperText('Recommended. If the backup fails, nothing is deleted.'),
                    Toggle::make('accounts')->label('Also remove the demo accounts (…'.ResetToLiveData::DEMO_EMAIL_SUFFIX.') other than mine')
                        ->helperText($this->resetter->demoAccounts(auth()->user()).' demo account(s) found.')->default(false),
                    TextInput::make('confirm')->label('Type '.self::PHRASE.' to confirm')->required()->in([self::PHRASE])->validationMessages(['in' => 'Type '.self::PHRASE.' exactly.']),
                ])
                ->action(function (array $data, Action $action) {
                    try {
                        $result = ($this->resetter)(auth()->user(), (bool) $data['backup'], (bool) $data['accounts']);
                    } catch (DomainException $e) {
                        Notification::make()->title('Nothing was deleted')->body($e->getMessage())->danger()->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->title('The system is ready for real data')
                        ->body(number_format($result['rows']).' practice records were cleared from '.$result['tables'].' tables.')->success()->persistent()->send();

                    $this->redirect(Dashboard::getUrl());
                }),
        ];
    }
}
