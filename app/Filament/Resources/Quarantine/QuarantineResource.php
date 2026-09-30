<?php

namespace App\Filament\Resources\Quarantine;

use App\Domain\Health\Actions\ReleaseQuarantine;
use App\Domain\Health\Models\QuarantineRecord;
use App\Filament\Resources\Quarantine\Pages\CreateQuarantine;
use App\Filament\Resources\Quarantine\Pages\ListQuarantines;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\DomainAction;
use App\Filament\Support\HealthForms;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class QuarantineResource extends Resource
{
    protected static ?string $model = QuarantineRecord::class;

    protected static ?string $navigationLabel = 'Quarantine & isolation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AnimalPicker::any()->required(),
            ...HealthForms::quarantine(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal']))
            ->columns([
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('type')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('started_on')->date()->sortable(),
                TextColumn::make('reason')->limit(50),
                TextColumn::make('released_on')->date()->placeholder('Still in'),
            ])
            ->filters([
                Filter::make('current')->label('Currently in')->default()->query(fn (Builder $q) => $q->whereNull('released_on')),
            ])
            ->recordActions([
                Action::make('release')->label('Release')->icon('heroicon-o-lock-open')
                    ->visible(fn (QuarantineRecord $record) => $record->isOpen() && auth()->user()->can('create', QuarantineRecord::class))
                    ->schema([DatePicker::make('released_on')->default(now())->maxDate(now())->required(), Textarea::make('notes')])
                    ->action(fn (QuarantineRecord $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(ReleaseQuarantine::class)($record, Carbon::parse($data['released_on']), $data['notes'] ?? null), $action, 'Animal released')),
            ])
            ->defaultSort('started_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuarantines::route('/'),
            'create' => CreateQuarantine::route('/create'),
        ];
    }
}
