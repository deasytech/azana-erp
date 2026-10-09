<?php

namespace App\Domain\Backup\Actions;

use App\Domain\Backup\Data\StatusCheck as C;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Is this server set up to run the ERP for real? Run by the deployment script before the site is brought back up, and by hand
 * after changing the environment. A failed check blocks a deployment; a warning is something to decide on.
 */
class RunPreflightChecks
{
    /** @return list<C> */
    public function __invoke(): array
    {
        return [
            $this->environment(), $this->debug(), $this->key(), $this->url(), $this->database(), $this->queue(), $this->cache(), $this->mail(),
            $this->hosts(), $this->offsite(), $this->storage(), $this->extensions(),
        ];
    }

    private function environment(): C
    {
        return app()->isProduction() ? new C('Environment', C::OK, 'APP_ENV is production.') : new C('Environment', C::FAILED, 'APP_ENV is "'.app()->environment().'", not production.');
    }

    private function debug(): C
    {
        return config('app.debug') ? new C('Debug mode', C::FAILED, 'APP_DEBUG is on: errors would show code, paths and settings to visitors.') : new C('Debug mode', C::OK, 'Off.');
    }

    private function key(): C
    {
        return config('app.key') ? new C('App key', C::OK, 'Set.') : new C('App key', C::FAILED, 'APP_KEY is empty. Run `php artisan key:generate` once and keep the key safe: encrypted data cannot be read without it.');
    }

    private function url(): C
    {
        return str_starts_with((string) config('app.url'), 'https://')
            ? new C('HTTPS', C::OK, config('app.url'))
            : new C('HTTPS', C::FAILED, 'APP_URL must start with https:// (sessions, 2FA codes and passwords travel over it).');
    }

    private function database(): C
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return new C('Database', C::FAILED, 'Cannot connect to the database.');
        }

        $driver = DB::connection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true)
            ? new C('Database', C::OK, "Connected ({$driver}).")
            : new C('Database', C::WARNING, "Running on {$driver}. Production is built and tested for MySQL.");
    }

    private function queue(): C
    {
        if (config('queue.default') === 'sync') {
            return new C('Queue', C::FAILED, 'QUEUE_CONNECTION is "sync": notifications and backups would run inside web requests.');
        }

        if (config('queue.default') === 'database' && config('queue.connections.database.retry_after') <= 3600) {
            return new C('Queue', C::FAILED, 'DB_QUEUE_RETRY_AFTER must be above 3600: a backup that is still running would be handed to a second worker.');
        }

        return new C('Queue', C::OK, config('queue.default').' (a worker must be running: see deploy/supervisor).');
    }

    private function cache(): C
    {
        return in_array(config('cache.default'), ['array', 'null'], true)
            ? new C('Cache', C::FAILED, 'CACHE_STORE is "'.config('cache.default').'": locks that stop two postings or backups at once would not work.')
            : new C('Cache', C::OK, config('cache.default').'.');
    }

    private function mail(): C
    {
        return in_array(config('mail.default'), ['log', 'array'], true)
            ? new C('Mail', C::WARNING, 'MAIL_MAILER is "'.config('mail.default').'": e-mail alerts are only written to the log.')
            : new C('Mail', C::OK, config('mail.default').'.');
    }

    private function hosts(): C
    {
        return config('website.host') && config('website.erp_host')
            ? new C('Hosts', C::OK, 'Website on '.config('website.host').', ERP on '.config('website.erp_host').'.')
            : new C('Hosts', C::WARNING, 'WEBSITE_HOST and ERP_HOST are not both set: the public site and the ERP both answer on every host.');
    }

    private function offsite(): C
    {
        return config('backup.offsite_disk')
            ? new C('Off-site backups', C::OK, 'Copied to "'.config('backup.offsite_disk').'".')
            : new C('Off-site backups', C::FAILED, 'BACKUP_OFFSITE_DISK is not set: a backup on the same server does not survive losing the server.');
    }

    private function storage(): C
    {
        foreach ([storage_path(), storage_path('app'), base_path('bootstrap/cache')] as $path) {
            if (! is_writable($path)) {
                return new C('Folders', C::FAILED, "{$path} is not writable by the web user.");
            }
        }

        return is_link(public_path('storage')) ? new C('Folders', C::OK, 'Writable; storage link present.') : new C('Folders', C::FAILED, 'Run `php artisan storage:link`.');
    }

    private function extensions(): C
    {
        $missing = array_filter(['bcmath', 'zlib', 'pdo_mysql', 'mbstring', 'intl', 'zip', 'gd'], fn ($e) => ! extension_loaded($e));

        return $missing === [] ? new C('PHP extensions', C::OK, 'All present.') : new C('PHP extensions', C::FAILED, 'Missing: '.implode(', ', $missing).'.');
    }
}
