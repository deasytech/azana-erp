<?php

namespace App\Filament\Resources\WebsiteEnquiries\Pages;

use App\Enums\EnquiryStatus;
use App\Filament\Resources\WebsiteEnquiries\WebsiteEnquiryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWebsiteEnquiries extends ListRecords
{
    protected static string $resource = WebsiteEnquiryResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [EnquiryStatus::New, EnquiryStatus::Contacted, EnquiryStatus::Quoted])),
            'all' => Tab::make('Everything'),
        ];
    }
}
