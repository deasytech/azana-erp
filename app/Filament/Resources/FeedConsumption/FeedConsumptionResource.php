<?php

namespace App\Filament\Resources\FeedConsumption;

use App\Domain\Farm\Models\Farm;
use App\Domain\Feed\Actions\VoidFeedConsumption;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Production\Models\ProductionBatch;
use App\Enums\BatchStatus;
use App\Filament\Resources\FeedConsumption\Pages\CreateFeedConsumption;
use App\Filament\Resources\FeedConsumption\Pages\ListFeedConsumption;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\DomainAction;
use App\Filament\Support\MoneyInput;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FeedConsumptionResource extends Resource
{
    protected static ?string $model = FeedConsumptionRecord::class;

    protected static ?string $navigationLabel = 'Feed records';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('target')->label('Fed to')->options(['batch' => 'A batch', 'animal' => 'One animal'])->default('batch')->required()->live(),
            Select::make('production_batch_id')->label('Batch')->searchable()
                ->options(fn () => ProductionBatch::where('status', BatchStatus::Active->value)->orderBy('code')->get()->mapWithKeys(fn ($b) => [$b->id => "{$b->code} - {$b->name}"])->all())
                ->visible(fn ($get) => $get('target') !== 'animal')->required(fn ($get) => $get('target') !== 'animal'),
            AnimalPicker::any()->visible(fn ($get) => $get('target') === 'animal')->required(fn ($get) => $get('target') === 'animal'),
            Select::make('feed_type_id')->label('Feed type')->required()->options(fn () => FeedType::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            DatePicker::make('consumed_on')->default(now())->maxDate(now())->required(),
            TextInput::make('quantity_kg')->label('Quantity (kg)')->numeric()->minValue(0.01)->step(0.01)->required(),
            MoneyInput::make('cost_per_kg_minor', 'Cost per kg'),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['batch', 'animal', 'feedType']))
            ->columns([
                TextColumn::make('consumed_on')->date()->sortable(),
                TextColumn::make('fed_to')->label('Fed to')->state(fn (FeedConsumptionRecord $r) => $r->batch?->code ?? $r->animal?->animal_number)->searchable(false),
                TextColumn::make('feedType.name')->label('Feed'),
                TextColumn::make('quantity_kg')->label('kg')->numeric(decimalPlaces: 2),
                TextColumn::make('cost_minor')->label('Cost')->state(fn (FeedConsumptionRecord $r) => $r->cost_minor === null ? '-' : Money::ofMinor($r->cost_minor, Farm::defaultCurrency())->format()),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn (FeedConsumptionRecord $r) => $r->isVoided()),
            ])
            ->filters([
                SelectFilter::make('production_batch_id')->label('Batch')->relationship('batch', 'code'),
                SelectFilter::make('feed_type_id')->label('Feed type')->relationship('feedType', 'name'),
            ])
            ->recordActions([
                Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
                    ->visible(fn (FeedConsumptionRecord $record) => ! $record->isVoided() && auth()->user()->can('update', $record))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (FeedConsumptionRecord $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(VoidFeedConsumption::class)($record, $data['reason']), $action, 'Feed record voided')),
            ])
            ->defaultSort('consumed_on', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListFeedConsumption::route('/'), 'create' => CreateFeedConsumption::route('/create')];
    }
}
