<?php

namespace App\Filament\Resources\LabResults;

use App\Domain\Health\Models\LaboratoryResult;
use App\Filament\Resources\LabResults\Pages\CreateLabResult;
use App\Filament\Resources\LabResults\Pages\ListLabResults;
use App\Filament\Support\AnimalPicker;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LabResultResource extends Resource
{
    protected static ?string $model = LaboratoryResult::class;

    protected static ?string $navigationLabel = 'Lab results';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 36;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sample')->description('What was sampled, from whom and when.')->columnSpanFull()->columns(2)->schema([
                AnimalPicker::any()->helperText('Leave empty for a herd or pen sample.'),
                TextInput::make('sample_type')->required()->maxLength(60)->placeholder('Blood, faeces, swab...'),
                TextInput::make('test_name')->required()->maxLength(255),
                DatePicker::make('sampled_on')->default(now())->maxDate(now())->required(),
                DatePicker::make('resulted_on')->maxDate(now()),
            ]),
            Section::make('Result')->description('What the laboratory found.')->columnSpanFull()->columns(2)->schema([
                Textarea::make('result'),
                Toggle::make('is_abnormal')->label('Abnormal result'),
                TextInput::make('lab_name')->maxLength(255),
                Textarea::make('notes'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal']))
            ->columns([
                TextColumn::make('sampled_on')->date()->sortable(),
                TextColumn::make('animal.animal_number')->label('Animal')->placeholder('Herd / batch'),
                TextColumn::make('test_name')->label('Test')->searchable(),
                TextColumn::make('result')->limit(40)->placeholder('Awaiting'),
                IconColumn::make('is_abnormal')->label('Abnormal')->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_abnormal')->label('Result')->options([1 => 'Abnormal', 0 => 'Normal']),
            ])
            ->recordActions([

            ])
            ->defaultSort('sampled_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabResults::route('/'),
            'create' => CreateLabResult::route('/create'),
        ];
    }
}
