<?php

namespace App\Filament\Resources\WebsiteEnquiries;

use App\Domain\Farm\Models\LookupValue;
use App\Domain\Website\Actions\ConvertEnquiryToCustomer;
use App\Domain\Website\Actions\UpdateEnquiryStatus;
use App\Domain\Website\Models\Enquiry;
use App\Enums\EnquiryKind;
use App\Enums\EnquiryStatus;
use App\Enums\LookupCategory;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\WebsiteEnquiries\Pages\ListWebsiteEnquiries;
use App\Filament\Resources\WebsiteEnquiries\Pages\ViewWebsiteEnquiry;
use App\Filament\Support\DomainAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** What visitors have asked for through the website. Staff answer them here; an order is raised through the sales screens. */
class WebsiteEnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static ?string $modelLabel = 'enquiry';

    protected static ?string $pluralModelLabel = 'enquiries';

    protected static ?string $navigationLabel = 'Enquiries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $new = auth()->user()?->can('viewAny', Enquiry::class) ? Enquiry::where('status', EnquiryStatus::New)->count() : 0;

        return $new > 0 ? (string) $new : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['listing', 'customer', 'handler']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
                TextColumn::make('name')->searchable()->description(fn (Enquiry $r) => $r->organisation),
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('email')->searchable()->placeholder('-')->description(fn (Enquiry $r) => $r->phone),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        EnquiryStatus::New => 'warning', EnquiryStatus::Converted => 'success', EnquiryStatus::Spam => 'danger', default => 'gray'
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(EnquiryStatus::cases())),
                SelectFilter::make('kind')->options(AnimalResource::enumOptions(EnquiryKind::cases())),
            ])
            ->recordActions([ViewAction::make(), ActionGroup::make(static::actions())])
            ->defaultSort('id', 'desc');
    }

    /** @return list<Action> */
    public static function actions(): array
    {
        $move = fn (string $name, string $label, EnquiryStatus $to, string $icon, ?array $from = null) => Action::make($name)->label($label)->icon($icon)
            ->visible(fn (Enquiry $r) => auth()->user()->can('update', $r) && $r->status !== EnquiryStatus::Converted && $r->status !== $to && ($from === null || in_array($r->status, $from, true)))
            ->schema([Textarea::make('note')->label('Note')->rows(3)])
            ->action(fn (Enquiry $record, array $data, Action $action) => DomainAction::run(fn () => app(UpdateEnquiryStatus::class)($record, $to, $data['note'] ?? null), $action, "Marked as {$to->value}"));

        return [
            $move('contacted', 'Mark contacted', EnquiryStatus::Contacted, 'heroicon-o-phone', [EnquiryStatus::New]),
            $move('quoted', 'Mark quoted', EnquiryStatus::Quoted, 'heroicon-o-document-text', [EnquiryStatus::New, EnquiryStatus::Contacted]),
            $move('close', 'Close', EnquiryStatus::Closed, 'heroicon-o-check', [EnquiryStatus::New, EnquiryStatus::Contacted, EnquiryStatus::Quoted]),
            $move('spam', 'Mark as spam', EnquiryStatus::Spam, 'heroicon-o-no-symbol', [EnquiryStatus::New, EnquiryStatus::Contacted, EnquiryStatus::Quoted]),
            $move('reopen', 'Reopen', EnquiryStatus::New, 'heroicon-o-arrow-path', [EnquiryStatus::Closed, EnquiryStatus::Spam]),
            static::makeCustomer(),
        ];
    }

    public static function makeCustomer(): Action
    {
        return Action::make('makeCustomer')->label('Make customer')->icon('heroicon-o-user-plus')
            ->visible(fn (Enquiry $r) => $r->customer_id === null && $r->status !== EnquiryStatus::Spam && auth()->user()->can('update', $r) && auth()->user()->can('sales.create'))
            ->schema([Select::make('customer_type_id')->label('Customer type')->required()->searchable()
                ->options(fn () => LookupValue::inCategory(LookupCategory::CustomerType)->pluck('name', 'id')->all())
                ->helperText('An existing customer with the same e-mail or phone is reused. Credit is set up separately.')])
            ->action(fn (Enquiry $record, array $data, Action $action) => DomainAction::run(function () use ($record, $data) {
                app(ConvertEnquiryToCustomer::class)($record, (int) $data['customer_type_id']);
            }, $action, 'Customer ready - raise the order from their page'))
            ->successRedirectUrl(fn (Enquiry $record) => $record->customer_id ? CustomerResource::getUrl('view', ['record' => $record->customer_id]) : null);
    }

    public static function getPages(): array
    {
        return ['index' => ListWebsiteEnquiries::route('/'), 'view' => ViewWebsiteEnquiry::route('/{record}')];
    }
}
