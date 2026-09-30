<?php

namespace App\Filament\Resources\WithdrawalPeriods;

use App\Domain\Health\Actions\ClearWithdrawal;
use App\Domain\Health\Models\WithdrawalPeriod;
use App\Filament\Resources\WithdrawalPeriods\Pages\ListWithdrawalPeriods;
use App\Filament\Support\DomainAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class WithdrawalPeriodResource extends Resource
{
    protected static ?string $model = WithdrawalPeriod::class;

    protected static ?string $navigationLabel = 'Withdrawal periods';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 15;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'medicine']))
            ->columns([
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('medicine.name')->label('Medicine'),
                TextColumn::make('starts_on')->date(),
                TextColumn::make('ends_on')->label('Safe from')->date()->sortable(),
                TextColumn::make('state')->label('State')->state(fn (WithdrawalPeriod $r) => $r->cleared_at ? 'Cleared early' : ($r->isActiveOn(now()) ? 'Active' : 'Ended'))->badge()->color(fn ($state) => $state === 'Active' ? 'danger' : 'gray'),
                TextColumn::make('clear_reason')->label('Cleared because')->placeholder('-')->toggleable(),
            ])
            ->filters([
                Filter::make('active')->label('Active only')->default()->query(fn (Builder $q) => $q->whereNull('cleared_at')->whereDate('ends_on', '>', now()->toDateString())),
            ])
            ->recordActions([
                Action::make('clear')->label('Clear early')->icon('heroicon-o-lock-open')->color('warning')->requiresConfirmation()
                    ->visible(fn (WithdrawalPeriod $record) => $record->isActiveOn(now()) && auth()->user()->can('approve', WithdrawalPeriod::class))
                    ->modalDescription('Only clear a withdrawal early on a veterinarian\'s advice (for example a negative residue test). The reason is kept in the audit log.')
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (WithdrawalPeriod $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(ClearWithdrawal::class)($record, $data['reason']), $action, 'Withdrawal cleared')),
            ])
            ->defaultSort('ends_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawalPeriods::route('/'),
        ];
    }
}
