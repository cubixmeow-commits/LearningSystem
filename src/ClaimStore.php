<?php
declare(strict_types=1);

namespace Learn;

use PDO;

final class ClaimStore
{
    public static function upsert(PDO $pdo, array $claim): array
    {
        Validate::claim($claim);

        $normalized = learn_normalize_claim_text((string) $claim['claim']);
        $sourceUrl = (string) $claim['source_url'];
        $itemId = array_key_exists('item_id', $claim) ? $claim['item_id'] : null;
        $lessonFile = array_key_exists('lesson_file', $claim) ? $claim['lesson_file'] : null;

        if ($itemId !== null && $itemId !== '') {
            $itemId = (int) $itemId;
        } else {
            $itemId = null;
        }
        if ($lessonFile !== null && $lessonFile !== '') {
            $lessonFile = (string) $lessonFile;
        } else {
            $lessonFile = null;
        }

        $existing = self::findMatch($pdo, $sourceUrl, $normalized, $itemId, $lessonFile);

        $fields = [
            'item_id' => $itemId,
            'lesson_file' => $lessonFile,
            'category' => (string) $claim['category'],
            'claim' => trim((string) $claim['claim']),
            'evidence' => isset($claim['evidence']) ? (string) $claim['evidence'] : null,
            'source_url' => $sourceUrl,
            'source_date' => isset($claim['source_date']) ? (string) $claim['source_date'] : null,
            'confidence' => (string) $claim['confidence'],
            'claim_type' => (string) $claim['claim_type'],
            'status' => isset($claim['status']) ? (string) $claim['status'] : 'unreviewed',
            'relevance_to_me' => isset($claim['relevance_to_me']) ? (string) $claim['relevance_to_me'] : null,
        ];

        if ($existing) {
            $sql = 'UPDATE claims SET
                item_id = :item_id,
                lesson_file = :lesson_file,
                category = :category,
                claim = :claim,
                evidence = :evidence,
                source_url = :source_url,
                source_date = :source_date,
                confidence = :confidence,
                claim_type = :claim_type,
                status = :status,
                relevance_to_me = :relevance_to_me
              WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $fields['id'] = (int) $existing['id'];
            $stmt->execute($fields);
            return ['action' => 'updated', 'id' => (int) $existing['id']];
        }

        $sql = 'INSERT INTO claims (
            item_id, lesson_file, category, claim, evidence, source_url, source_date,
            confidence, claim_type, status, relevance_to_me
          ) VALUES (
            :item_id, :lesson_file, :category, :claim, :evidence, :source_url, :source_date,
            :confidence, :claim_type, :status, :relevance_to_me
          )';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($fields);
        return ['action' => 'inserted', 'id' => (int) $pdo->lastInsertId()];
    }

    private static function findMatch(
        PDO $pdo,
        string $sourceUrl,
        string $normalizedClaim,
        ?int $itemId,
        ?string $lessonFile
    ): ?array {
        $sql = 'SELECT * FROM claims WHERE source_url = :source_url';
        $params = [':source_url' => $sourceUrl];

        if ($itemId === null) {
            $sql .= ' AND item_id IS NULL';
        } else {
            $sql .= ' AND item_id = :item_id';
            $params[':item_id'] = $itemId;
        }

        if ($lessonFile === null) {
            $sql .= ' AND lesson_file IS NULL';
        } else {
            $sql .= ' AND lesson_file = :lesson_file';
            $params[':lesson_file'] = $lessonFile;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            if (learn_normalize_claim_text((string) $row['claim']) === $normalizedClaim) {
                return $row;
            }
        }
        return null;
    }
}
