<?php
declare(strict_types=1);

namespace Learn;

use PDO;

final class TopicRepository
{
    public static function add(PDO $pdo, string $topic, string $addedBy, ?string $sourceNote = null): array
    {
        Validate::addedBy($addedBy);
        $stmt = $pdo->prepare(
            'INSERT INTO topics (topic, added_by, status, source_note)
             VALUES (:topic, :added_by, :status, :source_note)'
        );
        $stmt->execute([
            ':topic' => trim($topic),
            ':added_by' => $addedBy,
            ':status' => 'pending',
            ':source_note' => $sourceNote,
        ]);
        $id = (int) $pdo->lastInsertId();
        return self::find($pdo, $id) ?? [];
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM topics WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function claimNext(PDO $pdo): ?array
    {
        $pdo->beginTransaction();
        try {
            $inProgress = $pdo->query(
                "SELECT * FROM topics WHERE status = 'in-progress' ORDER BY datetime(added_at) ASC LIMIT 1"
            )->fetch();
            if ($inProgress) {
                $pdo->commit();
                return $inProgress;
            }

            $pending = $pdo->query(
                "SELECT * FROM topics WHERE status = 'pending' ORDER BY datetime(added_at) ASC LIMIT 1"
            )->fetch();
            if (!$pending) {
                $pdo->commit();
                return null;
            }

            $upd = $pdo->prepare("UPDATE topics SET status = 'in-progress' WHERE id = :id AND status = 'pending'");
            $upd->execute([':id' => $pending['id']]);
            if ($upd->rowCount() === 0) {
                $pdo->commit();
                return self::claimNext($pdo);
            }
            $pdo->commit();
            return self::find($pdo, (int) $pending['id']);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function markDone(PDO $pdo, int $id, string $lessonFile): void
    {
        $stmt = $pdo->prepare(
            "UPDATE topics
             SET status = 'done', lesson_file = :lesson_file, completed_at = datetime('now')
             WHERE id = :id AND status IN ('in-progress', 'pending')"
        );
        $stmt->execute([
            ':id' => $id,
            ':lesson_file' => $lessonFile,
        ]);
    }

    public static function listByStatus(PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT * FROM topics ORDER BY
              CASE status WHEN 'pending' THEN 1 WHEN 'in-progress' THEN 2 WHEN 'done' THEN 3 ELSE 4 END,
              datetime(added_at) DESC"
        )->fetchAll();
        $out = ['pending' => [], 'in-progress' => [], 'done' => []];
        foreach ($rows as $row) {
            $status = $row['status'];
            if (!isset($out[$status])) {
                $out[$status] = [];
            }
            $out[$status][] = $row;
        }
        return $out;
    }
}
