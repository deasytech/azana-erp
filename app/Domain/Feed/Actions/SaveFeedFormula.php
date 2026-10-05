<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates a feed formula (version 1), or replaces the contents of a draft. Active and retired formulas never
 * change; a new version is made instead (CreateFormulaVersion). A draft may be saved while its ingredients do
 * not yet add up to 100%; it cannot be activated until they do.
 *
 * $data keys: code, name, feed_type_id, process_loss_percent, notes, the FeedFormula::NUTRITION fields, and
 * items: a list of inventory_item_id, inclusion_percent, notes.
 */
class SaveFeedFormula
{
    private const DECIMAL = '/^\d{1,3}(\.\d{1,4})?$/';

    /** @param array<string, mixed> $data */
    public function __invoke(array $data, ?FeedFormula $formula = null, ?User $actor = null): FeedFormula
    {
        $items = $this->validate($data, $formula);

        return DB::transaction(function () use ($data, $formula, $items, $actor) {
            $fields = [
                'name' => trim($data['name']),
                'feed_type_id' => (int) $data['feed_type_id'],
                'process_loss_percent' => $data['process_loss_percent'] ?? '0',
                'notes' => $data['notes'] ?? null,
            ] + collect(FeedFormula::NUTRITION)->map(fn ($label, $field) => filled($data[$field] ?? null) ? (string) $data[$field] : null)->all();

            if ($formula) {
                $formula = FeedFormula::lockForUpdate()->findOrFail($formula->id);
                $formula->isDraft() || throw new DomainException('Only a draft formula can be changed; make a new version instead.', 'formula_not_draft');
                $formula->update($fields);
                $formula->items()->delete();
            } else {
                $formula = FeedFormula::create($fields + ['code' => $data['code'], 'created_by' => ($actor ?? Auth::user())?->getKey()]);
            }

            foreach ($items as $item) {
                $formula->items()->create($item);
            }

            return $formula->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function validate(array $data, ?FeedFormula $formula): array
    {
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new DomainException('Give the formula a name.', 'formula_name');
        }

        if (! $formula && trim((string) ($data['code'] ?? '')) === '') {
            throw new DomainException('Give the formula a code.', 'formula_code');
        }

        if (! $formula && FeedFormula::where('code', strtoupper(trim($data['code'])))->exists()) {
            throw new DomainException('That formula code is already used; make a new version of the existing formula instead.', 'formula_code_taken');
        }

        if (! FeedType::where('is_active', true)->whereKey($data['feed_type_id'] ?? 0)->exists()) {
            throw new DomainException('Choose an active feed type.', 'invalid_feed_type');
        }

        $loss = (string) ($data['process_loss_percent'] ?? '0');

        if (! preg_match('/^\d{1,2}(\.\d{1,2})?$/', $loss) || bccomp($loss, '50', 2) > 0) {
            throw new DomainException('The process loss must be between 0 and 50 percent.', 'process_loss');
        }

        foreach (FeedFormula::NUTRITION as $field => $label) {
            $value = $data[$field] ?? null;

            if (filled($value) && (! preg_match('/^\d{1,5}(\.\d{1,2})?$/', (string) $value) || ($field !== 'energy_kcal_per_kg' && bccomp((string) $value, '100', 2) > 0))) {
                throw new DomainException("{$label} must be a number from 0 ".($field === 'energy_kcal_per_kg' ? 'upwards' : 'to 100').' with at most 2 decimals.', 'nutrition_value');
            }
        }

        return $this->validateItems($data['items'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function validateItems(array $items): array
    {
        if ($items === []) {
            throw new DomainException('Add at least one ingredient.', 'formula_items');
        }

        $seen = [];

        return array_map(function (array $item) use (&$seen) {
            $itemId = (int) ($item['inventory_item_id'] ?? 0);
            $percent = trim((string) ($item['inclusion_percent'] ?? ''));

            if (! InventoryItem::where('is_active', true)->whereKey($itemId)->exists()) {
                throw new DomainException('Choose an active stock item for every ingredient.', 'invalid_item');
            }

            if (isset($seen[$itemId])) {
                throw new DomainException('Each ingredient may appear only once.', 'duplicate_item');
            }

            $seen[$itemId] = true;

            if (! preg_match(self::DECIMAL, $percent) || bccomp($percent, '0', 4) <= 0 || bccomp($percent, '100', 4) > 0) {
                throw new DomainException('Each inclusion must be above 0 and at most 100 percent, with at most 4 decimals.', 'inclusion_percent');
            }

            return ['inventory_item_id' => $itemId, 'inclusion_percent' => $percent, 'notes' => $item['notes'] ?? null];
        }, array_values($items));
    }
}
