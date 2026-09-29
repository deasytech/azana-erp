<?php

namespace App\Filament\Resources\PriceLists\RelationManagers;

use App\Filament\Support\MoneyInput;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Prices';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(60)
                ->mutateStateForValidationUsing(fn (?string $state) => $state === null ? null : strtoupper(trim($state)))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('price_list_id', $this->getOwnerRecord()->getKey())),
            TextInput::make('description')->required()->maxLength(255),
            Select::make('unit_id')->label('Unit')->relationship('unit', 'name')->required()->preload()->searchable(),
            MoneyInput::make('unit_price_minor', 'Unit price')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('description')->searchable(),
                TextColumn::make('unit.code')->label('Unit'),
                TextColumn::make('unit_price_minor')->label('Unit price')
                    ->state(fn ($record) => $record->unitPrice()->format()),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
    }
}
