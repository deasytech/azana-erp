<?php

namespace App\Support\ApiDocs;

/** Turns JSON and curl commands into the coloured HTML of the reference page (and the plain text behind each Copy button). */
final class Highlight
{
    /** Example values for the path and query placeholders in a curl command. */
    private const SAMPLES = ['code' => 'SOW-000123', 'client_id' => '6c9e2a42-9f0e-4d4f-b0c1-0a1f6a6f2c11', 'device_id' => 'pixel-7-3f9a', 'version' => 'a41f09c7d2e8b365', 'type' => 'record_weight'];

    private const END = '</span>';

    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** JSON laid out for reading, as plain text. */
    public static function jsonText(mixed $value): string
    {
        return self::layout($value, fn (string $kind, string $text) => $text, 0);
    }

    /** The same layout as HTML, each key, string, number and constant in its own colour. */
    public static function json(mixed $value): string
    {
        return self::layout($value, fn (string $kind, string $text) => '<span class="tok-'.$kind.'">'.htmlspecialchars($text, ENT_NOQUOTES).self::END, 0);
    }

    /**
     * Two-space indent, with short lists and objects of plain values kept on one line. $paint(kind, text) dresses each token (kind is
     * key, str, num, bool or null).
     */
    private static function layout(mixed $value, callable $paint, int $depth): string
    {
        return is_array($value) ? self::container($value, $paint, $depth) : self::scalar($value, $paint);
    }

    private static function scalar(mixed $value, callable $paint): string
    {
        $kind = match (true) {
            is_string($value) => 'str', is_bool($value) => 'bool', $value === null => 'null', default => 'num',
        };

        return $paint($kind, (string) json_encode($value, self::FLAGS));
    }

    private static function container(array $value, callable $paint, int $depth): string
    {
        $list = array_is_list($value);
        $open = $list ? '[' : '{';
        $close = $list ? ']' : '}';
        $item = fn (mixed $v, string|int $k, int $level) => ($list ? '' : $paint('key', (string) json_encode((string) $k, self::FLAGS)).': ').self::layout($v, $paint, $level);
        $inline = array_map(fn ($v, $k) => $item($v, $k, $depth + 1), $value, array_keys($value));
        $plain = ! array_filter($value, 'is_array') && strlen(strip_tags(implode(', ', $inline))) <= 72;
        $pad = str_repeat('  ', $depth + 1);

        return match (true) {
            $value === [] => $open.$close,
            $plain && $list => '['.implode(', ', $inline).']',
            $plain => '{ '.implode(', ', $inline).' }',
            default => $open."\n".$pad.implode(",\n".$pad, $inline)."\n".str_repeat('  ', $depth).$close,
        };
    }

    /** The curl command for an endpoint, as plain text. */
    public static function curlText(array $endpoint, string $base): string
    {
        $path = $endpoint['path'];
        $path = preg_replace_callback('/\{(\w+)\}/', fn ($m) => self::SAMPLES[$m[1]] ?? $m[1], $path);
        $query = collect($endpoint['params']['rows'] ?? [])->filter(fn ($r) => ($endpoint['params']['title'] ?? '') === 'Query' && $r[2])->map(fn ($r) => $r[0].'='.(self::SAMPLES[$r[0]] ?? 'value'))->implode('&');
        $method = $endpoint['method'];
        $lines = ['curl'.($method === 'GET' ? '' : " -X {$method}")." {$base}{$path}".($query ? "?{$query}" : '')];

        if ($endpoint['auth'] ?? true) {
            $lines[] = '  -H "Authorization: Bearer $TOKEN"';
        }

        if (isset($endpoint['body'])) {
            $lines[] = '  -H "Content-Type: application/json"';
            $lines[] = "  -d '".json_encode($endpoint['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."'";
        }

        return implode(" \\\n", $lines);
    }

    public static function curl(string $text): string
    {
        // Colour the tokens and escape everything, in one pass over the plain text, so nothing we add is mistaken for text to colour.
        preg_match_all('/\'[^\']*\'|"[^"]*"|^curl\b|-X (\w+)|(?<=\s)-[Hd](?=\s)/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $html = '';
        $at = 0;

        foreach ($matches as $m) {
            [$token, $offset] = $m[0];
            $html .= htmlspecialchars(substr($text, $at, $offset - $at), ENT_NOQUOTES);
            $html .= match (true) {
                isset($m[1]) && $m[1][0] !== '' => '<span class="tok-flag">-X</span> <span class="tok-verb">'.htmlspecialchars($m[1][0], ENT_NOQUOTES).self::END,
                in_array($token, ['curl', '-H', '-d'], true) => '<span class="tok-flag">'.$token.self::END,
                default => '<span class="tok-str">'.htmlspecialchars($token, ENT_NOQUOTES).self::END,
            };
            $at = $offset + strlen($token);
        }

        return $html.htmlspecialchars(substr($text, $at), ENT_NOQUOTES);
    }
}
