<?php

namespace App\Domain\Import\Actions;

use App\Domain\Import\Models\DataImport;
use App\Domain\Import\Support\ImportFile;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validates an uploaded file without saving any of its data: every row goes through the real domain action and the
 * lot is rolled back. What comes back is a stored import with each row's result, ready to download errors from or commit.
 */
class CheckImport
{
    public const DISK = 'local';

    public function __construct(private readonly ImportRegistry $registry, private readonly ImportFile $files, private readonly RunImport $run) {}

    /** @param string $path a readable file on disk (the upload) */
    public function __invoke(string $type, string $path, string $originalName, ?User $actor): DataImport
    {
        $importer = $this->registry->get($type);

        ($actor?->can('data-imports.create') && $actor->can($importer->module().'.create')) || throw new DomainException('You are not allowed to import this kind of data.', 'import_forbidden');

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $rows = $this->files->read($path, $extension, $importer);

        $rows !== [] || throw new DomainException('The file has headings but no data rows.', 'import_empty');

        $numbered = [];

        foreach ($rows as [$number, $cells]) {
            $numbered[$number] = $cells;
        }

        $errors = ($this->run)($importer, $numbered, false, $actor);

        return DB::transaction(function () use ($type, $path, $originalName, $extension, $numbered, $errors, $actor) {
            $stored = 'imports/'.Str::ulid().'.'.strtolower($extension);
            Storage::disk(self::DISK)->put($stored, (string) file_get_contents($path));

            $import = DataImport::create([
                'type' => $type, 'status' => DataImport::CHECKED, 'original_name' => Str::limit($originalName, 200, ''), 'path' => $stored,
                'total_rows' => count($numbered), 'error_rows' => count($errors), 'created_by' => $actor?->getKey(),
            ]);

            foreach (array_chunk(array_keys($numbered), 500) as $chunk) {
                $import->rows()->insert(array_map(fn ($number) => [
                    'data_import_id' => $import->id, 'row_number' => $number,
                    'data' => json_encode($numbered[$number]), 'error' => $errors[$number] ?? null,
                ], $chunk));
            }

            return $import;
        });
    }
}
