<?php
declare(strict_types=1);

namespace Learn;

use PDO;

final class ItemRepository
{
    public static function insertIfNew(PDO $pdo, array $item): array
    {
        Validate::itemSource((string) $item['source']);
        $stmt = $pdo->prepare('SELECT * FROM items WHERE url = :url');
        $stmt->execute([':url' => $item['url']]);
        $existing = $stmt->fetch();
        if ($existing) {
            return ['action' => 'exists', 'item' => $existing];
        }

        $ins = $pdo->prepare(
            'INSERT INTO items (url, source, title, trend_signal, status, relevance, one_liner, brief)
             VALUES (:url, :source, :title, :trend_signal, :status, :relevance, :one_liner, :brief)'
        );
        $ins->execute([
            ':url' => $item['url'],
            ':source' => $item['source'],
            ':title' => $item['title'],
            ':trend_signal' => $item['trend_signal'] ?? null,
            ':status' => $item['status'] ?? 'triaged',
            ':relevance' => $item['relevance'] ?? null,
            ':one_liner' => $item['one_liner'] ?? null,
            ':brief' => $item['brief'] ?? null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $stmt->execute([':url' => $item['url']]);
        return ['action' => 'inserted', 'item' => $stmt->fetch()];
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listGrouped(PDO $pdo): array
    {
        $order = "CASE relevance
            WHEN 'touches-my-work' THEN 1
            WHEN 'selected' THEN 2
            WHEN 'adjacent' THEN 3
            WHEN 'no' THEN 4
            ELSE 5 END, datetime(seen_at) DESC";
        $rows = $pdo->query('SELECT * FROM items ORDER BY ' . $order)->fetchAll();
        $groups = [
            'touches-my-work' => [],
            'selected' => [],
            'adjacent' => [],
            'no' => [],
            'untagged' => [],
        ];
        foreach ($rows as $row) {
            $key = $row['relevance'] ?? null;
            if ($key === null || $key === '') {
                $groups['untagged'][] = $row;
            } elseif (isset($groups[$key])) {
                $groups[$key][] = $row;
            } else {
                $groups['untagged'][] = $row;
            }
        }
        return $groups;
    }
}
