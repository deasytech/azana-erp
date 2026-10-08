<?php

namespace App\Filament\Resources\HeatEvents;

use App\Domain\Breeding\Models\HeatEvent;
use App\Filament\Resources\HeatEvents\Pages\CreateHeatEvent;
use App\Filament\Resources\HeatEvents\Pages\ListHeatEvents;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\DateRangeFilter;
use App\Filament\Support\FormSections;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class HeatEventResource extends Resource
{
    protected static ?string $model = HeatEvent::class;

    protected static ?string $navigationLabel = 'Heat detection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Breeding';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSections::make('Heat event', 'Which sow came on heat, and when it was seen.', [
                AnimalPicker::sow()->required(),
                DatePicker::make('detected_on')->required()->default(now())->maxDate(now()),
                Textarea::make('notes'),
            ], Heroicon::OutlinedHeart),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('sow'))
            ->columns([
                TextColumn::make('detected_on')->date()->sortable(),
                TextColumn::make('sow.animal_number')->label('Sow')->searchable(),
                TextColumn::make('notes')->limit(60)->placeholder('-'),
            ])
            ->filters([DateRangeFilter::make('detected_on', 'Detected')])
            ->defaultSort('detected_on', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListHeatEvents::route('/'), 'create' => CreateHeatEvent::route('/create')];
    }
}
