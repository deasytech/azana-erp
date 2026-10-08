<?php

namespace App\Filament\Resources\VeterinaryVisits;

use App\Domain\Health\Models\VeterinaryVisit;
use App\Filament\Resources\VeterinaryVisits\Pages\CreateVeterinaryVisit;
use App\Filament\Resources\VeterinaryVisits\Pages\ListVeterinaryVisits;
use App\Filament\Support\MoneyInput;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VeterinaryVisitResource extends Resource
{
    protected static ?string $model = VeterinaryVisit::class;

    protected static ?string $navigationLabel = 'Vet visits';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Visit')->description('When the vet came and who they are.')->columns(2)->schema([
                DatePicker::make('visited_on')->default(now())->maxDate(now())->required(),
                Select::make('veterinarian_id')->label('Veterinarian (user)')->searchable()->options(fn () => User::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                TextInput::make('veterinarian_name')->label('Veterinarian (name)')->maxLength(255),
            ]),
            Section::make('Outcome')->description('What was found and what to do next.')->columns(2)->schema([
                TextInput::make('reason')->required()->maxLength(255),
                Textarea::make('findings'),
                Textarea::make('recommendations'),
                DatePicker::make('follow_up_on')->label('Follow-up on'),
                MoneyInput::make('cost_minor', 'Cost'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veterinarian']))
            ->columns([
                TextColumn::make('visited_on')->date()->sortable(),
                TextColumn::make('vet')->label('Veterinarian')->state(fn (VeterinaryVisit $r) => $r->veterinarian?->name ?? $r->veterinarian_name),
                TextColumn::make('reason')->searchable()->limit(50),
                TextColumn::make('follow_up_on')->label('Follow-up')->date()->placeholder('-'),
            ])
            ->filters([

            ])
            ->recordActions([

            ])
            ->defaultSort('visited_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVeterinaryVisits::route('/'),
            'create' => CreateVeterinaryVisit::route('/create'),
        ];
    }
}
