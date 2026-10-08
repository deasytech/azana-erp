<?php

namespace App\Filament\Resources\SlaughterBatches;

use App\Domain\Slaughter\Models\SlaughterBatch;
use App\Enums\SlaughterBatchStatus;
use App\Enums\SlaughterRecordStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\SlaughterBatches\Pages\CreateSlaughterBatch;
use App\Filament\Resources\SlaughterBatches\Pages\ListSlaughterBatches;
use App\Filament\Resources\SlaughterBatches\Pages\ViewSlaughterBatch;
use App\Filament\Resources\SlaughterBatches\RelationManagers\RecordsRelationManager;
use App\Filament\Support\FormSections;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Slaughter days: scheduled, pigs received and inspected, slaughtered, then closed. */
class SlaughterBatchResource extends Resource
{
    protected static ?string $model = SlaughterBatch::class;

    protected static ?string $navigationLabel = 'Slaughter days';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSections::make('Slaughter batch', 'The day the pigs in this batch are slaughtered.', [
                DatePicker::make('scheduled_on')->label('Slaughter date')->default(now())->required(),
                Textarea::make('notes'),
            ], Heroicon::OutlinedCalendarDays),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'records',
                'records as slaughtered_count' => fn ($q) => $q->whereIn('status', [SlaughterRecordStatus::Slaughtered, SlaughterRecordStatus::Condemned]),
            ]))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('scheduled_on')->date()->sortable(),
                TextColumn::make('records_count')->label('Received'),
                TextColumn::make('slaughtered_count')->label('Slaughtered'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        SlaughterBatchStatus::Completed => 'success', SlaughterBatchStatus::InProgress => 'warning', SlaughterBatchStatus::Cancelled => 'danger', default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(SlaughterBatchStatus::cases()))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('scheduled_on', 'desc');
    }

    public static function getRelations(): array
    {
        return [RecordsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlaughterBatches::route('/'),
            'create' => CreateSlaughterBatch::route('/create'),
            'view' => ViewSlaughterBatch::route('/{record}'),
        ];
    }
}
