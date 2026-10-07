<?php

namespace App\Domain\Traceability;

/**
 * The nodes and links found while tracing a product, grouped into stages from the farm's inputs to the customer.
 * A thing met twice (a supplier behind two feed batches) appears once. The domain describes what things are by type
 * and id; the screens decide how to link them.
 */
class TraceGraph
{
    /** Display order of the stages, from where things start to where they end up. */
    private const ORDER = ['Suppliers', 'Raw materials', 'Feed', 'Genetics', 'Litter', 'Pig', 'Housing', 'Slaughter', 'Meat', 'Semen', 'Sales', 'Customers'];

    /** @var array<string, array<string, array{key: string, type: string, id: int, label: string, detail: ?string}>> */
    private array $stages = [];

    /** @var array<string, array{from: string, to: string, label: ?string}> */
    private array $edges = [];

    /** Adds (or finds) a node and returns its key, like "animal:12". */
    public function node(string $stage, string $type, int $id, string $label, ?string $detail = null): string
    {
        $key = "{$type}:{$id}";
        $this->stages[$stage][$key] ??= ['key' => $key, 'type' => $type, 'id' => $id, 'label' => $label, 'detail' => $detail];

        return $key;
    }

    /** Records that $from led to $to (a supplier to a raw material batch, a litter to a pig). */
    public function edge(string $from, string $to, ?string $label = null): void
    {
        $this->edges["{$from}>{$to}"] ??= ['from' => $from, 'to' => $to, 'label' => $label];
    }

    /** @return list<array{title: string, nodes: list<array{key: string, type: string, id: int, label: string, detail: ?string}>}> */
    public function stages(): array
    {
        $titles = array_keys($this->stages);
        $position = fn (string $title) => ($at = array_search($title, self::ORDER, true)) === false ? count(self::ORDER) : $at;
        usort($titles, fn (string $a, string $b) => $position($a) <=> $position($b));

        return array_map(fn (string $title) => ['title' => $title, 'nodes' => array_values($this->stages[$title])], $titles);
    }

    /** @return list<array{from: string, to: string, label: ?string}> */
    public function edges(): array
    {
        return array_values($this->edges);
    }

    /** @return list<string> keys of every node in the graph */
    public function keys(): array
    {
        return array_merge(...array_map(fn (array $nodes) => array_keys($nodes), array_values($this->stages)) ?: [[]]);
    }

    /** Keys of the nodes that lead (directly or not) to $key. */
    public function ancestors(string $key): array
    {
        $found = [];
        $queue = [$key];

        while ($queue) {
            $current = array_shift($queue);

            foreach ($this->edges as $edge) {
                if ($edge['to'] === $current && ! in_array($edge['from'], $found, true)) {
                    $found[] = $edge['from'];
                    $queue[] = $edge['from'];
                }
            }
        }

        return $found;
    }
}
