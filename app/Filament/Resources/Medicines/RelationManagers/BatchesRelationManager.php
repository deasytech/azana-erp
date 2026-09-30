<?php

namespace App\Filament\Resources\Medicines\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Batches received: identity and expiry only. Stock quantities join the inventory ledger in Phase 08. */
class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('batch_number')->required()->maxLength(60)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('medicine_id', $this->getOwnerRecord()->getKey())),
            DatePicker::make('expiry_date')->required(),
            DatePicker::make('received_on')->maxDate(now()),
            TextInput::make('supplier_name')->maxLength(255),
            TextInput::make('quantity_received')->numeric()->minValue(0)->step(0.001),
            Toggle::make('is_active')->label('In use')->default(true),
            Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')->searchable(),
                TextColumn::make('expiry_date')->date()->sortable()->color(fn ($record) => $record->expiry_date->isPast() ? 'danger' : null),
                TextColumn::make('supplier_name')->placeholder('-'),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('expiry_date');
    }
}
