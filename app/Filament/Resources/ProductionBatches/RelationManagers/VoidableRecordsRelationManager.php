<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Filament\Concerns\NotifiesDomainErrors;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** History tab for records that are voided rather than edited or deleted (weigh-ins, feed, costs). */
abstract class VoidableRecordsRelationManager extends RelationManager
{
    use NotifiesDomainErrors;

    /** @return list<Column> */
    abstract protected function recordColumns(): array;

    abstract protected function voidRecord(Model $record, string $reason): void;

    abstract protected function dateColumn(): string;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ...$this->recordColumns(),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn (Model $record) => $record->isVoided()),
            ])
            ->recordActions([
                Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
                    ->visible(fn (Model $record) => ! $record->isVoided() && auth()->user()->can('update', $this->getOwnerRecord()))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(function (Model $record, array $data, Action $action) {
                        $this->attempt(fn () => $this->voidRecord($record, $data['reason']), $action);
                        Notification::make()->title('Record voided')->success()->send();
                    }),
            ])
            ->defaultSort($this->dateColumn(), 'desc')
            ->paginated([10, 25]);
    }
}
