<?php

namespace App\Filament\Resources\FeedFormulas;

use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Enums\FormulaStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\FeedFormulas\Pages\CreateFeedFormula;
use App\Filament\Resources\FeedFormulas\Pages\EditFeedFormula;
use App\Filament\Resources\FeedFormulas\Pages\ListFeedFormulas;
use App\Filament\Resources\FeedFormulas\Pages\ViewFeedFormula;
use App\Filament\Resources\FeedFormulas\RelationManagers\IngredientsRelationManager;
use App\Filament\Support\CodeField;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Feed recipes, kept as versions: only a draft can be edited. */
class FeedFormulaResource extends Resource
{
    protected static ?string $model = FeedFormula::class;

    protected static ?string $navigationLabel = 'Formulas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Feed mill';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function canEdit(Model $record): bool
    {
        return $record->isDraft() && parent::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Formula')->description('What it is and what it makes.')->columnSpanFull()->columns(2)->schema([
                CodeField::make('feed_formulas', 'FF')->required()->maxLength(30)->visibleOn('create')->unique()
                    ->mutateStateForValidationUsing(fn (?string $state) => $state === null ? null : strtoupper(trim($state)))
                    ->helperText('Filled in for you. Stays the same across versions; stored in upper case.'),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('feed_type_id')->label('Feed type')->required()->searchable()
                    ->options(fn () => FeedType::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                TextInput::make('process_loss_percent')->label('Process loss (%)')->numeric()->minValue(0)->maxValue(50)->step(0.01)->default(0)->required()
                    ->helperText('Expected loss between mixing and bagging; the materials needed are increased to allow for it.'),
            ]),
            Section::make('Nutritional specification')->columns(4)->columnSpanFull()->description('Targets set by the nutritionist; all optional.')
                ->schema(collect(FeedFormula::NUTRITION)->map(fn (string $label, string $field) => TextInput::make($field)->label($label)->numeric()->minValue(0)->step(0.01))->values()->all()),
            Section::make('Ingredients and notes')->description('What goes into the mix, as a share of the whole.')->columnSpanFull()->schema([
                Textarea::make('notes')->columnSpanFull(),
                Repeater::make('items')->label('Ingredients')->hiddenLabel()->columnSpanFull()->minItems(1)->columns(3)->addActionLabel('Add ingredient')
                    ->schema([
                        Select::make('inventory_item_id')->label('Item')->required()->searchable()->distinct()
                            ->options(fn () => InventoryItem::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($i) => [$i->id => "{$i->code} - {$i->name}"])->all()),
                        TextInput::make('inclusion_percent')->label('Inclusion (%)')->numeric()->minValue(0.0001)->maxValue(100)->step(0.0001)->required(),
                        TextInput::make('notes')->maxLength(255),
                    ]),
                Text::make(fn ($get) => 'Ingredients add up to '.static::total($get('items') ?? '').'% (must be exactly 100% to activate).')->columnSpanFull(),
            ]),
        ]);
    }

    /** @param  array<int|string, array<string, mixed>>|string  $items */
    public static function total(array|string $items): string
    {
        return collect((array) $items)->reduce(fn (string $sum, $item) => is_numeric($item['inclusion_percent'] ?? null) ? bcadd($sum, (string) $item['inclusion_percent'], 4) : $sum, '0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('feedType'))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('version')->label('Version')->prefix('v'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('feedType.name')->label('Feed type'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        FormulaStatus::Active => 'success', FormulaStatus::Retired => 'gray', default => 'warning',
                    }),
                TextColumn::make('crude_protein_percent')->label('Protein %')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(FormulaStatus::cases()))->default(FormulaStatus::Active->value),
                SelectFilter::make('feed_type_id')->label('Feed type')->relationship('feedType', 'name'),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [IngredientsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedFormulas::route('/'),
            'create' => CreateFeedFormula::route('/create'),
            'view' => ViewFeedFormula::route('/{record}'),
            'edit' => EditFeedFormula::route('/{record}/edit'),
        ];
    }
}
