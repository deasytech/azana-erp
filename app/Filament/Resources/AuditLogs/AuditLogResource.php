<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only trail: no create/edit/delete pages exist. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $modelLabel = 'Audit log';

    protected static ?string $pluralModelLabel = 'Audit log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Who')->placeholder('System'),
                TextColumn::make('event')->badge()->searchable(),
                TextColumn::make('auditable_type')->label('Record')->formatStateUsing(fn (?string $state, $record) => $state ? class_basename($state).' #'.$record->auditable_id : null),
                TextColumn::make('old_values')->label('Before')->formatStateUsing(fn ($state) => json_encode($state))->limit(60)->tooltip(fn ($state) => json_encode($state))->toggleable(),
                TextColumn::make('new_values')->label('After')->formatStateUsing(fn ($state) => json_encode($state))->limit(60)->tooltip(fn ($state) => json_encode($state))->toggleable(),
                TextColumn::make('reason')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approver.name')->label('Approved by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options(fn () => AuditLog::query()->distinct()->pluck('event', 'event')->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
