<?php

namespace App\Filament\Resources\Accounts;

use App\Domain\Finance\Models\Account;
use App\Enums\AccountType;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

/** The chart of accounts. An account is deactivated, not deleted, once it has been used. */
class AccountResource extends MasterResource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationLabel = 'Chart of accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 90;

    protected static int $codeLength = 10;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('type')->options(AnimalResource::enumOptions(AccountType::cases()))->required()->disabledOn('edit')->helperText('Fixed once the account is made.'),
            Textarea::make('notes'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => $state->label()),
        ];
    }

    protected static function filters(): array
    {
        return [SelectFilter::make('type')->options(AnimalResource::enumOptions(AccountType::cases()))];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
