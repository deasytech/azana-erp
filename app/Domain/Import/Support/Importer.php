<?php

namespace App\Domain\Import\Support;

use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of import. A row is a record (or, for importers that group, one line of a record). The real domain
 * action does the saving, so an import obeys exactly the rules of entering the same data by hand; the runner
 * checks a file by saving it all inside a transaction it then rolls back.
 *
 * Row values arrive as trimmed strings; an empty cell is null.
 */
abstract class Importer
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** The permission module that owns the data this importer creates. */
    abstract public function module(): string;

    /** @return list<ImportColumn> */
    abstract public function columns(): array;

    /** Column that ties several rows into one record (a feed formula's ingredient lines); null when a row is a record. */
    public function groupBy(): ?string
    {
        return null;
    }

    /**
     * Saves one record. Throws DomainException when a rule is broken.
     *
     * @param  list<array<string, ?string>>  $rows
     */
    abstract public function save(array $rows, ?User $actor): void;

    /** Called once before a run, so importers can start with empty look-up caches. */
    public function reset(): void
    {
        $this->found = [];
    }

    /** @var array<string, array<string, Model>> look-ups already made in this run */
    private array $found = [];

    /** @return list<string> */
    final public function requiredColumns(): array
    {
        return array_values(array_map(fn (ImportColumn $c) => $c->name, array_filter($this->columns(), fn (ImportColumn $c) => $c->required)));
    }

    /** @return list<string> */
    final public function columnNames(): array
    {
        return array_map(fn (ImportColumn $c) => $c->name, $this->columns());
    }

    /**
     * The contact columns customers and suppliers share.
     *
     * @return list<ImportColumn>
     */
    protected function contactColumns(): array
    {
        return [
            new ImportColumn('contact_name', 'The person to speak to.'),
            new ImportColumn('phone', 'Telephone number. Format the column as text so a leading 0 is kept.'),
            new ImportColumn('email', 'E-mail address.'),
            new ImportColumn('address', 'Postal or delivery address.'),
            new ImportColumn('tax_number', 'Tax identification number.'),
        ];
    }

    // ---- Helpers for importers --------------------------------------------------------------------------------------

    /** @param array<string, ?string> $row */
    protected function need(array $row, string $column): string
    {
        return $row[$column] ?? throw $this->problem($column, 'is required');
    }

    protected function problem(string $column, string $message): DomainException
    {
        return new DomainException("{$column} {$message}.", 'import_row');
    }

    /** @param array<string, ?string> $row */
    protected function date(array $row, string $column, bool $required = false): ?CarbonInterface
    {
        $value = $required ? $this->need($row, $column) : ($row[$column] ?? null);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[3], (int) $m[2], (int) $m[1]];   // day first, as the farm writes it
        } else {
            throw $this->problem($column, "must be a date written YYYY-MM-DD (you wrote \"{$value}\")");
        }

        if (! checkdate($month, $day, $year) || $year < 1990) {
            throw $this->problem($column, "is not a real date (\"{$value}\")");
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    /** A non-negative decimal with at most $places decimals, as a string (never a float). */
    protected function decimal(array $row, string $column, int $places = 3, bool $required = false): ?string
    {
        $value = $required ? $this->need($row, $column) : ($row[$column] ?? null);

        if ($value === null) {
            return null;
        }

        $clean = str_replace([',', ' '], '', $value);

        if (! preg_match('/^\d+(\.\d+)?$/', $clean) || strlen(explode('.', $clean)[1] ?? '') > $places) {
            throw $this->problem($column, "must be a number of 0 or more with at most {$places} decimals (\"{$value}\")");
        }

        return $clean;
    }

    /** Money written in major units (1250.50) as integer minor units. */
    protected function minor(array $row, string $column, bool $required = false): ?int
    {
        $value = $this->decimal($row, $column, 2, $required);

        return $value === null ? null : (int) bcmul($value, '100', 0);
    }

    protected function whole(array $row, string $column, bool $required = false): ?int
    {
        $value = $required ? $this->need($row, $column) : ($row[$column] ?? null);

        if ($value === null) {
            return null;
        }

        if (! preg_match('/^\d+$/', $value)) {
            throw $this->problem($column, "must be a whole number (\"{$value}\")");
        }

        return (int) $value;
    }

    /** @param array<string, ?string> $row */
    protected function flag(array $row, string $column, bool $default): bool
    {
        $value = strtolower($row[$column] ?? '');

        return match (true) {
            $value === '' => $default,
            in_array($value, ['yes', 'y', 'true', '1'], true) => true,
            in_array($value, ['no', 'n', 'false', '0'], true) => false,
            default => throw $this->problem($column, "must be yes or no (\"{$value}\")"),
        };
    }

    /**
     * @param  array<string, ?string>  $row
     * @param  list<string>  $allowed
     */
    protected function choice(array $row, string $column, array $allowed, bool $required = false): ?string
    {
        $value = $required ? $this->need($row, $column) : ($row[$column] ?? null);

        if ($value === null) {
            return null;
        }

        $value = strtolower(str_replace([' ', '-'], '_', $value));

        return in_array($value, $allowed, true) ? $value : throw $this->problem($column, 'must be one of: '.implode(', ', $allowed)." (\"{$value}\")");
    }

    /**
     * An active record that the cell names by its code or its name (any case). The same cell always finds the same record in a run.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @param  ?\Closure  $scope  fn (Builder $query) narrowing the candidates (e.g. one lookup category); $scopeKey names it for the cache
     * @return ?T
     */
    protected function find(string $model, array $row, string $column, ?\Closure $scope = null, bool $required = false, string $label = 'record', string $scopeKey = ''): ?Model
    {
        $value = $required ? $this->need($row, $column) : ($row[$column] ?? null);

        if ($value === null) {
            return null;
        }

        $key = $model.'|'.$scopeKey.'|'.mb_strtolower($value);

        return $this->found[$model][$key] ??= $model::query()
            ->where('is_active', true)
            ->when($scope, fn ($q) => $scope($q))
            ->where(fn ($q) => $q->whereRaw('LOWER(code) = ?', [mb_strtolower($value)])->orWhereRaw('LOWER(name) = ?', [mb_strtolower($value)]))
            ->first() ?? throw $this->problem($column, "\"{$value}\" is not a known {$label}");
    }
}
