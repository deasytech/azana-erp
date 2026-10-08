<?php

namespace App\Filament\Resources\Customers;

use App\Domain\Farm\Models\LookupValue;
use App\Domain\Sales\Models\Customer;
use App\Enums\CreditStatus;
use App\Enums\LookupCategory;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PurchasesRelationManager;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Who the farm sells to, with their credit terms. Credit itself is approved from the customer's page. */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'phone'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer')->description('Who they are.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('customer_type_id')->label('Customer type')->required()->searchable()
                    ->options(fn () => LookupValue::inCategory(LookupCategory::CustomerType)->pluck('name', 'id')->all()),
            ]),
            Section::make('Contact')->description('How to reach them.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('contact_name')->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(40),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('tax_number')->maxLength(60),
                Textarea::make('address'),
                Textarea::make('notes'),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('type'))
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type.name')->label('Type'),
                TextColumn::make('phone')->placeholder('-'),
                TextColumn::make('credit_status')->label('Credit')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        CreditStatus::Approved => 'success', CreditStatus::OnHold => 'warning', CreditStatus::Blocked => 'danger', default => 'gray',
                    }),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('credit_status')->label('Credit')->options(AnimalResource::enumOptions(CreditStatus::cases())),
                SelectFilter::make('customer_type_id')->label('Type')->relationship('type', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class, InvoicesRelationManager::class, PurchasesRelationManager::class, PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
