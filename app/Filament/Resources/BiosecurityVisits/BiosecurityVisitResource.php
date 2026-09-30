<?php

namespace App\Filament\Resources\BiosecurityVisits;

use App\Domain\Biosecurity\Actions\RecordVisitorDeparture;
use App\Domain\Biosecurity\Models\BiosecurityVisit;
use App\Filament\Resources\BiosecurityVisits\Pages\CreateBiosecurityVisit;
use App\Filament\Resources\BiosecurityVisits\Pages\ListBiosecurityVisits;
use App\Filament\Support\DomainAction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BiosecurityVisitResource extends Resource
{
    protected static ?string $model = BiosecurityVisit::class;

    protected static ?string $navigationLabel = 'Visitor log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Biosecurity';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('visitor_name')->required()->maxLength(255),
            TextInput::make('organisation')->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(40),
            TextInput::make('vehicle_registration')->maxLength(30),
            TextInput::make('purpose')->required()->maxLength(255),
            DateTimePicker::make('arrived_at')->default(now())->maxDate(now())->required()->seconds(false),
            TextInput::make('last_pig_contact_hours')->label('Hours since last contact with pigs')->numeric()->integer()->minValue(0),
            Toggle::make('health_declaration')->label('Visitor declares no symptoms of illness')
                ->helperText('A visitor who has not declared, or has been near pigs too recently, can only be signed in by a user with approval rights.'),
            Textarea::make('areas_visited'),
            Select::make('host_id')->label('Host (user)')->searchable()->options(fn () => User::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            TextInput::make('host_name')->label('Host (name)')->maxLength(255),
            Textarea::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['approver']))
            ->columns([
                TextColumn::make('arrived_at')->dateTime()->sortable(),
                TextColumn::make('visitor_name')->label('Visitor')->searchable(),
                TextColumn::make('organisation')->placeholder('-'),
                TextColumn::make('purpose')->limit(40),
                TextColumn::make('last_pig_contact_hours')->label('Pig-free (h)')->placeholder('?'),
                IconColumn::make('health_declaration')->label('Declared healthy')->boolean(),
                TextColumn::make('approver.name')->label('Approved by')->placeholder('-'),
                TextColumn::make('departed_at')->dateTime()->placeholder('On site'),
            ])
            ->filters([
                Filter::make('on_site')->label('On site now')->query(fn (Builder $q) => $q->whereNull('departed_at')),
            ])
            ->recordActions([
                Action::make('signout')->label('Sign out')->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->visible(fn (BiosecurityVisit $record) => $record->departed_at === null && auth()->user()->can('create', BiosecurityVisit::class))
                    ->action(fn (BiosecurityVisit $record, Action $action) => DomainAction::run(
                        fn () => app(RecordVisitorDeparture::class)($record), $action, 'Visitor signed out')),
            ])
            ->defaultSort('arrived_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiosecurityVisits::route('/'),
            'create' => CreateBiosecurityVisit::route('/create'),
        ];
    }
}
