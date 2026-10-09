<?php

namespace App\Domain\Import\Actions;

use App\Domain\Import\Models\DataImport;
use App\Domain\System\Actions\RecordAudit;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;

/**
 * Saves a checked import for real. Only a file with no failed row can be committed, and only once; the data is
 * checked again as it is saved, because the farm's records may have changed since the check.
 */
class CommitImport
{
    public function __construct(private readonly ImportRegistry $registry, private readonly RunImport $run) {}

    public function __invoke(DataImport $import, ?User $actor): DataImport
    {
        $importer = $this->registry->get($import->type);

        ($actor?->can('data-imports.approve') && $actor->can($importer->module().'.create')) || throw new DomainException('You are not allowed to commit this import.', 'import_forbidden');

        $import = $import->fresh();

        // Claim the file first, so a second click or a second person cannot commit it twice.
        $import->isReady() || throw new DomainException($import->status === DataImport::CHECKED
            ? 'Fix the failed rows and upload the file again: nothing is imported until every row passes.'
            : 'This import was already '.$import->status.'.', $import->status === DataImport::CHECKED ? 'import_has_errors' : 'import_closed');

        DataImport::whereKey($import->id)->where('status', DataImport::CHECKED)->update(['status' => DataImport::COMMITTING]) === 1
            || throw new DomainException('This import is already being committed.', 'import_closed');

        $import->refresh();

        $errors = [];

        try {
            $rows = $import->rows()->orderBy('row_number')->get()->mapWithKeys(fn ($r) => [$r->row_number => $r->data])->all();
            $errors = ($this->run)($importer, $rows, true, $actor, function () use ($import, $actor) {
                $import->update(['status' => DataImport::COMMITTED, 'committed_by' => $actor->getKey(), 'committed_at' => now()]);
                app(RecordAudit::class)('imported', $import, [], ['rows' => $import->total_rows, 'type' => $import->type]);
            });
        } catch (\Throwable $e) {
            $import->update(['status' => DataImport::CHECKED]);

            throw $e;
        }

        if ($errors !== []) {
            foreach ($errors as $number => $message) {
                $import->rows()->where('row_number', $number)->update(['error' => $message]);
            }

            $import->update(['status' => DataImport::CHECKED, 'error_rows' => count($errors)]);

            throw new DomainException('The data no longer passes the checks (something changed since it was uploaded). Nothing was imported; see the failed rows.', 'import_has_errors');
        }

        return $import;
    }
}
