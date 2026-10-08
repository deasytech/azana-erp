<?php

namespace App\Filament\Resources\BiosecurityChecks;

use App\Domain\Biosecurity\Models\BiosecurityCheck;
use App\Domain\Biosecurity\Models\BiosecurityChecklistItem;
use App\Domain\Farm\Models\ProductionUnit;
use App\Filament\Resources\BiosecurityChecks\Pages\CreateBiosecurityCheck;
use App\Filament\Resources\BiosecurityChecks\Pages\ListBiosecurityChecks;
use App\Filament\Support\DateRangeFilter;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BiosecurityCheckResource extends Resource
{
    protected static ?string $model = BiosecurityCheck::class;

    protected static ?string $navigationLabel = 'Inspections';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Biosecurity';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Check')->description('When, and which unit it covers.')->columnSpanFull()->columns(2)->schema([
                DatePicker::make('checked_on')->default(now())->maxDate(now())->required(),
                Select::make('production_unit_id')->label('Production unit')->options(fn () => ProductionUnit::where('is_active', true)->orderBy('name')->pluck('name', 'id'))->helperText('Leave empty for a whole-farm check.'),
            ]),
            Section::make('Checklist')->description('Tick what passed; add a note for anything that did not.')->columnSpanFull()->schema([
                Repeater::make('results')->hiddenLabel()->addable(false)->deletable(false)->reorderable(false)->columns(3)->columnSpanFull()
                    ->default(fn () => BiosecurityChecklistItem::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                        ->map(fn ($i) => ['item_id' => $i->id, 'description' => $i->description, 'passed' => true, 'notes' => null])->all())
                    ->schema([
                        Hidden::make('item_id'),
                        TextInput::make('description')->disabled()->dehydrated(false)->columnSpan(2),
                        Toggle::make('passed')->label('Passed'),
                        TextInput::make('notes')->columnSpanFull(),
                    ]),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['productionUnit', 'performer']))
            ->columns([
                TextColumn::make('checked_on')->date()->sortable(),
                TextColumn::make('productionUnit.name')->label('Unit')->placeholder('Whole farm'),
                TextColumn::make('score')->label('Score')->state(fn (BiosecurityCheck $r) => $r->scorePercent().'% ('.$r->items_passed.'/'.$r->items_total.')'),
                TextColumn::make('performer.name')->label('By')->placeholder('-'),
            ])
            ->filters([DateRangeFilter::make('checked_on', 'Checked')])
            ->recordActions([

            ])
            ->defaultSort('checked_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiosecurityChecks::route('/'),
            'create' => CreateBiosecurityCheck::route('/create'),
        ];
    }
}
