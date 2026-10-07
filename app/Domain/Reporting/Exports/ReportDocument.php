<?php

namespace App\Domain\Reporting\Exports;

/** A report as plain tables, whatever it came from: a title, and sections each with headings and rows of text. Exports are made from this. */
final class ReportDocument
{
    /** @param list<array{title: string, headings: list<string>, rows: list<list<string>>}> $sections */
    public function __construct(public readonly string $title, public readonly string $subtitle, public readonly array $sections = []) {}

    /** @param list<string> $headings @param list<list<string|int|null>> $rows */
    public function with(string $title, array $headings, array $rows): self
    {
        return new self($this->title, $this->subtitle, [...$this->sections, [
            'title' => $title, 'headings' => $headings, 'rows' => array_map(fn (array $row) => array_map(fn ($cell) => (string) $cell, $row), array_values($rows)),
        ]]);
    }

    public function filename(string $extension): string
    {
        return str($this->title.' '.$this->subtitle)->slug().'.'.$extension;
    }
}
