<?php

namespace App\Filament\Resources\Suppliers;

use App\Domain\Supplier\Models\Supplier;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class SupplierResource extends MasterResource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 50;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            TextInput::make('contact_name')->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(40),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('tax_number')->maxLength(60),
            TextInput::make('payment_terms_days')->label('Payment terms (days)')->numeric()->integer()->minValue(0)->maxValue(365)->default(0)->required()
                ->helperText('How long after an invoice date it falls due. Copied onto each order when it is made.'),
            Textarea::make('address'),
            Textarea::make('notes'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('contact_name')->label('Contact')->placeholder('-'),
            TextColumn::make('phone')->placeholder('-'),
            TextColumn::make('payment_terms_days')->label('Terms (days)'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
