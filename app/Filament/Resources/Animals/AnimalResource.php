<?php

namespace App\Filament\Resources\Animals;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\WeightRecord;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\GeneticLine;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Pen;
use App\Enums\AnimalSex;
use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\IdentifierType;
use App\Enums\LookupCategory;
use App\Filament\Resources\Animals\Pages\CreateAnimal;
use App\Filament\Resources\Animals\Pages\EditAnimal;
use App\Filament\Resources\Animals\Pages\ListAnimals;
use App\Filament\Resources\Animals\Pages\ViewAnimal;
use App\Filament\Resources\Animals\RelationManagers\IdentifiersRelationManager;
use App\Filament\Resources\Animals\RelationManagers\LittersRelationManager;
use App\Filament\Resources\Animals\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\Animals\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Animals\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Resources\Animals\RelationManagers\WeightsRelationManager;
use App\Filament\Support\DateRangeFilter;
use App\Filament\Support\LookupSelect;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AnimalResource extends Resource
{
    protected static ?string $model = Animal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Animals';

    protected static ?int $navigationSort = 10;

    private const NUMBER = 'animal_number';

    protected static ?string $recordTitleAttribute = self::NUMBER;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [self::NUMBER, 'identifiers.value'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails($record): array
    {
        return ['Category' => $record->category->name, 'Status' => $record->status->label()];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('category');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')->columns(2)->schema([
                TextInput::make(self::NUMBER)->disabled()->dehydrated(false)->visibleOn('edit')
                    ->helperText('Permanent number, issued at registration.'),
                Select::make('sex')->options(self::enumOptions(AnimalSex::cases()))->required()->disabledOn('edit'),
                LookupSelect::make('category_id', 'category', LookupCategory::AnimalCategory, 'Category')->required(),
                Select::make('breed_id')->label('Breed')->options(fn () => Breed::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->live()->afterStateUpdated(fn ($set) => $set('genetic_line_id', null)),
                Select::make('genetic_line_id')->label('Genetic line')->searchable()
                    ->options(fn ($get) => GeneticLine::where('is_active', true)
                        ->when($get('breed_id'), fn ($q, $breed) => $q->where('breed_id', $breed))->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('birth_date')->maxDate(now()),
                Toggle::make('birth_date_estimated')->label('Birth date is an estimate'),
            ]),
            Section::make('Source')->columns(2)->schema([
                Select::make('source')->options(self::enumOptions(AnimalSource::cases()))->default(AnimalSource::BornOnFarm->value)->required(),
                DatePicker::make('acquired_on')->maxDate(now()),
                TextInput::make('source_name')->label('Supplier / source farm')->maxLength(255),
                TextInput::make('source_reference')->label('Invoice / reference')->maxLength(255),
                MoneyInput::make('purchase_price_minor', 'Purchase price'),
            ]),
            Section::make('Parentage')->columns(2)->schema([
                self::parentSelect('sire_id', 'Sire (boar)', AnimalSex::Male),
                self::parentSelect('dam_id', 'Dam (sow)', AnimalSex::Female),
                TextInput::make('sire_note')->label('External sire')->maxLength(255)->helperText('For a sire not registered here.'),
                TextInput::make('dam_note')->label('External dam')->maxLength(255),
            ]),
            Section::make('First placement')->columns(2)->visibleOn('create')->schema([
                Select::make('pen_id')->label('Pen')->searchable()->live()
                    ->options(fn () => Pen::where('is_active', true)->orderBy('code')->pluck('code', 'id'))
                    ->disabled(fn ($get) => filled($get('location_id'))),
                Select::make('location_id')->label('Or location')->searchable()->live()
                    ->options(fn () => Location::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->disabled(fn ($get) => filled($get('pen_id'))),
            ]),
            Section::make('Identifiers')->visibleOn('create')->schema([
                Repeater::make('identifiers')->columns(2)->defaultItems(0)->addActionLabel('Add identifier')->schema([
                    Select::make('type')->options(self::enumOptions(IdentifierType::cases()))->required(),
                    TextInput::make('value')->required()->maxLength(100),
                ]),
            ]),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['category', 'breed', 'currentPen.building', 'currentLocation'])
                ->addSelect(['latest_weight_kg' => WeightRecord::select('weight_kg')
                    ->whereColumn('animal_id', 'animals.id')->whereNull('voided_at')
                    ->orderByDesc('weighed_at')->orderByDesc('id')->limit(1)]))
            ->columns([
                TextColumn::make(self::NUMBER)->label('Number')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category')->badge(),
                TextColumn::make('sex')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('breed.name')->label('Breed')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('position')->label('Position')->state(fn (Animal $r) => $r->positionLabel()),
                TextColumn::make('latest_weight_kg')->label('Latest kg')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('birth_date')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(AnimalStatus::cases()))->default(AnimalStatus::Active->value),
                SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
                SelectFilter::make('sex')->options(self::enumOptions(AnimalSex::cases())),
                SelectFilter::make('breed_id')->label('Breed')->relationship('breed', 'name'),
                SelectFilter::make('current_pen_id')->label('Pen')->relationship('currentPen', 'code')->searchable()->preload(),
                DateRangeFilter::make('birth_date', 'Born'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort(self::NUMBER);
    }

    public static function getRelations(): array
    {
        return [
            IdentifiersRelationManager::class,
            LittersRelationManager::class,
            MovementsRelationManager::class,
            WeightsRelationManager::class,
            StatusHistoryRelationManager::class,
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnimals::route('/'),
            'create' => CreateAnimal::route('/create'),
            'view' => ViewAnimal::route('/{record}'),
            'edit' => EditAnimal::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<int, AnimalSex|AnimalStatus|AnimalSource|IdentifierType>  $cases
     * @return array<string, string>
     */
    public static function enumOptions(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    private static function parentSelect(string $field, string $label, AnimalSex $sex): Select
    {
        return Select::make($field)->label($label)->searchable()
            ->getSearchResultsUsing(fn (string $search) => Animal::where('sex', $sex->value)->where(self::NUMBER, 'like', '%'.strtoupper($search).'%')->limit(30)->pluck('animal_number', 'id')->all())
            ->getOptionLabelUsing(fn ($value) => Animal::find($value)?->animal_number);
    }
}
