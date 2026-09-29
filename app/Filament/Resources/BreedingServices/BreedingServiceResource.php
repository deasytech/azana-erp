<?php

namespace App\Filament\Resources\BreedingServices;

use App\Domain\Breeding\Models\BreedingService;
use App\Enums\ServiceMethod;
use App\Enums\ServiceOutcome;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\BreedingServices\Pages\CreateBreedingService;
use App\Filament\Resources\BreedingServices\Pages\ListBreedingServices;
use App\Filament\Resources\BreedingServices\Pages\ViewBreedingService;
use App\Filament\Support\AnimalPicker;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BreedingServiceResource extends Resource
{
    protected static ?string $model = BreedingService::class;

    protected static ?string $navigationLabel = 'Services';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Breeding';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            AnimalPicker::sow()->required(),
            DatePicker::make('serviced_on')->required()->default(now())->maxDate(now()),
            Select::make('method')->options(AnimalResource::enumOptions(ServiceMethod::cases()))->required()->default(ServiceMethod::Natural->value),
            AnimalPicker::boar()->helperText('Required for natural mating; optional for AI.'),
            TextInput::make('semen_source')->maxLength(255)->helperText('For AI: boar / supplier of the semen when no boar is chosen.'),
            Select::make('technician_id')->label('Technician (user)')->searchable()->options(fn () => User::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            TextInput::make('technician_name')->label('Technician (name)')->maxLength(255),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sow', 'boar']))
            ->columns([
                TextColumn::make('serviced_on')->date()->sortable(),
                TextColumn::make('sow.animal_number')->label('Sow')->searchable(),
                TextColumn::make('method')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('boar')->label('Boar / semen')->state(fn (BreedingService $r) => $r->boar?->animal_number ?? $r->semen_source),
                TextColumn::make('outcome')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        ServiceOutcome::Pregnant, ServiceOutcome::Farrowed => 'success',
                        ServiceOutcome::NotPregnant, ServiceOutcome::Aborted => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expected_farrowing_on')->label('Farrowing due')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('outcome')->options(AnimalResource::enumOptions(ServiceOutcome::cases())),
                SelectFilter::make('method')->options(AnimalResource::enumOptions(ServiceMethod::cases())),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('serviced_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBreedingServices::route('/'),
            'create' => CreateBreedingService::route('/create'),
            'view' => ViewBreedingService::route('/{record}'),
        ];
    }
}
