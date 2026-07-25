<?php
declare(strict_types=1);

namespace Learn;

use PDO;

/**
 * Store-as-lens helpers for curriculum building and agent grounding.
 */
final class StoreLens
{
    public static function claimsForTopic(PDO $pdo, string $topic, int $limit = 40): array
    {
        $tokens = self::tokens($topic);
        if ($tokens === []) {
            return [];
        }

        $clauses = [];
        $params = [];
        foreach ($tokens as $i => $token) {
            $key = ':t' . $i;
            $clauses[] = "(category LIKE $key ESCAPE '\\' OR claim LIKE $key ESCAPE '\\' OR relevance_to_me LIKE $key ESCAPE '\\' OR evidence LIKE $key ESCAPE '\\')";
            $params[$key] = '%' . $token . '%';
        }

        $sql = 'SELECT * FROM claims WHERE ' . implode(' OR ', $clauses) . '
             ORDER BY
               CASE confidence WHEN \'high\' THEN 1 WHEN \'medium\' THEN 2 ELSE 3 END,
               CASE status WHEN \'confirmed\' THEN 1 ELSE 2 END,
               datetime(seen_at) DESC
             LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $topic): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', mb_strtolower($topic, 'UTF-8')) ?: [];
        $stop = [
            'a' => true, 'an' => true, 'and' => true, 'for' => true, 'from' => true,
            'in' => true, 'of' => true, 'on' => true, 'or' => true, 'the' => true,
            'to' => true, 'with' => true, 'my' => true, 'me' => true,
        ];
        $out = [];
        foreach ($parts as $part) {
            $part = str_replace(['%', '_'], ['\\%', '\\_'], $part);
            if (strlen($part) < 3 || isset($stop[$part])) {
                continue;
            }
            $out[$part] = $part;
        }
        return array_values($out);
    }

    public static function byCategory(PDO $pdo, string $category, ?string $minConfidence = null): array
    {
        $sql = 'SELECT * FROM claims WHERE category = :category';
        $params = [':category' => $category];
        if ($minConfidence === 'high') {
            $sql .= " AND confidence = 'high'";
        } elseif ($minConfidence === 'medium') {
            $sql .= " AND confidence IN ('high', 'medium')";
        }
        $sql .= ' ORDER BY datetime(seen_at) DESC LIMIT 50';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byRelevance(PDO $pdo, string $relevanceToMe): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM claims WHERE relevance_to_me = :r ORDER BY datetime(seen_at) DESC LIMIT 50'
        );
        $stmt->execute([':r' => $relevanceToMe]);
        return $stmt->fetchAll();
    }

    public static function byConfidence(PDO $pdo, string $confidence): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM claims WHERE confidence = :c ORDER BY datetime(seen_at) DESC LIMIT 50'
        );
        $stmt->execute([':c' => $confidence]);
        return $stmt->fetchAll();
    }

    public static function byStatus(PDO $pdo, string $status): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM claims WHERE status = :s ORDER BY datetime(seen_at) DESC LIMIT 50'
        );
        $stmt->execute([':s' => $status]);
        return $stmt->fetchAll();
    }
}
