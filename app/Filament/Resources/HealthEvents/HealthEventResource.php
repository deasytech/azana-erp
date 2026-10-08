<?php

namespace App\Filament\Resources\HealthEvents;

use App\Domain\Health\Actions\ResolveHealthEvent;
use App\Domain\Health\Models\HealthEvent;
use App\Filament\Resources\HealthEvents\Pages\CreateHealthEvent;
use App\Filament\Resources\HealthEvents\Pages\ListHealthEvents;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\DomainAction;
use App\Filament\Support\FormSections;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class HealthEventResource extends Resource
{
    protected static ?string $model = HealthEvent::class;

    protected static ?string $navigationLabel = 'Health cases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSections::make('Animal', 'Which animal this is about.', [AnimalPicker::any()->required()], Heroicon::OutlinedTag),
            FormSections::make('Case', 'What was seen and how it was assessed.', HealthForms::caseReport(), Heroicon::OutlinedHeart),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'disease']))
            ->columns([
                TextColumn::make('observed_on')->date()->sortable(),
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('kind')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('severity')->formatStateUsing(fn ($state) => $state->label())->badge()->color(fn ($state) => $state->value === 'severe' ? 'danger' : 'gray'),
                TextColumn::make('disease.name')->label('Disease')->placeholder('-'),
                TextColumn::make('status')->formatStateUsing(fn ($state) => $state->label())->badge()->color(fn ($state) => $state->value === 'open' ? 'warning' : 'success'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'resolved' => 'Resolved'])->default('open'),
            ])
            ->recordActions([
                Action::make('resolve')->label('Resolve')->icon('heroicon-o-check-circle')
                    ->visible(fn (HealthEvent $record) => $record->isOpen() && auth()->user()->can('create', HealthEvent::class))
                    ->schema([DatePicker::make('resolved_on')->default(now())->maxDate(now())->required(), Textarea::make('notes')])
                    ->action(fn (HealthEvent $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(ResolveHealthEvent::class)($record, Carbon::parse($data['resolved_on']), $data['notes'] ?? null), $action, 'Health case resolved')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthEvents::route('/'),
            'create' => CreateHealthEvent::route('/create'),
        ];
    }
}
