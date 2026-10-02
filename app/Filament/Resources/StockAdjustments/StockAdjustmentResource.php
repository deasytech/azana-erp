<?php

namespace App\Filament\Resources\StockAdjustments;

use App\Domain\Inventory\Actions\ApproveStockAdjustment;
use App\Domain\Inventory\Actions\RejectStockAdjustment;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Enums\ApprovalStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Filament\Resources\StockAdjustments\Pages\ListStockAdjustments;
use App\Filament\Support\DomainAction;
use App\Filament\Support\StockForms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Corrections to stock that move nothing until someone with approval rights approves them. */
class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationLabel = 'Adjustments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            StockForms::item(),
            StockForms::store(),
            StockForms::batch(),
            TextInput::make('quantity')->numeric()->step(0.001)->required()->rule('not_in:0')
                ->helperText('Positive adds stock, negative removes it.'),
            Textarea::make('reason')->required()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item.unit', 'location', 'batch', 'requestedBy', 'decidedBy']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('item.name')->label('Item')->searchable(),
                TextColumn::make('location.name')->label('Store'),
                TextColumn::make('batch.batch_number')->label('Batch')->placeholder('-'),
                TextColumn::make('quantity')->numeric(decimalPlaces: 3)->suffix(fn (StockAdjustment $r) => ' '.$r->item->unit->code),
                TextColumn::make('reason')->limit(50),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        ApprovalStatus::Approved => 'success', ApprovalStatus::Rejected => 'danger', default => 'warning',
                    }),
                TextColumn::make('requestedBy.name')->label('Requested by')->placeholder('-'),
                TextColumn::make('decidedBy.name')->label('Decided by')->placeholder('-')->description(fn (StockAdjustment $r) => $r->decision_notes),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(ApprovalStatus::cases()))->default(ApprovalStatus::Pending->value)])
            ->recordActions([
                Action::make('approve')->label('Approve')->color('success')->requiresConfirmation()
                    ->visible(fn (StockAdjustment $r) => $r->status === ApprovalStatus::Pending && auth()->user()->can('approve', $r))
                    ->schema([Textarea::make('notes')])
                    ->action(fn (StockAdjustment $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(ApproveStockAdjustment::class)($record, auth()->user(), $data['notes'] ?? null), $action, 'Adjustment approved')),
                Action::make('reject')->label('Reject')->color('danger')
                    ->visible(fn (StockAdjustment $r) => $r->status === ApprovalStatus::Pending && auth()->user()->can('approve', $r))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (StockAdjustment $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(RejectStockAdjustment::class)($record, auth()->user(), $data['reason']), $action, 'Adjustment rejected')),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListStockAdjustments::route('/'), 'create' => CreateStockAdjustment::route('/create')];
    }
}
