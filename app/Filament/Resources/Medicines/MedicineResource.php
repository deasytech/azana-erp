<?php

namespace App\Filament\Resources\Medicines;

use App\Domain\Health\Models\Medicine;
use App\Enums\LookupCategory;
use App\Filament\Resources\Medicines\Pages\CreateMedicine;
use App\Filament\Resources\Medicines\Pages\EditMedicine;
use App\Filament\Resources\Medicines\Pages\ListMedicines;
use App\Filament\Resources\Medicines\RelationManagers\BatchesRelationManager;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class MedicineResource extends MasterResource
{
    protected static ?string $model = Medicine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 50;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            static::lookupSelect('type_id', 'type', LookupCategory::MedicineType, 'Type'),
            Select::make('unit_id')->label('Unit')->relationship('unit', 'name')->searchable()->preload(),
            TextInput::make('default_withdrawal_days')->label('Withdrawal period (days)')->numeric()->integer()->minValue(0)->default(0)->required()->helperText('Animals cannot be sold or slaughtered until this many days after treatment.'),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('type.name')->label('Type')->badge(),
            TextColumn::make('default_withdrawal_days')->label('Withdrawal (days)'),
            TextColumn::make('batches_count')->counts('batches')->label('Batches'),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('type_id')->label('Type')->relationship('type', 'name'),
        ];
    }

    public static function getRelations(): array
    {
        return [BatchesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicines::route('/'),
            'create' => CreateMedicine::route('/create'),
            'edit' => EditMedicine::route('/{record}/edit'),
        ];
    }
}
