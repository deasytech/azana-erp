<?php

namespace App\Filament\Resources\LoginActivities;

use App\Filament\Resources\LoginActivities\Pages\ListLoginActivities;
use App\Models\LoginActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only trail: no create/edit/delete pages exist. */
class LoginActivityResource extends Resource
{
    protected static ?string $model = LoginActivity::class;

    protected static ?string $modelLabel = 'Login activity';

    protected static ?string $pluralModelLabel = 'Login activity';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('event')->badge()->color(fn (string $state) => match ($state) {
                    'login' => 'success', 'failed', 'lockout' => 'danger', default => 'gray'
                }),
                TextColumn::make('user.name')->label('User')->placeholder('Unknown'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('ip_address')->label('IP')->searchable(),
                TextColumn::make('user_agent')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options(['login' => 'Login', 'logout' => 'Logout', 'failed' => 'Failed', 'lockout' => 'Lockout']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginActivities::route('/')];
    }
}
