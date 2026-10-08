<?php

namespace App\Filament\Resources\FeedProductionOrders;

use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Enums\FeedProductionStatus;
use App\Enums\FormulaStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\FeedProductionOrders\Pages\CreateFeedProductionOrder;
use App\Filament\Resources\FeedProductionOrders\Pages\ListFeedProductionOrders;
use App\Filament\Resources\FeedProductionOrders\Pages\ViewFeedProductionOrder;
use App\Filament\Resources\FeedProductionOrders\RelationManagers\MaterialsRelationManager;
use App\Filament\Support\StockForms;
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

/** Batches of feed to make: planned from a formula, confirmed by production staff, completed into stock. */
class FeedProductionOrderResource extends Resource
{
    protected static ?string $model = FeedProductionOrder::class;

    protected static ?string $navigationLabel = 'Production orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Feed mill';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Production')->description('What to make, how much and when.')->columnSpanFull()->columns(2)->schema([
                Select::make('feed_formula_id')->label('Formula')->required()->searchable()
                    ->options(fn () => FeedFormula::where('status', FormulaStatus::Active)->orderBy('code')->get()->mapWithKeys(fn ($f) => [$f->id => "{$f->code} v{$f->version} - {$f->name}"])->all()),
                TextInput::make('planned_output_kg')->label('Finished feed to make (kg)')->numeric()->minValue(0.001)->step(0.001)->required(),
                DatePicker::make('planned_on')->default(now())->required(),
            ]),
            Section::make('Stores')->description('Where the materials come from and the feed goes.')->columnSpanFull()->columns(2)->schema([
                StockForms::store('source_location_id', 'Take materials from'),
                StockForms::store('output_location_id', 'Put finished feed in'),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['formula.feedType', 'batch']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('formula.name')->label('Formula')->description(fn (FeedProductionOrder $r) => "{$r->formula->code} v{$r->formula->version}"),
                TextColumn::make('planned_output_kg')->label('Planned (kg)')->numeric(decimalPlaces: 3),
                TextColumn::make('batch.output_kg')->label('Made (kg)')->numeric(decimalPlaces: 3)->placeholder('-'),
                TextColumn::make('planned_on')->date()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        FeedProductionStatus::Completed => 'success', FeedProductionStatus::Planned => 'warning', default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(FeedProductionStatus::cases()))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [MaterialsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedProductionOrders::route('/'),
            'create' => CreateFeedProductionOrder::route('/create'),
            'view' => ViewFeedProductionOrder::route('/{record}'),
        ];
    }
}
