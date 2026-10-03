<?php

namespace App\Filament\Resources\StockCounts;

use App\Domain\Inventory\Models\StockCount;
use App\Enums\StockCountStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\StockCounts\Pages\CreateStockCount;
use App\Filament\Resources\StockCounts\Pages\ListStockCounts;
use App\Filament\Resources\StockCounts\Pages\ViewStockCount;
use App\Filament\Resources\StockCounts\RelationManagers\LinesRelationManager;
use App\Filament\Support\StockForms;
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

/** Physical stock counts of a store; differences become adjustments once approved. */
class StockCountResource extends Resource
{
    protected static ?string $model = StockCount::class;

    protected static ?string $navigationLabel = 'Stock counts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            StockForms::store(),
            DatePicker::make('counted_on')->default(now())->maxDate(now())->required(),
            Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'startedBy'])->withCount('lines'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('location.name')->label('Store'),
                TextColumn::make('counted_on')->date()->sortable(),
                TextColumn::make('lines_count')->label('Lines'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        StockCountStatus::Approved => 'success', StockCountStatus::Rejected => 'danger',
                        StockCountStatus::Submitted => 'warning', default => 'gray',
                    }),
                TextColumn::make('startedBy.name')->label('Started by')->placeholder('-'),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(StockCountStatus::cases()))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockCounts::route('/'),
            'create' => CreateStockCount::route('/create'),
            'view' => ViewStockCount::route('/{record}'),
        ];
    }
}
