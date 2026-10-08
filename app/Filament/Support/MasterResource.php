<?php

namespace App\Filament\Support;

use App\Enums\LookupCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Shared shape of the code + name + active master-data resources: subclasses only supply
 * their own fields, columns and filters (and pages), so the boilerplate lives in one place.
 */
abstract class MasterResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name';

    protected static int $codeLength = 30;

    /** The letters generated codes start with (e.g. PEN for PEN-0001); null leaves the code to be typed, as for the chart of accounts. */
    protected static ?string $codePrefix = null;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    /** @return list<Component> fields between the code and the active toggle */
    abstract protected static function fields(): array;

    /** @return list<Column> columns between the code and the active flag */
    abstract protected static function columns(): array;

    /** @return list<BaseFilter> */
    protected static function filters(): array
    {
        return [];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(static::detailsHeading())->description(static::detailsDescription())->icon(Heroicon::OutlinedClipboardDocumentList)->columnSpanFull()->columns(2)->schema([
                static::codeField(),
                ...array_map(fn (Component $field): Component => $field instanceof Textarea ? $field->rows(3)->columnSpanFull() : $field, static::fields()),
            ]),
            Section::make('Status')->description('Inactive records stay on past entries but are no longer offered for new ones.')->icon(Heroicon::OutlinedPower)->columnSpanFull()->schema([
                static::activeToggle()->inline(false),
            ]),
        ]);
    }

    /** The heading of the form's main section; subclasses may name it after the record, e.g. "Building". */
    protected static function detailsHeading(): string
    {
        return static::getModelLabel() ? ucfirst(static::getModelLabel()) : 'Details';
    }

    protected static function detailsDescription(): string
    {
        return 'The identifier and the facts that describe this record.';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                ...static::columns(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                ...static::filters(),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
    }

    protected static function codeField(): TextInput
    {
        $field = static::$codePrefix ? CodeField::make((new (static::getModel()))->getTable(), static::$codePrefix) : TextInput::make('code');

        return $field
            ->required()
            ->maxLength(static::$codeLength)
            ->mutateStateForValidationUsing(fn (?string $state) => static::normalizeCode($state))
            ->unique(ignoreRecord: true)
            ->helperText(static::$codePrefix ? 'Filled in for you; use the sparkles icon for another, or type your own. Must be unique; stored in upper case.' : 'Unique business identifier; stored in upper case.');
    }

    /** Mirrors HasBusinessCode so uniqueness is checked against what will actually be stored. */
    protected static function normalizeCode(?string $code): ?string
    {
        return $code === null ? null : strtoupper(trim($code));
    }

    protected static function nameField(bool $required = true): TextInput
    {
        return TextInput::make('name')->required($required)->maxLength(255);
    }

    protected static function activeToggle(): Toggle
    {
        return Toggle::make('is_active')->label('Active')->default(true);
    }

    /** Required select bound to a lookup_values category. */
    protected static function lookupSelect(string $field, string $relation, LookupCategory $category, string $label): Select
    {
        return LookupSelect::make($field, $relation, $category, $label)->required();
    }
}
