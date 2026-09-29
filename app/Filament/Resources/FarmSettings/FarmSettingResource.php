<?php

namespace App\Filament\Resources\FarmSettings;

use App\Domain\Farm\Models\FarmSetting;
use App\Filament\Resources\FarmSettings\Pages\EditFarmSetting;
use App\Filament\Resources\FarmSettings\Pages\ListFarmSettings;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Production assumptions and targets. Rows come from the registered definitions; values are edited here. */
class FarmSettingResource extends Resource
{
    protected static ?string $model = FarmSetting::class;

    protected static ?string $modelLabel = 'setting';

    protected static ?string $navigationLabel = 'Farm settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'key';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('Setting')
                ->disabled()
                ->dehydrated(false)
                ->afterStateHydrated(fn ($component, ?FarmSetting $record) => $component->state($record?->definition()?->label ?? $record?->key)),
            TextInput::make('value')
                ->required()
                ->helperText(fn (?FarmSetting $record) => $record?->definition()?->description),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group')->state(fn (FarmSetting $r) => $r->definition()?->group ?? 'Other')->badge(),
                TextColumn::make('label')->state(fn (FarmSetting $r) => $r->definition()?->label ?? $r->key),
                TextColumn::make('key')->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('value'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarmSettings::route('/'),
            'edit' => EditFarmSetting::route('/{record}/edit'),
        ];
    }
}
