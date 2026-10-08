<?php

namespace App\Filament\Resources\SemenBatches;

use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Semen\Models\SemenBoar;
use App\Enums\SemenBatchStatus;
use App\Enums\SemenBoarStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\SemenBatches\Pages\CreateSemenBatch;
use App\Filament\Resources\SemenBatches\Pages\ListSemenBatches;
use App\Filament\Resources\SemenBatches\Pages\ViewSemenBatch;
use App\Filament\Resources\SemenBatches\RelationManagers\QcRecordsRelationManager;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Every collection and the batch it became: QC, release, stock and what happened after. */
class SemenBatchResource extends Resource
{
    protected static ?string $model = SemenBatch::class;

    protected static ?string $navigationLabel = 'Batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Semen';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number'];
    }

    /** The "collection" form: creating a batch means recording the ejaculate it came from. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Collection')->description('Which boar and when.')->columnSpanFull()->columns(2)->schema([
                Select::make('animal_id')->label('Boar')->required()->searchable()
                    ->options(fn () => SemenBoar::with('animal')->where('status', SemenBoarStatus::Active)->get()->mapWithKeys(fn ($b) => [$b->animal_id => $b->animal->animal_number])->sort()->all()),
                DateTimePicker::make('collected_at')->default(now())->maxDate(now())->seconds(false)->required(),
            ]),
            Section::make('Ejaculate')->description('What was measured at collection.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('volume_ml')->label('Volume (ml)')->numeric()->minValue(0.1)->maxValue(1000)->step(0.1)->required(),
                TextInput::make('ph')->label('pH')->numeric()->minValue(0)->maxValue(14)->step(0.1),
                TextInput::make('colour')->maxLength(30),
                TextInput::make('odour')->maxLength(30),
                TextInput::make('technician_name')->maxLength(255),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['boar', 'breed', 'latestQc', 'inventoryBatch']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('boar.animal_number')->label('Boar')->searchable(),
                TextColumn::make('breed.name')->label('Breed')->placeholder('-'),
                TextColumn::make('collected_on')->date()->sortable(),
                TextColumn::make('expiry_date')->date()->sortable()->color(fn (SemenBatch $r) => $r->isExpired() ? 'danger' : null),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        SemenBatchStatus::Released, SemenBatchStatus::Passed => 'success',
                        SemenBatchStatus::PendingQc => 'warning',
                        SemenBatchStatus::Failed, SemenBatchStatus::Quarantined => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('doses_produced')->label('Doses')->placeholder('-'),
                IconColumn::make('sellable')->label('Sellable')->boolean()->getStateUsing(fn (SemenBatch $r) => $r->isSellable()),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(SemenBatchStatus::cases())),
                SelectFilter::make('breed_id')->label('Breed')->relationship('breed', 'name'),
                SelectFilter::make('animal_id')->label('Boar')->relationship('boar', 'animal_number')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [QcRecordsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSemenBatches::route('/'),
            'create' => CreateSemenBatch::route('/create'),
            'view' => ViewSemenBatch::route('/{record}'),
        ];
    }
}
