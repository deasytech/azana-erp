<?php

namespace App\Filament\Resources\Animals\RelationManagers;

use App\Domain\Animal\Actions\AddAnimalIdentifier;
use App\Domain\Animal\Actions\RetireAnimalIdentifier;
use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalIdentifier;
use App\Enums\IdentifierType;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Animals\AnimalResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class IdentifiersRelationManager extends RelationManager
{
    use NotifiesDomainErrors;

    protected static string $relationship = 'identifiers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(AnimalResource::enumOptions(IdentifierType::cases()))->required(),
            TextInput::make('value')->required()->maxLength(100),
            DatePicker::make('issued_on')->maxDate(now()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('value')->searchable(),
                TextColumn::make('issued_on')->date(),
                TextColumn::make('retired_at')->dateTime()->placeholder('Active'),
                TextColumn::make('retired_reason')->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add identifier')->using(fn (array $data, CreateAction $action) => $this->attempt(
                    fn () => app(AddAnimalIdentifier::class)(
                        $this->getOwnerRecord(), IdentifierType::from($data['type']), $data['value'],
                        ! empty($data['issued_on']) ? Carbon::parse($data['issued_on']) : null,
                    ),
                    $action,
                )),
            ])
            ->recordActions([
                Action::make('retire')->label('Retire')->color('warning')->requiresConfirmation()
                    ->visible(fn (AnimalIdentifier $record) => ! $record->isRetired() && auth()->user()->can('update', $this->getOwnerRecord()))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (AnimalIdentifier $record, array $data, Action $action) => $this->attempt(
                        fn () => app(RetireAnimalIdentifier::class)($record, $data['reason']), $action,
                    )),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Animal && parent::canViewForRecord($ownerRecord, $pageClass);
    }
}
