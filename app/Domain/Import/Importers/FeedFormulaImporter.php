<?php

namespace App\Domain\Import\Importers;

use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Import\Support\ImportColumn;
use App\Domain\Import\Support\Importer;
use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;

/**
 * Feed recipes, one ingredient per row; the rows with the same formula_code make one formula. Each is saved as a
 * draft (version 1) through SaveFeedFormula, to be reviewed and activated by the nutritionist as usual.
 */
class FeedFormulaImporter extends Importer
{
    public function __construct(private readonly SaveFeedFormula $save) {}

    public function key(): string
    {
        return 'feed_formulas';
    }

    public function label(): string
    {
        return 'Feed formulas';
    }

    public function description(): string
    {
        return 'Recipes, one ingredient per row; rows sharing a formula_code make one formula. They arrive as drafts for the nutritionist to check and activate. Ingredients must already be stock items.';
    }

    public function module(): string
    {
        return 'feed-mill';
    }

    public function groupBy(): ?string
    {
        return 'formula_code';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('formula_code', 'A short code shared by every row of the formula.', true, 'FF-GROW-1'),
            new ImportColumn('formula_name', 'The formula name. Write it on the first row of the formula.', false, 'Grower ration 16% CP'),
            new ImportColumn('feed_type', 'The feed type it makes (code or name). Write it on the first row.', false, 'Grower'),
            new ImportColumn('process_loss_percent', 'Loss in milling, 0 to 50. First row only.', false, '1.5'),
            new ImportColumn('ingredient', 'A stock item used (code or name).', true, 'Maize'),
            new ImportColumn('inclusion_percent', 'Its share of the mix, above 0 up to 100 (4 decimals). The shares must total 100 before the formula can be activated.', true, '62.5'),
            ...collect(FeedFormula::NUTRITION)->map(fn ($label, $field) => new ImportColumn($field, "{$label}. First row only."))->values()->all(),
        ];
    }

    public function save(array $rows, ?User $actor): void
    {
        $first = $rows[0];
        $code = strtoupper($first['formula_code']);

        FeedFormula::where('code', $code)->exists() && throw $this->problem('formula_code', "{$code} is already a formula. Existing formulas are not overwritten; use a new code");

        $type = $this->find(FeedType::class, $first, 'feed_type', required: true, label: 'feed type');
        $name = $this->need($first, 'formula_name');

        $items = array_map(function (array $row) {
            $item = $this->find(InventoryItem::class, $row, 'ingredient', required: true, label: 'stock item');

            return ['inventory_item_id' => $item->id, 'inclusion_percent' => $this->decimal($row, 'inclusion_percent', 4, true)];
        }, $rows);

        ($this->save)([
            'code' => $code, 'name' => $name, 'feed_type_id' => $type->id,
            'process_loss_percent' => $this->decimal($first, 'process_loss_percent', 2) ?? '0',
            'items' => $items,
        ] + collect(FeedFormula::NUTRITION)->map(fn ($label, $field) => $this->decimal($first, $field, 2))->all(), null, $actor);
    }
}
