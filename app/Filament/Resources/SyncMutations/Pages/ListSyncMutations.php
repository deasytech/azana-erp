<?php

namespace App\Filament\Resources\SyncMutations\Pages;

use App\Domain\Mobile\Models\SyncMutation;
use App\Filament\Resources\SyncMutations\SyncMutationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSyncMutations extends ListRecords
{
    protected static string $resource = SyncMutationResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'attention';
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'attention' => Tab::make('Needs attention')->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', SyncMutation::ACCEPTED)->whereNull('reviewed_at')),
            'all' => Tab::make('Everything'),
        ];
    }
}
