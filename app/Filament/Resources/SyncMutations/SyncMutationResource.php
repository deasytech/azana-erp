<?php

namespace App\Filament\Resources\SyncMutations;

use App\Domain\Mobile\Models\SyncMutation;
use App\Filament\Resources\SyncMutations\Pages\ListSyncMutations;
use App\Filament\Resources\SyncMutations\Pages\ViewSyncMutation;
use App\Filament\Support\DomainAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** What the mobile devices have sent and what became of it. Conflicts and rejections wait here for a supervisor to look at them. */
class SyncMutationResource extends Resource
{
    protected static ?string $model = SyncMutation::class;

    protected static ?string $navigationLabel = 'Mobile sync log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Tasks & alerts';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'client_id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'reviewedBy']))
            ->columns([
                TextColumn::make('occurred_at')->label('Happened')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Worker'),
                TextColumn::make('device_id')->label('Device')->limit(16),
                TextColumn::make('type')->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->ucfirst()->toString()),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        SyncMutation::ACCEPTED => 'success', SyncMutation::CONFLICT => 'warning', default => 'danger'
                    }),
                TextColumn::make('error_message')->label('Why')->limit(60)->placeholder('-')->description(fn (SyncMutation $r) => $r->reviewed_at ? 'Reviewed by '.($r->reviewedBy?->name ?? '-') : null),
                TextColumn::make('attempts')->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($statuses = [SyncMutation::ACCEPTED, SyncMutation::REJECTED, SyncMutation::CONFLICT, SyncMutation::FAILED], array_map('ucfirst', $statuses))),
                SelectFilter::make('type')->options(fn () => SyncMutation::query()->distinct()->orderBy('type')->pluck('type', 'type')->all()),
            ])
            ->recordActions([ViewAction::make(), static::review()])
            ->defaultSort('id', 'desc');
    }

    public static function review(): Action
    {
        return Action::make('review')->label('Mark reviewed')->icon('heroicon-o-check')
            ->visible(fn (SyncMutation $r) => $r->status !== SyncMutation::ACCEPTED && $r->reviewed_at === null && auth()->user()->can('update', $r))
            ->schema([Textarea::make('note')->label('What was done about it')->required()])
            ->action(fn (SyncMutation $record, array $data, Action $action) => DomainAction::run(function () use ($record, $data) {
                $record->update(['reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'review_note' => trim($data['note'])]);
            }, $action, 'Marked as reviewed'));
    }

    public static function getPages(): array
    {
        return ['index' => ListSyncMutations::route('/'), 'view' => ViewSyncMutation::route('/{record}')];
    }
}
