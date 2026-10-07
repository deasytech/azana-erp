<?php

namespace App\Filament\Resources\SyncMutations\Pages;

use App\Filament\Resources\SyncMutations\SyncMutationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSyncMutation extends ViewRecord
{
    protected static string $resource = SyncMutationResource::class;

    protected function getHeaderActions(): array
    {
        return [SyncMutationResource::review()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('client_id'), TextEntry::make('type'), TextEntry::make('status')->badge(),
            TextEntry::make('user.name')->label('Worker'), TextEntry::make('device_id')->label('Device'), TextEntry::make('attempts'),
            TextEntry::make('occurred_at')->label('Happened on the device')->dateTime(), TextEntry::make('attempted_at')->dateTime(), TextEntry::make('synced_at')->dateTime()->placeholder('-'),
            TextEntry::make('error_code')->placeholder('-'), TextEntry::make('error_message')->placeholder('-')->columnSpan(2),
            TextEntry::make('server_type')->placeholder('-'), TextEntry::make('server_id')->placeholder('-'),
            TextEntry::make('payload')->label('What the device sent')->state(fn ($record) => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace']),
            TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('-'), TextEntry::make('reviewed_at')->dateTime()->placeholder('-'), TextEntry::make('review_note')->placeholder('-'),
        ]);
    }
}
