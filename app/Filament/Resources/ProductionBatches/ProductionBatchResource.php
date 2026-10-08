<?php

namespace App\Filament\Resources\ProductionBatches;

use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\Pen;
use App\Domain\Production\Models\ProductionBatch;
use App\Enums\BatchStatus;
use App\Enums\LookupCategory;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\ProductionBatches\Pages\CreateProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\ListProductionBatches;
use App\Filament\Resources\ProductionBatches\Pages\ViewProductionBatch;
use App\Filament\Resources\ProductionBatches\RelationManagers\AnimalsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\CostsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\EventsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\FeedRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\WeighInsRelationManager;
use App\Filament\Support\LookupSelect;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductionBatchResource extends Resource
{
    protected static ?string $model = ProductionBatch::class;

    protected static ?string $navigationLabel = 'Batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'code';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch')->description('What the group is and when it started.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                LookupSelect::make('stage_id', 'stage', LookupCategory::AnimalCategory, 'Stage')->required(),
                DatePicker::make('started_on')->required()->default(now())->maxDate(now()),
                TextInput::make('count')->label('Pigs placed')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('average_weight_kg')->label('Average weight at placement (kg)')->numeric()->minValue(0.01)->step(0.01),
            ]),
            Section::make('Placement')->description('The pigs as they enter the batch.')->columnSpanFull()->columns(2)->schema([
                MoneyInput::make('unit_cost_minor', 'Cost per pig at entry'),
                TextInput::make('placed_age_days')->label('Average age at placement (days)')->numeric()->integer()->minValue(0),
                TextInput::make('target_weight_kg')->label('Target market weight (kg)')->numeric()->minValue(0.01)->step(0.01)->helperText('Leave empty to use the farm setting.'),
            ]),
            Section::make('Housing and source')->description('Where the batch is kept and where it came from.')->columnSpanFull()->columns(2)->schema([
                Select::make('breed_id')->label('Breed')->searchable()->options(fn () => Breed::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                Select::make('pen_id')->label('Pen')->searchable()->options(fn () => Pen::where('is_active', true)->orderBy('code')->pluck('code', 'id')),
                TextInput::make('source_note')->label('Source')->maxLength(255)->helperText('For example: weaned from litters L01-L04, or bought from a supplier.'),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['stage', 'pen'])->withSum('events as heads', 'delta'))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('stage.name')->label('Stage')->badge(),
                TextColumn::make('pen.code')->label('Pen')->placeholder('-'),
                TextColumn::make('heads')->label('Pigs')->numeric(),
                TextColumn::make('started_on')->date()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state === BatchStatus::Active ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(BatchStatus::cases()))->default(BatchStatus::Active->value),
                SelectFilter::make('stage_id')->label('Stage')->relationship('stage', 'name'),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('started_on', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            EventsRelationManager::class,
            WeighInsRelationManager::class,
            FeedRelationManager::class,
            CostsRelationManager::class,
            AnimalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionBatches::route('/'),
            'create' => CreateProductionBatch::route('/create'),
            'view' => ViewProductionBatch::route('/{record}'),
        ];
    }
}
