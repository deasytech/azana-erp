<?php

namespace App\Filament\Resources\MeatProductionBatches;

use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Slaughter\Models\Carcass;
use App\Enums\CarcassStatus;
use App\Enums\MeatProductionStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\MeatProductionBatches\Pages\CreateMeatProductionBatch;
use App\Filament\Resources\MeatProductionBatches\Pages\ListMeatProductionBatches;
use App\Filament\Resources\MeatProductionBatches\Pages\ViewMeatProductionBatch;
use App\Filament\Resources\MeatProductionBatches\RelationManagers\LinesRelationManager;
use App\Filament\Resources\MeatProductionBatches\RelationManagers\SourcesRelationManager;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
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

/** Carcasses cut into products and put into a cold room's stock, with the cost shared out by weight. */
class MeatProductionBatchResource extends Resource
{
    protected static ?string $model = MeatProductionBatch::class;

    protected static ?string $navigationLabel = 'Meat production';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Source')->description('Which carcasses are being processed, and where the meat goes.')->columnSpanFull()->columns(2)->schema([
                Select::make('carcass_ids')->label('Carcasses')->multiple()->required()->searchable()->columnSpanFull()
                    ->options(fn () => Carcass::with('record.animal')->where('status', CarcassStatus::Hanging)->orderBy('id')->get()
                        ->mapWithKeys(fn (Carcass $c) => [$c->id => "{$c->number} - {$c->record->sourceLabel()} ({$c->usableKg()} kg usable)"])->all())
                    ->helperText('Carcasses hanging in the chiller. Everything made must weigh no more than they do.'),
                Select::make('inventory_location_id')->label('Cold room')->required()->searchable()
                    ->options(fn () => InventoryLocation::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('produced_on')->default(now())->maxDate(now())->required(),
            ]),
            Section::make('Products made')->description('What came out of the carcasses.')->columnSpanFull()->schema([
                Repeater::make('lines')->hiddenLabel()->label('Products made')->columnSpanFull()->minItems(1)->columns(2)->addActionLabel('Add product')
                    ->schema([
                        Select::make('meat_product_id')->label('Product')->required()->searchable()->distinct()
                            ->options(fn () => MeatProduct::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                        TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(0.01)->step(0.01)->required(),
                    ]),
            ]),
            Section::make('Waste and cost')->description('What was lost and what processing cost.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('waste_kg')->label('Waste (kg)')->numeric()->minValue(0)->step(0.01)->default(0)->helperText('Trim, bone and anything thrown away.'),
                MoneyInput::make('other_cost_minor', 'Other processing costs')->helperText('Labour, power, packaging; added to what the pigs cost to raise.'),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('location'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('produced_on')->date()->sortable(),
                TextColumn::make('location.name')->label('Cold room'),
                TextColumn::make('input_kg')->label('From (kg)'),
                TextColumn::make('output_kg')->label('Made (kg)'),
                TextColumn::make('waste_kg')->label('Waste (kg)'),
                MoneyColumn::make('total_cost_minor', 'Total cost'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state === MeatProductionStatus::Produced ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(MeatProductionStatus::cases()))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class, SourcesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeatProductionBatches::route('/'),
            'create' => CreateMeatProductionBatch::route('/create'),
            'view' => ViewMeatProductionBatch::route('/{record}'),
        ];
    }
}
