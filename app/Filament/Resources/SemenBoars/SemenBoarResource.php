<?php

namespace App\Filament\Resources\SemenBoars;

use App\Domain\Animal\Models\Animal;
use App\Domain\Semen\Models\SemenBoar;
use App\Enums\AnimalStatus;
use App\Enums\SemenBoarStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\SemenBoars\Pages\CreateSemenBoar;
use App\Filament\Resources\SemenBoars\Pages\EditSemenBoar;
use App\Filament\Resources\SemenBoars\Pages\ListSemenBoars;
use BackedEnum;
use Filament\Actions\EditAction;
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

/** The boars semen is collected from, with their rest period and weekly target. */
class SemenBoarResource extends Resource
{
    protected static ?string $model = SemenBoar::class;

    protected static ?string $navigationLabel = 'Boars';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Semen';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Boar')->description('Which boar and whether it is being collected.')->columnSpanFull()->columns(2)->schema([
                Select::make('animal_id')->label('Boar')->required()->searchable()->visibleOn('create')
                    ->options(fn () => Animal::whereHas('category', fn ($q) => $q->where('code', 'boar'))->where('status', AnimalStatus::Active)
                        ->whereNotIn('id', SemenBoar::pluck('animal_id'))->orderBy('animal_number')->pluck('animal_number', 'id'))
                    ->helperText('Active boars not yet in the programme.'),
                Select::make('status')->options(AnimalResource::enumOptions(SemenBoarStatus::cases()))->required()->default(SemenBoarStatus::Active->value)
                    ->helperText('A resting or retired boar is not collected from.'),
            ]),
            Section::make('Collection plan')->description('How often to collect and how much to aim for.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('min_interval_days')->label('Days between collections')->numeric()->integer()->minValue(0)->maxValue(365)
                    ->helperText('Leave empty to use the farm setting.'),
                TextInput::make('target_doses_per_week')->label('Target doses per week')->numeric()->integer()->minValue(0)->maxValue(100000)
                    ->helperText('Leave empty to use the farm target.'),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('animal.breed')->withMax('collections', 'collected_at')->withCount('collections'))
            ->columns([
                TextColumn::make('animal.animal_number')->label('Boar')->searchable()->sortable(),
                TextColumn::make('animal.breed.name')->label('Breed')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        SemenBoarStatus::Active => 'success', SemenBoarStatus::Resting => 'warning', default => 'gray',
                    }),
                TextColumn::make('collections_count')->label('Collections'),
                TextColumn::make('collections_max_collected_at')->label('Last collected')->date()->placeholder('Never'),
                TextColumn::make('min_interval_days')->label('Rest (days)')->placeholder('Farm setting'),
                TextColumn::make('target_doses_per_week')->label('Weekly target')->placeholder('Farm target'),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(SemenBoarStatus::cases()))])
            ->recordActions([EditAction::make()])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSemenBoars::route('/'),
            'create' => CreateSemenBoar::route('/create'),
            'edit' => EditSemenBoar::route('/{record}/edit'),
        ];
    }
}
