<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/** The one shape every form section takes: a titled card with an icon and a one-line description, fields in two columns and notes full width. */
class FormSections
{
    /** @param  list<Component>  $fields */
    public static function make(string $heading, string $description, array $fields, string|BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentList, int $columns = 2): Section
    {
        return Section::make($heading)
            ->description($description)
            ->icon($icon)
            ->columnSpanFull()
            ->columns($columns)
            ->schema(array_map(fn (Component $f): Component => $f instanceof Textarea ? $f->rows(3)->columnSpanFull() : $f, $fields));
    }
}
