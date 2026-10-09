<?php

use App\Domain\Backup\Actions\CreateBackup;
use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Backup\Actions\PruneBackups;
use App\Domain\Backup\Actions\RunPreflightChecks;
use App\Domain\Backup\Actions\TestBackupRestore;
use App\Domain\Backup\BackupException;
use App\Domain\Backup\Data\StatusCheck;
use App\Domain\Backup\Drivers\BackupDriver;
use App\Domain\Backup\Drivers\MySqlBackupDriver;
use App\Domain\Backup\Drivers\SqliteBackupDriver;
use App\Domain\Backup\Models\BackupRun;
use App\Domain\Backup\Notifications\OperationsAlert;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const BACKUP_CHECK = 'Database backup';
const RESTORE_CHECK = 'Restore test';
const DISK_FULL = 'disk full';

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // A real SQLite file stands in for the live database, so a backup and a restore run end to end.
    $this->dbFile = tempnam(sys_get_temp_dir(), 'azana-live').'.sqlite';
    $pdo = new PDO('sqlite:'.$this->dbFile);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT); CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT); CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT)');
    $pdo->exec("INSERT INTO users (name) VALUES ('Ada'), ('Bayo'); INSERT INTO roles (name) VALUES ('Owner'); INSERT INTO migrations (migration) VALUES ('m1'), ('m2'), ('m3')");
    unset($pdo);

    config(['database.connections.live_copy' => ['driver' => 'sqlite', 'database' => $this->dbFile, 'prefix' => '', 'foreign_key_constraints' => false]]);
    app()->bind(BackupDriver::class, fn () => new SqliteBackupDriver('live_copy'));

    Storage::fake('backups');
    Storage::fake('offsite');
    config(['backup.disk' => 'backups', 'backup.offsite_disk' => 'offsite']);
});

afterEach(fn () => @unlink($this->dbFile));

function backupRun(string $startedAgo, array $over = []): BackupRun
{
    static $n = 0;
    $n++;
    $at = CarbonImmutable::parse($startedAgo);

    Storage::disk('backups')->put("database/old-{$n}.sql.gz", 'x');
    Storage::disk('offsite')->put("database/old-{$n}.sql.gz", 'x');

    return BackupRun::create($over + ['kind' => 'backup', 'status' => 'success', 'file' => "database/old-{$n}.sql.gz", 'started_at' => $at, 'finished_at' => $at, 'offsite' => true]);
}

describe('taking a backup', function () {
    it('writes a readable compressed copy to the server and off-site and records it', function () {
        $run = app(CreateBackup::class)();

        expect($run->status)->toBe('success')->and($run->offsite)->toBeTrue()->and($run->checksum)->toHaveLength(64)->and($run->size_bytes)->toBeGreaterThan(0);
        Storage::disk('backups')->assertExists($run->file);
        Storage::disk('offsite')->assertExists($run->file);
        expect(hash('sha256', Storage::disk('backups')->get($run->file)))->toBe($run->checksum)
            ->and(strlen(gzdecode(Storage::disk('backups')->get($run->file))))->toBeGreaterThan(1000);
    });

    it('says plainly when there is no off-site copy', function () {
        config(['backup.offsite_disk' => null]);

        $run = app(CreateBackup::class)();

        expect($run->offsite)->toBeFalse()->and($run->message)->toContain('this server only');
    });

    it('records a failure and raises it', function () {
        app()->bind(BackupDriver::class, fn () => new class implements BackupDriver
        {
            public function dump(string $gzPath): void
            {
                throw new BackupException(DISK_FULL);
            }

            public function restoreScratch(string $gzPath): array
            {
                return [];
            }
        });

        expect(fn () => app(CreateBackup::class)())->toThrow(BackupException::class, DISK_FULL);

        $run = BackupRun::first();
        expect($run->status)->toBe('failed')->and($run->message)->toBe(DISK_FULL)->and($run->file)->toBeNull();
    });

    it('is available as a command', function () {
        $this->artisan('erp:backup')->expectsOutputToContain('copied off-site')->assertSuccessful();

        expect(BackupRun::where('status', 'success')->count())->toBe(1);
    });
});

describe('proving a restore', function () {
    it('restores the newest backup into a scratch copy and reports what came back', function () {
        app(CreateBackup::class)();

        $test = app(TestBackupRestore::class)();

        expect($test->status)->toBe('success')->and($test->message)->toContain('Restored 3 tables')->and($test->message)->toContain('Users: 2')
            ->and($test->backup_run_id)->toBe(BackupRun::where('kind', 'backup')->value('id'));
    });

    it('fails when the file was changed after it was taken', function () {
        $backup = app(CreateBackup::class)();
        Storage::disk('backups')->put($backup->file, 'tampered');
        Storage::disk('offsite')->delete($backup->file);

        $test = app(TestBackupRestore::class)();

        expect($test->status)->toBe('failed')->and($test->message)->toContain('checksum');
    });

    it('fetches the off-site copy when the local file is gone', function () {
        $backup = app(CreateBackup::class)();
        Storage::disk('backups')->delete($backup->file);

        expect(app(TestBackupRestore::class)()->status)->toBe('success');
    });

    it('fails a restore that comes back without its people', function () {
        $pdo = new PDO('sqlite:'.$this->dbFile);
        $pdo->exec('DELETE FROM users');
        unset($pdo);
        app(CreateBackup::class)();

        $test = app(TestBackupRestore::class)();

        expect($test->status)->toBe('failed')->and($test->message)->toContain('users table is empty');
    });

    it('fails clearly when there is nothing to test', function () {
        $test = app(TestBackupRestore::class)();

        expect($test->status)->toBe('failed')->and($test->message)->toContain('no backup');
    });

    it('will not use the live database as the MySQL scratch database', function () {
        config(['database.connections.mysql_x' => ['driver' => 'mysql', 'database' => 'azana_erp', 'host' => 'h', 'username' => 'u', 'password' => 'p'], 'backup.restore_test_database' => 'azana_erp']);

        expect(fn () => (new MySqlBackupDriver('mysql_x'))->restoreScratch('/dev/null'))->toThrow(BackupException::class, 'scratch database');
    });
});

describe('a failed copy', function () {
    it('removes the stored backup when the off-site copy fails', function () {
        config(['filesystems.disks.broken' => ['driver' => 'local', 'root' => '/dev/null/nowhere', 'throw' => false], 'backup.offsite_disk' => 'broken']);

        $failed = false;

        try {
            app(CreateBackup::class)();
        } catch (Throwable) {
            $failed = true;
        }

        expect($failed)->toBeTrue();

        expect(Storage::disk('backups')->allFiles())->toBe([])->and(BackupRun::first()->status)->toBe('failed');
    });
});

describe('retention', function () {
    it('keeps recent days, the newest of each week and month, and always the newest good backup', function () {
        $now = CarbonImmutable::parse('2026-10-15 12:00');
        config(['backup.retention' => ['daily_days' => 3, 'weekly_weeks' => 3, 'monthly_months' => 3]]);

        $today = backupRun('2026-10-15 02:00');
        $twoDays = backupRun('2026-10-13 02:00');
        $lastWeekA = backupRun('2026-10-08 02:00');   // Thursday of the previous week
        $lastWeekB = backupRun('2026-10-07 02:00');   // earlier in that week: dropped, the newest of the week stays
        $threeWeeks = backupRun('2026-09-25 02:00');
        $old = backupRun('2026-01-05 02:00');         // outside every window
        $failed = backupRun('2026-10-01 02:00', ['status' => 'failed']);

        $removed = app(PruneBackups::class)($now);

        $kept = BackupRun::whereNull('pruned_at')->pluck('id')->all();
        expect($removed)->toBe(2)->and($kept)->toContain($today->id, $twoDays->id, $lastWeekA->id, $threeWeeks->id, $failed->id)
            ->and($kept)->not->toContain($lastWeekB->id, $old->id);
        Storage::disk('backups')->assertMissing($old->file);
        Storage::disk('offsite')->assertMissing($old->file);
        Storage::disk('backups')->assertExists($today->file);
    });

    it('never removes the only good backup or the one behind the last passed restore test', function () {
        config(['backup.retention' => ['daily_days' => 1, 'weekly_weeks' => 0, 'monthly_months' => 0]]);
        $now = CarbonImmutable::parse('2026-10-15 12:00');

        $proven = backupRun('2026-08-01 02:00');
        $newest = backupRun('2026-09-01 02:00');
        $stale = backupRun('2026-08-15 02:00');
        BackupRun::create(['kind' => 'restore_test', 'status' => 'success', 'backup_run_id' => $proven->id, 'started_at' => '2026-08-02']);

        app(PruneBackups::class)($now);

        expect(BackupRun::whereNull('pruned_at')->where('kind', 'backup')->pluck('id')->all())->toEqualCanonicalizing([$proven->id, $newest->id])
            ->and($stale->fresh()->pruned_at)->not->toBeNull();
    });
});

describe('monitoring', function () {
    function checkNamed(string $name): StatusCheck
    {
        return collect(app(GetOperationsStatus::class)())->firstWhere('name', $name);
    }

    it('fails when no backup exists and passes after a fresh tested one', function () {
        expect(checkNamed(BACKUP_CHECK)->state)->toBe(StatusCheck::FAILED)
            ->and(checkNamed(RESTORE_CHECK)->state)->toBe(StatusCheck::WARNING);

        app(CreateBackup::class)();
        app(TestBackupRestore::class)();

        expect(checkNamed(BACKUP_CHECK)->state)->toBe(StatusCheck::OK)
            ->and(checkNamed('Off-site copy')->state)->toBe(StatusCheck::OK)
            ->and(checkNamed(RESTORE_CHECK)->state)->toBe(StatusCheck::OK);
    });

    it('fails on an old backup, a missing off-site copy and a failed restore test', function () {
        backupRun('-3 days', ['offsite' => false]);
        BackupRun::create(['kind' => 'restore_test', 'status' => 'failed', 'message' => 'boom', 'started_at' => now()]);

        expect(checkNamed(BACKUP_CHECK)->state)->toBe(StatusCheck::FAILED)
            ->and(checkNamed('Off-site copy')->state)->toBe(StatusCheck::FAILED)
            ->and(checkNamed(RESTORE_CHECK)->detail)->toContain('boom');
    });

    it('sees whether the scheduler is alive', function () {
        expect(checkNamed('Scheduler')->state)->toBe(StatusCheck::FAILED);

        Cache::put(GetOperationsStatus::HEARTBEAT, now()->toIso8601String(), 600);
        expect(checkNamed('Scheduler')->state)->toBe(StatusCheck::OK);

        Cache::put(GetOperationsStatus::HEARTBEAT, now()->subMinutes(20)->toIso8601String(), 600);
        expect(checkNamed('Scheduler')->state)->toBe(StatusCheck::FAILED);
    });

    it('sees a queue nobody is working', function () {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(30)->timestamp, 'created_at' => now()->subMinutes(30)->timestamp]);

        expect(checkNamed('Queue worker')->state)->toBe(StatusCheck::FAILED);
    });

    it('alerts the people who watch backups once for the same problem', function () {
        Notification::fake();
        $owner = owner();
        $worker = farmWorker();

        $this->artisan('erp:monitor')->assertFailed();
        $this->artisan('erp:monitor')->assertFailed();

        Notification::assertSentToTimes($owner, OperationsAlert::class, 1);
        Notification::assertNotSentTo($worker, OperationsAlert::class);
    });

    it('is scheduled: backup, restore test, monitor and the heartbeat', function () {
        Artisan::call('schedule:list');
        $list = Artisan::output();

        expect($list)->toContain('erp:backup')->and($list)->toContain('erp:backup:test-restore')->and($list)->toContain('erp:monitor');
    });
});

describe('preflight', function () {
    it('blocks a deployment on an unsafe setup and passes a safe one', function () {
        config(['app.debug' => true, 'queue.default' => 'sync', 'backup.offsite_disk' => null, 'app.url' => 'http://erp.test']);
        $this->artisan('erp:preflight')->expectsOutputToContain('Not ready for production')->assertFailed();

        $failed = collect(app(RunPreflightChecks::class)())->where('state', StatusCheck::FAILED)->pluck('name')->all();
        expect($failed)->toContain('Debug mode', 'Queue', 'Off-site backups', 'HTTPS');
    });

    it('is satisfied by a production-like environment', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => false, 'app.url' => 'https://erp.test', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 3700, 'cache.default' => 'database', 'backup.offsite_disk' => 'offsite',
            'website.host' => 'www.test', 'website.erp_host' => 'erp.test', 'mail.default' => 'smtp']);

        $checks = collect(app(RunPreflightChecks::class)())->keyBy('name');

        expect($checks['Environment']->state)->toBe(StatusCheck::OK)->and($checks['Debug mode']->state)->toBe(StatusCheck::OK)
            ->and($checks['HTTPS']->state)->toBe(StatusCheck::OK)->and($checks['Off-site backups']->state)->toBe(StatusCheck::OK)
            ->and($checks['Queue']->state)->toBe(StatusCheck::OK)->and($checks['Hosts']->state)->toBe(StatusCheck::OK);
    });

    it('refuses a queue that would hand a running backup to a second worker', function () {
        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);

        $queue = collect(app(RunPreflightChecks::class)())->firstWhere('name', 'Queue');

        expect($queue->state)->toBe(StatusCheck::FAILED)->and($queue->detail)->toContain('DB_QUEUE_RETRY_AFTER');
    });

    it('ships an executable, strict deployment script', function () {
        $script = base_path('deploy/deploy.sh');

        expect(is_executable($script))->toBeTrue()
            ->and(file_get_contents($script))->toContain('set -euo pipefail')->toContain('erp:backup')->toContain('migrate --force')->toContain('erp:preflight')->toContain('queue:restart');
    });
});
