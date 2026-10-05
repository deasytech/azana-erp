<?php

namespace App\Filament\Resources\SupplierPayments;

use App\Domain\Procurement\Models\SupplierPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\SupplierPayments\Pages\ListSupplierPayments;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ProcurementActions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Payments to suppliers; large ones wait here for approval. Payments are made from an invoice. */
class SupplierPaymentResource extends Resource
{
    protected static ?string $model = SupplierPayment::class;

    protected static ?string $navigationLabel = 'Supplier payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 45;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['invoice.supplier', 'createdBy', 'decidedBy']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('invoice.supplier.name')->label('Supplier'),
                TextColumn::make('invoice.number')->label('Invoice'),
                TextColumn::make('paid_on')->date()->sortable(),
                MoneyColumn::make('amount_minor', 'Amount'),
                TextColumn::make('method')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('reference')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        PaymentStatus::Paid => 'success', PaymentStatus::PendingApproval => 'warning', default => 'gray',
                    }),
                TextColumn::make('createdBy.name')->label('Entered by')->placeholder('-'),
                TextColumn::make('decidedBy.name')->label('Decided by')->placeholder('-')->description(fn (SupplierPayment $r) => $r->decision_notes ?? $r->void_reason),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(PaymentStatus::cases())),
                SelectFilter::make('method')->options(AnimalResource::enumOptions(PaymentMethod::cases())),
            ])
            ->recordActions(ProcurementActions::paymentDecisions())
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListSupplierPayments::route('/')];
    }
}
