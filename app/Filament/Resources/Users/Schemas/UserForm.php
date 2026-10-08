<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\FormSections;
use App\Models\Role;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSections::make('Account', 'Who this is and how they sign in.', [
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
                ], Heroicon::OutlinedUserCircle),
                FormSections::make('Access', 'What this person may do, and whether the account is in use.', [
                    Select::make('roles')
                        ->relationship(
                            'roles',
                            'name',
                            // Only an Owner may hand out the Owner role (it carries the permission bypass).
                            modifyQueryUsing: fn ($query) => auth()->user()?->isOwner() ? $query : $query->where('name', '!=', Role::OWNER),
                        )
                        ->multiple()
                        ->preload()
                        ->required()
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                            if (! auth()->user()?->isOwner() && Role::whereIn('id', (array) $value)->where('name', Role::OWNER)->exists()) {
                                $fail('Only an Owner/Director can assign the Owner/Director role.');
                            }
                        }),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Deactivate instead of deleting: accounts keep their audit history.')
                        ->disabled(fn (?User $record) => $record?->getKey() === auth()->id()),
                ], Heroicon::OutlinedShieldCheck),
            ]);
    }
}
