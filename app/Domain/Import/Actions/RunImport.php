<?php

namespace App\Domain\Import\Actions;

use App\Domain\Import\Support\Importer;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Saves every row through the importer's real domain action inside one transaction. Checking a file rolls the
 * transaction back (nothing is kept); committing keeps it only when no row failed, so a file is all in or all out.
 * Rows are saved in file order, so a row sees the ones before it (a duplicate inside the file is caught).
 */
class RunImport
{
    /**
     * @param  array<int, array<string, ?string>>  $rows  cells by heading, keyed by the sheet's row number
     * @param  ?\Closure  $beforeCommit  runs inside the transaction, after the last row and only when the lot is about to be kept
     * @return array<int, string> the problem for each failed row, keyed by row number (empty when every row passed)
     */
    public function __invoke(Importer $importer, array $rows, bool $commit, ?User $actor, ?\Closure $beforeCommit = null): array
    {
        $importer->reset();
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($this->units($importer, $rows) as $unit) {
                if ($problem = $this->problem($importer, $unit, $actor)) {
                    foreach (array_keys($unit) as $number) {
                        $errors[$number] = $problem;
                    }
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($commit && $errors === []) {
            try {
                $beforeCommit?->__invoke();
            } catch (\Throwable $e) {
                DB::rollBack();

                throw $e;
            }

            DB::commit();
        } else {
            DB::rollBack();
        }

        ksort($errors);

        return $errors;
    }

    /**
     * Rows that make one record: a single row, or all the rows sharing the group column's value.
     *
     * @param  array<int, array<string, ?string>>  $rows
     * @return list<array<int, array<string, ?string>>>
     */
    private function units(Importer $importer, array $rows): array
    {
        $column = $importer->groupBy();

        if ($column === null) {
            return array_map(fn ($number) => [$number => $rows[$number]], array_keys($rows));
        }

        $groups = [];

        foreach ($rows as $number => $row) {
            $groups[strtoupper($row[$column] ?? "\0row{$number}")][$number] = $row;
        }

        return array_values($groups);
    }

    /** @param array<int, array<string, ?string>> $unit */
    private function problem(Importer $importer, array $unit, ?User $actor): ?string
    {
        foreach ($unit as $number => $row) {
            foreach ($importer->requiredColumns() as $column) {
                if (($row[$column] ?? null) === null) {
                    return "{$column} is required.";
                }
            }
        }

        try {
            DB::transaction(fn () => $importer->save(array_values($unit), $actor));

            return null;
        } catch (DomainException $e) {
            return $e->getMessage();
        } catch (QueryException $e) {
            report($e);

            return 'This row could not be saved (the details are in the log).';
        }
    }
}
