<?php
declare(strict_types=1);

namespace Learn;

final class Validate
{
    private const CONFIDENCE = ['high', 'medium', 'low'];
    private const CLAIM_TYPES = ['fact', 'inference', 'synthesis'];
    private const RELEVANCE = ['touches-my-work', 'adjacent', 'no', 'selected'];
    private const ITEM_STATUS = ['triaged', 'briefed', 'archived'];
    private const CLAIM_STATUS = ['unreviewed', 'confirmed'];
    private const TOPIC_STATUS = ['pending', 'in-progress', 'done'];
    private const ADDED_BY = ['me', 'gap-suggestion', 'trending'];
    private const SOURCES = ['github', 'hn', 'manual'];

    public static function claim(array $claim): void
    {
        self::requireKeys($claim, ['category', 'claim', 'source_url', 'confidence', 'claim_type']);
        if (trim((string) $claim['source_url']) === '') {
            throw new \InvalidArgumentException('source_url is required');
        }
        if (trim((string) $claim['claim']) === '') {
            throw new \InvalidArgumentException('claim text is required');
        }
        self::oneOf('confidence', (string) $claim['confidence'], self::CONFIDENCE);
        self::oneOf('claim_type', (string) $claim['claim_type'], self::CLAIM_TYPES);
        if (isset($claim['status'])) {
            self::oneOf('status', (string) $claim['status'], self::CLAIM_STATUS);
        }
    }

    public static function triage(array $row): void
    {
        self::requireKeys($row, ['id', 'one_liner', 'relevance']);
        self::oneOf('relevance', (string) $row['relevance'], self::RELEVANCE);
        if (trim((string) $row['one_liner']) === '') {
            throw new \InvalidArgumentException('one_liner is required');
        }
    }

    public static function brief(array $brief): void
    {
        $required = [
            'WHAT IT IS',
            'THE PROBLEM',
            'WHY NOW',
            'HOW IT WORKS',
            'WHO\'S USING IT',
            'TAKEAWAY FOR ME',
        ];
        foreach ($required as $key) {
            if (!isset($brief[$key]) || trim((string) $brief[$key]) === '') {
                throw new \InvalidArgumentException('Brief missing field: ' . $key);
            }
        }
    }

    public static function itemSource(string $source): void
    {
        self::oneOf('source', $source, self::SOURCES);
    }

    public static function itemStatus(string $status): void
    {
        self::oneOf('status', $status, self::ITEM_STATUS);
    }

    public static function topicStatus(string $status): void
    {
        self::oneOf('status', $status, self::TOPIC_STATUS);
    }

    public static function addedBy(string $addedBy): void
    {
        self::oneOf('added_by', $addedBy, self::ADDED_BY);
    }

    private static function requireKeys(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException('Missing required field: ' . $key);
            }
        }
    }

    private static function oneOf(string $label, string $value, array $allowed): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException(
                $label . ' must be one of: ' . implode(', ', $allowed)
            );
        }
    }
}
