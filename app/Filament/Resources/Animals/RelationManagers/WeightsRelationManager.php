<?php

namespace App\Filament\Resources\Animals\RelationManagers;

use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\VoidWeight;
use App\Domain\Animal\Models\WeightRecord;
use App\Filament\Concerns\NotifiesDomainErrors;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WeightsRelationManager extends RelationManager
{
    use NotifiesDomainErrors;

    protected static string $relationship = 'weights';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('weight_kg')->label('Weight (kg)')->required()->numeric()->minValue(0.01)->step(0.01),
            DateTimePicker::make('weighed_at')->default(now())->required()->seconds(false),
            Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('weighed_at')->dateTime()->label('When'),
                TextColumn::make('weight_kg')->label('kg')->description(fn (WeightRecord $r) => $r->isVoided() ? "Voided: {$r->void_reason}" : null),
                TextColumn::make('method'),
                TextColumn::make('user.name')->label('By')->placeholder('-'),
                IconColumn::make('voided_at')->label('Voided')->boolean()->getStateUsing(fn (WeightRecord $r) => $r->isVoided()),
            ])
            ->headerActions([
                CreateAction::make()->label('Record weight')->visible(fn () => $this->getOwnerRecord()->isActive())
                    ->using(fn (array $data, CreateAction $action) => $this->attempt(
                        fn () => app(RecordWeight::class)($this->getOwnerRecord(), (string) $data['weight_kg'], Carbon::parse($data['weighed_at']), 'scale', $data['notes'] ?? null),
                        $action,
                    )),
            ])
            ->recordActions([
                Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
                    ->visible(fn (WeightRecord $record) => ! $record->isVoided() && auth()->user()->can('update', $this->getOwnerRecord()))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (WeightRecord $record, array $data, Action $action) => $this->attempt(
                        fn () => app(VoidWeight::class)($record, $data['reason']), $action,
                    )),
            ])
            ->paginated([10, 25]);
    }
}
