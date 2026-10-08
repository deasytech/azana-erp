<?php

namespace App\Filament\Resources\KpiTargets;

use App\Domain\Reporting\KpiRegistry;
use App\Domain\Reporting\Models\KpiTarget;
use App\Filament\Resources\KpiTargets\Pages\CreateKpiTarget;
use App\Filament\Resources\KpiTargets\Pages\EditKpiTarget;
use App\Filament\Resources\KpiTargets\Pages\ListKpiTargets;
use App\Filament\Support\FormSections;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Management's targets for the indicators, for a whole year or a single month. Money targets are in minor units (kobo). */
class KpiTargetResource extends Resource
{
    protected static ?string $model = KpiTarget::class;

    protected static ?string $navigationLabel = 'KPI targets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSections::make('Indicator', 'What is measured.', [
                Select::make('kpi_key')->label('Indicator')->options(KpiRegistry::options())->searchable()->required()->columnSpanFull(),
            ], Heroicon::OutlinedChartBar),
            FormSections::make('Target', 'When it applies and the figure to reach.', [
                TextInput::make('year')->numeric()->integer()->minValue(2000)->maxValue(2100)->default((int) now()->year)->required(),
                Select::make('month')->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->all())->placeholder('Whole year')->helperText('Leave empty for a target that applies to every month of the year.'),
                TextInput::make('target_value')->label('Target')->required()->rule('regex:/^\d{1,14}(\.\d{1,4})?$/')->helperText('A number. Money is in minor units, e.g. 150000000 for 1,500,000.00.'),
                TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            ], Heroicon::OutlinedFlag),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kpi_key')->label('Indicator')->formatStateUsing(fn ($state) => KpiRegistry::all()[$state]['label'] ?? $state)->searchable()->sortable(),
                TextColumn::make('year')->sortable(),
                TextColumn::make('month')->formatStateUsing(fn ($state) => $state ? date('F', mktime(0, 0, 0, (int) $state, 1)) : 'Whole year'),
                TextColumn::make('target_value')->label('Target')->formatStateUsing(fn ($state, KpiTarget $r) => KpiRegistry::format(KpiRegistry::all()[$r->kpi_key]['unit'] ?? 'count', rtrim(rtrim((string) $state, '0'), '.'))),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('year', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListKpiTargets::route('/'), 'create' => CreateKpiTarget::route('/create'), 'edit' => EditKpiTarget::route('/{record}/edit')];
    }
}
