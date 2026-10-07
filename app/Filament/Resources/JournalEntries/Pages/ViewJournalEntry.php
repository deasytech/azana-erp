<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\MoneyColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [JournalEntryResource::approve(), JournalEntryResource::reject(), JournalEntryResource::reverse()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('number'), TextEntry::make('entry_date')->date(), TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('createdBy.name')->label('Entered by')->placeholder('System'), TextEntry::make('decidedBy.name')->label('Decided by')->placeholder('-'),
            TextEntry::make('decision_notes')->placeholder('-'),
            RepeatableEntry::make('lines')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('account.name')->label('Account'), TextEntry::make('costCentre.name')->label('Cost centre')->placeholder('-'),
                TextEntry::make('debit_minor')->label('Debit')->formatStateUsing(fn ($state) => $state ? MoneyColumn::format($state) : ''),
                TextEntry::make('credit_minor')->label('Credit')->formatStateUsing(fn ($state) => $state ? MoneyColumn::format($state) : ''),
            ]),
        ]);
    }
}
