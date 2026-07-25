<?php
declare(strict_types=1);

function learn_json_out(mixed $data, int $code = 0): never
{
    fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($code);
}

function learn_fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function learn_read_json_file(string $path): array
{
    if ($path === '-' ) {
        $raw = stream_get_contents(STDIN);
    } else {
        if (!is_file($path)) {
            learn_fail('JSON file not found: ' . $path);
        }
        $raw = file_get_contents($path);
    }
    if ($raw === false || trim($raw) === '') {
        learn_fail('Empty JSON input');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        learn_fail('Invalid JSON: ' . json_last_error_msg());
    }
    return $data;
}

function learn_normalize_claim_text(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return mb_strtolower($text, 'UTF-8');
}

function learn_slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? $text;
    return trim($text, '-') ?: 'topic';
}

function learn_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
