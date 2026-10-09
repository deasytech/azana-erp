<?php

namespace App\Filament\Resources\DataImports;

use App\Domain\Import\Actions\CommitImport;
use App\Domain\Import\Actions\ImportRegistry;
use App\Domain\Import\Models\DataImport;
use App\Domain\Import\Support\ImportFile;
use App\Filament\Resources\DataImports\Pages\CreateDataImport;
use App\Filament\Resources\DataImports\Pages\ListDataImports;
use App\Filament\Resources\DataImports\Pages\ViewDataImport;
use App\Filament\Resources\DataImports\RelationManagers\RowsRelationManager;
use App\Filament\Support\DomainAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Loading the farm's existing data: upload a file, see every row's verdict, then commit when all rows pass. Nothing is saved by
 * the check, and a commit saves everything or nothing.
 */
class DataImportResource extends Resource
{
    protected static ?string $model = DataImport::class;

    protected static ?string $modelLabel = 'import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'original_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Upload a file')
                ->description('The file is checked row by row first. Nothing is saved until you commit a file in which every row passed.')
                ->icon(Heroicon::OutlinedArrowUpTray)->columnSpanFull()
                ->schema([
                    Select::make('type')->label('What are you importing?')->required()->live()
                        ->options(fn () => collect(app(ImportRegistry::class)->allowedFor(auth()->user()))->map->label()->all())
                        ->helperText(fn (?string $state) => $state ? app(ImportRegistry::class)->get($state)->description() : 'Download the template for the kind of data first, fill it in, then upload it here.'),
                    FileUpload::make('file')->label('CSV or Excel file')->required()->maxSize(10240)->storeFiles(true)
                        ->disk('local')->directory('import-uploads')->visibility('private')
                        ->helperText('.csv or .xlsx, up to '.ImportFile::MAX_ROWS.' rows. The first row holds the column headings from the template.'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('type')->label('Kind')->formatStateUsing(fn (string $state) => app(ImportRegistry::class)->get($state)->label()),
            TextEntry::make('original_name')->label('File'),
            TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                DataImport::COMMITTED => 'success', DataImport::CHECKED => 'warning', default => 'gray'
            })
                ->formatStateUsing(fn (DataImport $r) => match (true) {
                    $r->status === DataImport::COMMITTED => 'Imported',
                    $r->status === DataImport::COMMITTING => 'Importing...',
                    $r->error_rows > 0 => 'Has errors - fix and upload again',
                    default => 'Checked - ready to import',
                }),
            TextEntry::make('total_rows')->label('Rows'),
            TextEntry::make('error_rows')->label('Rows with problems')->color(fn (int $state) => $state > 0 ? 'danger' : 'success'),
            TextEntry::make('creator.name')->label('Uploaded by')->placeholder('-'),
            TextEntry::make('created_at')->label('Uploaded')->dateTime(),
            TextEntry::make('committer.name')->label('Imported by')->placeholder('-'),
            TextEntry::make('committed_at')->label('Imported')->dateTime()->placeholder('-'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['creator']))
            ->columns([
                TextColumn::make('created_at')->label('Uploaded')->dateTime()->sortable(),
                TextColumn::make('type')->label('Kind')->formatStateUsing(fn (string $state) => app(ImportRegistry::class)->get($state)->label()),
                TextColumn::make('original_name')->label('File')->searchable()->limit(40),
                TextColumn::make('total_rows')->label('Rows')->numeric(),
                TextColumn::make('error_rows')->label('Problems')->numeric()->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    DataImport::COMMITTED => 'success', DataImport::CHECKED => 'warning', default => 'gray'
                })
                    ->formatStateUsing(fn (string $state) => $state === DataImport::COMMITTED ? 'Imported' : ($state === DataImport::CHECKED ? 'Not imported' : 'Importing...')),
                TextColumn::make('creator.name')->label('By')->placeholder('-'),
            ])
            ->defaultSort('id', 'desc');
    }

    /** The "Commit" button, shared by the list row and the import's page. */
    public static function commitAction(): Action
    {
        return Action::make('commit')->label('Import this data')->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn (DataImport $r) => $r->isReady() && auth()->user()->can('commit', $r))
            ->requiresConfirmation()
            ->modalHeading('Import this data?')
            ->modalDescription(fn (DataImport $r) => "All {$r->total_rows} rows passed. They will be saved now. This cannot be undone as one step: corrections are made record by record afterwards.")
            ->action(fn (DataImport $record, Action $action) => DomainAction::run(fn () => app(CommitImport::class)($record, auth()->user()), $action, 'Imported'));
    }

    public static function errorsAction(): Action
    {
        return Action::make('errors')->label('Download problems')->icon('heroicon-o-arrow-down-tray')->color('danger')
            ->visible(fn (DataImport $r) => $r->error_rows > 0 && auth()->user()->can('export', DataImport::class))
            ->action(function (DataImport $record) {
                $headings = app(ImportRegistry::class)->get($record->type)->columnNames();
                $csv = app(ImportFile::class)->errorReport($record->rows()->whereNotNull('error')->orderBy('row_number')->cursor(), $headings);

                return response()->streamDownload(fn () => print ($csv), 'import-problems-'.$record->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    /** One download per kind and format, so staff always start from the current headings. */
    public static function templateActions(): ActionGroup
    {
        $actions = [];

        foreach (app(ImportRegistry::class)->all() as $key => $importer) {
            foreach (['xlsx' => 'Excel', 'csv' => 'CSV'] as $format => $name) {
                $actions[] = Action::make("template_{$key}_{$format}")->label("{$importer->label()} ({$name})")
                    ->visible(fn () => auth()->user()->can($importer->module().'.create'))
                    ->action(fn () => response()->streamDownload(fn () => print (app(ImportFile::class)->template($importer, $format)), "template-{$key}.{$format}"));
            }
        }

        return ActionGroup::make($actions)->label('Download a template')->icon('heroicon-o-document-arrow-down')->button();
    }

    public static function getRelations(): array
    {
        return [RowsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListDataImports::route('/'), 'create' => CreateDataImport::route('/create'), 'view' => ViewDataImport::route('/{record}')];
    }
}
