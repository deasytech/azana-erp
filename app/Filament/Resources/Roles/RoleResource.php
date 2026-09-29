<?php

namespace App\Filament\Resources\Roles;

use App\Enums\Module;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        $matrix = collect(Module::cases())->map(fn (Module $module) => CheckboxList::make("matrix.{$module->value}")
            ->label($module->label())
            ->options(collect($module->actions())->mapWithKeys(fn ($a) => [$a->value => $a->label()])->all())
            ->columns(4)
            ->bulkToggleable())->all();

        return $schema->components([
            TextInput::make('name')->required()->maxLength(125)->unique(ignoreRecord: true),
            TextInput::make('description')->maxLength(255),
            Toggle::make('requires_two_factor')
                ->label('Require two-factor authentication')
                ->helperText('Users holding this role must set up an authenticator app before using the ERP.'),
            Section::make('Permission matrix')->schema($matrix)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('description')->wrap(),
                TextColumn::make('users_count')->counts('users')->label('Users'),
                IconColumn::make('requires_two_factor')->label('2FA')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
