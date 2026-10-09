<?php

use App\Domain\Import\Models\DataImport;
use App\Domain\Sales\Models\Customer;
use App\Filament\Pages\BackupsAndMonitoring;
use App\Filament\Resources\DataImports\DataImportResource;
use App\Filament\Resources\DataImports\Pages\CreateDataImport;
use App\Filament\Resources\DataImports\Pages\ListDataImports;
use App\Filament\Resources\DataImports\Pages\ViewDataImport;
use App\Filament\Resources\DataImports\RelationManagers\RowsRelationManager;
use App\Jobs\RunBackup;
use App\Jobs\RunBackupRestoreTest;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const IMPORT_MANAGER = 'General Manager';
beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
    Storage::fake('local');
});

function uploadCsv(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('customers.csv', $content);
}

describe('the import screens', function () {
    it('checks an uploaded file, shows the verdict and imports it on confirmation', function () {
        $this->actingAs(userWithRole(IMPORT_MANAGER));

        $create = Livewire::test(CreateDataImport::class)
            ->fillForm(['type' => 'customers', 'file' => uploadCsv("name,customer_type\nAda Farms,farmer\nBayo Ltd,farmer\n")])
            ->call('create')
            ->assertHasNoFormErrors();

        $import = DataImport::first();
        $create->assertRedirect(DataImportResource::getUrl('view', ['record' => $import]));
        expect($import->total_rows)->toBe(2)->and(Customer::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles('import-uploads'))->toBe([]);   // the upload is not kept lying around

        Livewire::test(ViewDataImport::class, ['record' => $import->id])
            ->assertActionVisible('commit')->assertActionHidden('errors')
            ->callAction('commit')
            ->assertNotified('Imported');

        expect(Customer::count())->toBe(2)->and($import->fresh()->status)->toBe(DataImport::COMMITTED);
    });

    it('hides the import button for a file with problems and lists the failed rows', function () {
        $this->actingAs(userWithRole(IMPORT_MANAGER));
        $import = csvImport('customers', [['name', 'customer_type'], ['Ada Farms', 'farmer'], ['', 'farmer']]);

        Livewire::test(ViewDataImport::class, ['record' => $import->id])
            ->assertActionHidden('commit')->assertActionVisible('errors');

        Livewire::test(RowsRelationManager::class, ['ownerRecord' => $import, 'pageClass' => ViewDataImport::class])
            ->assertCanSeeTableRecords($import->rows()->orderBy('row_number')->get())
            ->assertSee('name is required');
    });

    it('explains a file with the wrong headings and creates nothing', function () {
        $this->actingAs(userWithRole(IMPORT_MANAGER));

        Livewire::test(CreateDataImport::class)
            ->fillForm(['type' => 'customers', 'file' => uploadCsv("nom,type\nAda,farmer\n")])
            ->call('create')
            ->assertNotified();

        expect(DataImport::count())->toBe(0);
    });

    it('is closed to people without the import permissions', function () {
        $this->actingAs(userWithRole('Sales Officer'));

        expect(DataImportResource::canViewAny())->toBeFalse()->and(DataImportResource::canCreate())->toBeFalse();
    });

    it('lists imports with their results', function () {
        $this->actingAs(userWithRole(IMPORT_MANAGER));
        csvImport('customers', [['name', 'customer_type'], ['Ada Farms', 'farmer']]);

        Livewire::test(ListDataImports::class)->assertCanSeeTableRecords(DataImport::all())->assertSee('Customers');
    });
});

describe('backups and monitoring page', function () {
    it('shows the system status and history to those who may view it', function () {
        $this->actingAs(userWithRole(IMPORT_MANAGER));

        Livewire::test(BackupsAndMonitoring::class)
            ->assertSee('System status')->assertSee('Database backup')->assertSee('No backup has been taken yet')
            ->assertActionHidden('backup')->assertActionHidden('restoreTest');
    });

    it('queues a backup and a restore test for people who may', function () {
        Queue::fake();
        $this->actingAs(owner());

        Livewire::test(BackupsAndMonitoring::class)
            ->callAction('backup')->assertNotified('Backup started')
            ->callAction('restoreTest')->assertNotified('Restore test started');

        Queue::assertPushed(RunBackup::class);
        Queue::assertPushed(RunBackupRestoreTest::class);
    });

    it('is closed to everyone else', function () {
        $this->actingAs(userWithRole('Farm Worker'));

        expect(BackupsAndMonitoring::canAccess())->toBeFalse();
    });
});
