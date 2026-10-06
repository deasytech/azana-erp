<?php

namespace App\Filament\Resources\Carcasses;

use App\Domain\Slaughter\Actions\AdjustCarcassWeight;
use App\Domain\Slaughter\Models\Carcass;
use App\Enums\CarcassStatus;
use App\Enums\PostMortemResult;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Carcasses\Pages\ListCarcasses;
use App\Filament\Support\DomainAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Every carcass, with its yield. Created by recording a slaughter; weights are corrected here with approval. */
class CarcassResource extends Resource
{
    protected static ?string $model = Carcass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['record.batch', 'record.animal', 'record.productionBatch', 'productionBatch']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('slaughtered_at')->label('Slaughtered')->dateTime()->sortable(),
                TextColumn::make('record.batch.number')->label('Day'),
                TextColumn::make('source')->label('Pig')->state(fn (Carcass $c) => $c->record->sourceLabel()),
                TextColumn::make('live_weight_kg')->label('Live (kg)'),
                TextColumn::make('hot_weight_kg')->label('Carcass (kg)'),
                TextColumn::make('dressing_percent')->label('Dressing')->suffix('%'),
                TextColumn::make('post_mortem')->label('Inspection')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        PostMortemResult::Passed => 'success', PostMortemResult::Partial => 'warning', default => 'danger',
                    }),
                TextColumn::make('condemned_kg')->label('Condemned (kg)'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        CarcassStatus::Hanging => 'warning', CarcassStatus::Processed => 'success', default => 'danger',
                    }),
                TextColumn::make('productionBatch.number')->label('Meat batch')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(CarcassStatus::cases())),
                SelectFilter::make('post_mortem')->label('Inspection')->options(AnimalResource::enumOptions(PostMortemResult::cases())),
            ])
            ->recordActions([
                Action::make('adjust')->label('Correct weight')->icon('heroicon-o-pencil-square')
                    ->visible(fn (Carcass $c) => $c->status === CarcassStatus::Hanging && auth()->user()->can('approve', $c))
                    ->fillForm(fn (Carcass $c) => ['hot_weight_kg' => (string) $c->hot_weight_kg])
                    ->schema([
                        TextInput::make('hot_weight_kg')->label('Corrected hot weight (kg)')->numeric()->minValue(0.01)->step(0.01)->required(),
                        Textarea::make('reason')->required()->helperText('A correction is kept with its reason and who approved it.'),
                    ])
                    ->action(fn (Carcass $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(AdjustCarcassWeight::class)($record, (string) $data['hot_weight_kg'], $data['reason'], auth()->user()), $action, 'Carcass weight corrected')),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCarcasses::route('/')];
    }
}
