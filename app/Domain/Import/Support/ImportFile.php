<?php

namespace App\Domain\Import\Support;

use App\Domain\Import\Models\DataImportRow;
use App\Domain\System\Exceptions\DomainException;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;

/** Reads an uploaded CSV or Excel file into rows keyed by column heading, and writes the blank templates. */
class ImportFile
{
    public const MAX_ROWS = 5000;

    public const EXTENSIONS = ['csv', 'xlsx'];

    /**
     * @return list<array{0: int, 1: array<string, ?string>}> pairs of the sheet's row number and the cells by heading
     */
    public function read(string $path, string $extension, Importer $importer): array
    {
        $extension = strtolower($extension);
        in_array($extension, self::EXTENSIONS, true) || throw new DomainException('Upload a .csv or .xlsx file.', 'import_file_type');

        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        try {
            return $this->rows($reader, $importer);
        } finally {
            $reader->close();
        }
    }

    /** @return list<array{0: int, 1: array<string, ?string>}> */
    private function rows(CsvReader|XlsxReader $reader, Importer $importer): array
    {
        $headings = null;
        $rows = [];
        $number = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $number++;
                $cells = array_map($this->cell(...), $row->getCells());

                if ($headings === null) {
                    $headings = $this->headings($cells, $importer);

                    continue;
                }

                if (count(array_filter($cells, fn ($c) => $c !== null)) === 0) {
                    continue;
                }

                count($rows) < self::MAX_ROWS || throw new DomainException('A file may hold up to '.self::MAX_ROWS.' rows; split it and import it in parts.', 'import_too_large');

                $rows[] = [$number, array_combine($headings, array_pad(array_slice($cells, 0, count($headings)), count($headings), null))];
            }

            break;   // the first sheet only: a template's second sheet is the guide
        }

        $headings !== null || throw new DomainException('The file is empty: the first row must hold the column headings.', 'import_empty');

        return $rows;
    }

    /**
     * @param  list<?string>  $cells
     * @return list<string>
     */
    private function headings(array $cells, Importer $importer): array
    {
        $headings = array_map(fn (?string $c) => $c === null ? '' : trim(str_replace([' ', '-'], '_', strtolower($c)), "_ \t\xEF\xBB\xBF"), $cells);
        while ($headings !== [] && end($headings) === '') {
            array_pop($headings);
        }

        in_array('', $headings, true) && throw new DomainException('A column heading is blank: remove the empty column or name it.', 'import_headings');
        count($headings) === count(array_unique($headings)) || throw new DomainException('Two columns have the same heading.', 'import_headings');

        $missing = array_diff($importer->requiredColumns(), $headings);
        $missing === [] || throw new DomainException('The file is missing the column(s): '.implode(', ', $missing).'. Download the template to see the headings.', 'import_headings');

        $unknown = array_diff($headings, $importer->columnNames());
        $unknown === [] || throw new DomainException('Unknown column(s): '.implode(', ', $unknown).'. Download the template to see the headings.', 'import_headings');

        return $headings;
    }

    private function cell(mixed $cell): ?string
    {
        $value = is_object($cell) && method_exists($cell, 'getValue') ? $cell->getValue() : $cell;

        if ($cell instanceof DateTimeCell || $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) || is_int($value)) {
            $value = is_int($value) ? (string) $value : rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        $value = trim((string) $value);

        // A spreadsheet formula is never evaluated, and never stored as one.
        return $value === '' ? null : $value;
    }

    /** A blank template with the headings and, in Excel, a second sheet that explains each column. */
    public function template(Importer $importer, string $format): string
    {
        $headings = $importer->columnNames();

        if ($format === 'csv') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $headings, escape: '');
            rewind($out);

            return "\xEF\xBB\xBF".stream_get_contents($out);
        }

        $path = tempnam(sys_get_temp_dir(), 'tpl');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Data');
        $writer->addRow(Row::fromValues($headings));
        $writer->addNewSheetAndMakeItCurrent()->setName('Guide');
        $writer->addRow(Row::fromValues(['Column', 'Required', 'What to write', 'Example']));

        foreach ($importer->columns() as $column) {
            $writer->addRow(Row::fromValues([$column->name, $column->required ? 'Yes' : 'No', $column->help, $column->example]));
        }

        $writer->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    /**
     * The rows that failed, as a CSV the user can correct and upload again.
     *
     * @param  iterable<DataImportRow>  $rows
     * @param  list<string>  $headings
     */
    public function errorReport(iterable $rows, array $headings): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['sheet_row', 'problem', ...$headings], escape: '');

        foreach ($rows as $row) {
            fputcsv($out, [$row->row_number, $this->safe((string) $row->error), ...array_map(fn ($h) => $this->safe((string) ($row->data[$h] ?? '')), $headings)], escape: '');
        }

        rewind($out);

        return "\xEF\xBB\xBF".stream_get_contents($out);
    }

    /** Text that would be read as a formula when the report is opened in a spreadsheet is kept as text. */
    private function safe(string $cell): string
    {
        return preg_match('/^[=+@\t\r-]/', $cell) ? "'".$cell : $cell;
    }
}
