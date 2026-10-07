<?php

namespace App\Domain\Reporting\Exports;

use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Turns a ReportDocument into a CSV, Excel (xlsx) or PDF file, returned as the file's contents. */
class ExportReport
{
    public function csv(ReportDocument $document): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, [$document->title, $document->subtitle], escape: '');

        foreach ($document->sections as $section) {
            fputcsv($out, [], escape: '');
            fputcsv($out, [$this->safe($section['title'])], escape: '');
            fputcsv($out, array_map($this->safe(...), $section['headings']), escape: '');

            foreach ($section['rows'] as $row) {
                fputcsv($out, array_map($this->safe(...), $row), escape: '');
            }
        }

        rewind($out);

        return "\xEF\xBB\xBF".stream_get_contents($out);   // a byte-order mark so Excel reads UTF-8 (the naira sign) correctly
    }

    public function xlsx(ReportDocument $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([$document->title, $document->subtitle]));

        foreach ($document->sections as $section) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$this->safe($section['title'])]));
            $writer->addRow(Row::fromValues(array_map($this->safe(...), $section['headings'])));

            foreach ($section['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_map($this->safe(...), $row)));
            }
        }

        $writer->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    public function pdf(ReportDocument $document): string
    {
        $pdf = new Dompdf((new Options)->set('isRemoteEnabled', false)->set('isHtml5ParserEnabled', true));
        $pdf->loadHtml(view('reports.document', ['document' => $document])->render());
        $pdf->setPaper('A4', $this->wide($document) ? 'landscape' : 'portrait');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** Text that starts like a spreadsheet formula (= + @, or - not followed by a number or an amount) is kept as text by a leading apostrophe. */
    private function safe(string $cell): string
    {
        $formula = preg_match('/^[=+@]/', $cell) || (str_starts_with($cell, '-') && ! preg_match('/^-\s?([A-Z]{3}\s?)?[\d.,]+%?$/', $cell));

        return $formula ? "'".$cell : $cell;
    }

    private function wide(ReportDocument $document): bool
    {
        return collect($document->sections)->contains(fn ($s) => count($s['headings']) > 5);
    }
}
