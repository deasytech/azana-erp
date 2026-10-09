<?php

namespace App\Domain\Import\Actions;

use App\Domain\Import\Importers\AnimalImporter;
use App\Domain\Import\Importers\CustomerImporter;
use App\Domain\Import\Importers\FeedFormulaImporter;
use App\Domain\Import\Importers\HistoricalSalesImporter;
use App\Domain\Import\Importers\InventoryOpeningImporter;
use App\Domain\Import\Importers\SupplierImporter;
use App\Domain\Import\Support\Importer;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;

/** The kinds of import the system offers. Add a class here to add a kind. */
class ImportRegistry
{
    private const IMPORTERS = [
        CustomerImporter::class, SupplierImporter::class, AnimalImporter::class, InventoryOpeningImporter::class,
        FeedFormulaImporter::class, HistoricalSalesImporter::class,
    ];

    /** @return array<string, Importer> by key */
    public function all(): array
    {
        $all = [];

        foreach (self::IMPORTERS as $class) {
            $importer = app($class);
            $all[$importer->key()] = $importer;
        }

        return $all;
    }

    public function get(string $key): Importer
    {
        return $this->all()[$key] ?? throw new DomainException('That kind of import does not exist.', 'import_type');
    }

    /** The kinds this person may load: they need to be allowed to create that data by hand. */
    public function allowedFor(?User $user): array
    {
        return array_filter($this->all(), fn (Importer $i) => $user?->can('data-imports.create') && $user->can($i->module().'.create'));
    }
}
