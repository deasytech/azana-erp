<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->rule(Password::default())
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Deactivate instead of deleting: accounts keep their audit history.')
                    ->disabled(fn (?User $record) => $record?->getKey() === auth()->id()),
            ]);
    }
}
