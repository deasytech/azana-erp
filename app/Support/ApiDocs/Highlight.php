<?php

namespace App\Support\ApiDocs;

/** Turns JSON and curl commands into the coloured HTML of the reference page (and the plain text behind each Copy button). */
final class Highlight
{
    /** Example values for the path and query placeholders in a curl command. */
    private const SAMPLES = ['code' => 'SOW-000123', 'client_id' => '6c9e2a42-9f0e-4d4f-b0c1-0a1f6a6f2c11', 'device_id' => 'pixel-7-3f9a', 'version' => 'a41f09c7d2e8b365', 'type' => 'record_weight'];

    /** JSON laid out for reading: two-space indent, and short lists and objects of plain values kept on one line. */
    public static function jsonText(mixed $value, int $depth = 0): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (! is_array($value)) {
            return (string) json_encode($value, $flags);
        }

        if ($value === []) {
            return array_is_list($value) ? '[]' : '{}';
        }

        $list = array_is_list($value);
        $scalars = ! array_filter($value, 'is_array');
        $inline = $list
            ? '['.implode(', ', array_map(fn ($v) => json_encode($v, $flags), $value)).']'
            : '{ '.implode(', ', array_map(fn ($v, $k) => json_encode((string) $k, $flags).': '.json_encode($v, $flags), $value, array_keys($value))).' }';

        if ($scalars && strlen($inline) <= 72) {
            return $inline;
        }

        $pad = str_repeat('  ', $depth + 1);
        $items = array_map(fn ($v, $k) => $pad.($list ? '' : json_encode((string) $k, $flags).': ').self::jsonText($v, $depth + 1), $value, array_keys($value));

        return ($list ? '[' : '{')."\n".implode(",\n", $items)."\n".str_repeat('  ', $depth).($list ? ']' : '}');
    }

    public static function json(mixed $value): string
    {
        return preg_replace_callback('/("(?:\\\\.|[^"\\\\])*")(\s*:)?|\b(true|false)\b|\bnull\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/', function ($m) {
            $token = htmlspecialchars($m[0], ENT_NOQUOTES);

            return match (true) {
                isset($m[1]) && $m[1] !== '' && isset($m[2]) && $m[2] !== '' => '<span class="tok-key">'.htmlspecialchars($m[1], ENT_NOQUOTES).'</span>'.$m[2],
                isset($m[1]) && $m[1] !== '' => '<span class="tok-str">'.$token.'</span>',
                isset($m[3]) && $m[3] !== '' => '<span class="tok-bool">'.$token.'</span>',
                $m[0] === 'null' => '<span class="tok-null">null</span>',
                default => '<span class="tok-num">'.$token.'</span>',
            };
        }, self::jsonText($value));
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
                isset($m[1]) && $m[1][0] !== '' => '<span class="tok-flag">-X</span> <span class="tok-verb">'.htmlspecialchars($m[1][0], ENT_NOQUOTES).'</span>',
                in_array($token, ['curl', '-H', '-d'], true) => '<span class="tok-flag">'.$token.'</span>',
                default => '<span class="tok-str">'.htmlspecialchars($token, ENT_NOQUOTES).'</span>',
            };
            $at = $offset + strlen($token);
        }

        return $html.htmlspecialchars(substr($text, $at), ENT_NOQUOTES);
    }
}
