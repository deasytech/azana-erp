<?php

namespace App\Filament\Resources\LookupValues;

use App\Domain\Farm\Models\LookupValue;
use App\Enums\LookupCategory;
use App\Filament\Resources\LookupValues\Pages\CreateLookupValue;
use App\Filament\Resources\LookupValues\Pages\EditLookupValue;
use App\Filament\Resources\LookupValues\Pages\ListLookupValues;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class LookupValueResource extends Resource
{
    protected static ?string $model = LookupValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')->options(collect(LookupCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())->required()->disabledOn('edit')->dehydrated(),
            TextInput::make('code')->required()->maxLength(60)->alphaDash()->mutateStateForValidationUsing(fn (?string $state) => $state === null ? null : strtolower(trim($state)))->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('category', $get('category')))->helperText('Stored in lower case.'),
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('description')->maxLength(255),
            TextInput::make('sort_order')->numeric()->integer()->default(0)->minValue(0),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->badge()->formatStateUsing(fn ($state) => $state->label())->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->options(collect(LookupCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('category');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLookupValues::route('/'),
            'create' => CreateLookupValue::route('/create'),
            'edit' => EditLookupValue::route('/{record}/edit'),
        ];
    }
}
