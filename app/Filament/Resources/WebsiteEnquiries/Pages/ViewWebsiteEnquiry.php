<?php

namespace App\Filament\Resources\WebsiteEnquiries\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\WebsiteEnquiries\WebsiteEnquiryResource;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewWebsiteEnquiry extends ViewRecord
{
    protected static string $resource = WebsiteEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [ActionGroup::make(WebsiteEnquiryResource::actions())->label('Respond')->button()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('number'), TextEntry::make('created_at')->label('Received')->dateTime(), TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
            TextEntry::make('name'), TextEntry::make('organisation')->placeholder('-'), TextEntry::make('kind')->formatStateUsing(fn ($state) => $state->label()),
            TextEntry::make('email')->placeholder('-'), TextEntry::make('phone')->placeholder('-'), TextEntry::make('quantity')->placeholder('-'),
            TextEntry::make('listing.title')->label('About listing')->placeholder('-'),
            TextEntry::make('message')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap']),
            TextEntry::make('customer.name')->label('Customer')->placeholder('-')->url(fn ($record) => $record->customer_id ? CustomerResource::getUrl('view', ['record' => $record->customer_id]) : null),
            TextEntry::make('handler.name')->label('Last handled by')->placeholder('-'), TextEntry::make('handled_at')->dateTime()->placeholder('-'),
            TextEntry::make('staff_note')->label('Staff note')->placeholder('-')->columnSpanFull(),
        ]);
    }
}
