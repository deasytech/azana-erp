<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The business-code input: filled with the next free code when a record is created, with an icon that offers another, so nobody has to
 * invent or retype one. The code stays editable (a person may need a specific one) and the unique rule still guards it.
 */
class CodeField
{
    /**
     * The next code for a table: the prefix and one more than the highest number already used with it, e.g. PEN-0007 after PEN-0006.
     * Soft-deleted rows count, because the unique rule does. $scope narrows the rows that share a code space (e.g. one price list).
     */
    public static function next(string $table, string $prefix, ?Closure $scope = null, string $column = 'code', int $pad = 4): string
    {
        // The number after "PREFIX-", read as an integer by the database, so only one value comes back however many records exist. A code
        // that merely starts with the prefix (PEN-12X) reads as its leading digits, which can only skip a number, never repeat one.
        $cast = DB::connection()->getDriverName() === 'mysql' ? 'UNSIGNED' : 'INTEGER';
        $highest = (int) DB::table($table)
            ->where($column, 'like', $prefix.'-%')
            ->when($scope, fn (Builder $q) => $scope($q))
            ->max(DB::raw("CAST(SUBSTR({$column}, ".(strlen($prefix) + 2).") AS {$cast})"));

        return sprintf('%s-%0'.$pad.'d', $prefix, $highest + 1);
    }

    /**
     * @param  string  $table  the table whose codes must not repeat
     * @param  string  $prefix  the letters the codes of this record type start with
     * @param  ?Closure  $scope  fn (Builder $query) narrowing the rows that share a code space
     */
    public static function make(string $table, string $prefix, ?Closure $scope = null, string $name = 'code'): TextInput
    {
        return TextInput::make($name)
            ->default(fn (): string => static::next($table, $prefix, $scope, $name))
            ->suffixAction(
                Action::make('generate'.ucfirst($name))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->tooltip('Generate the next free code')
                    ->hidden(fn (string $operation): bool => $operation !== 'create')
                    ->action(fn (Set $set) => $set($name, static::next($table, $prefix, $scope, $name))),
            );
    }

    /**
     * A lower snake_case code made from a name, for the lookup lists (e.g. "Weaner pen" gives weaner_pen). Accents are transliterated
     * first ("Épaule" gives epaule); a name with nothing usable in it, such as one in a script that has no ASCII form, gets a short
     * code from its hash so the field is never left empty. A blank name gives a blank code.
     */
    public static function slug(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $slug = str($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(60, '')->toString();

        return $slug !== '' ? $slug : 'entry_'.substr(hash('sha256', $name), 0, 8);
    }
}
