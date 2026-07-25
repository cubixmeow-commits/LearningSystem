<?php
declare(strict_types=1);

namespace Learn;

use Learn\Fetch\GitHub;
use Learn\Fetch\HackerNews;

final class App
{
    public static function run(array $argv): void
    {
        $cmd = $argv[1] ?? null;
        if ($cmd === null || $cmd === 'help' || $cmd === '--help') {
            self::help();
            return;
        }

        $pdo = Db::pdo();

        try {
            match ($cmd) {
                'fetch' => self::fetch($pdo),
                'next-items' => self::nextItems($pdo),
                'save-triage' => self::saveTriage($pdo, $argv[2] ?? null),
                'next-brief' => self::nextBrief($pdo, isset($argv[2]) ? (int) $argv[2] : null),
                'save-brief' => self::saveBrief($pdo, $argv[2] ?? null),
                'add-repo' => self::addRepo($pdo, $argv[2] ?? null),
                'next-topic' => self::nextTopic($pdo),
                'save-lesson' => self::saveLesson($pdo, $argv[2] ?? null),
                'add-topic' => self::addTopic($pdo, $argv),
                'store-lens' => self::storeLens($pdo, $argv[2] ?? null),
                'claims-query' => self::claimsQuery($pdo, $argv),
                default => learn_fail('Unknown command: ' . $cmd),
            };
        } catch (\Throwable $e) {
            learn_fail($e->getMessage());
        }
    }

    private static function help(): void
    {
        $text = <<<TXT
Learn System CLI

  php learn.php fetch
  php learn.php next-items
  php learn.php save-triage in.json
  php learn.php next-brief [item_id]
  php learn.php save-brief in.json
  php learn.php add-repo <github-url>
  php learn.php add-topic "topic text" [--note "why"] [--by me|gap-suggestion|trending]
  php learn.php next-topic
  php learn.php save-lesson in.json
  php learn.php store-lens "topic"
  php learn.php claims-query --category X [--confidence high] [--status confirmed] [--relevance general]
TXT;
        fwrite(STDOUT, $text . PHP_EOL);
    }

    private static function fetch(\PDO $pdo): void
    {
        $candidates = array_merge(
            GitHub::rising(30, 20),
            HackerNews::frontPage(20),
            HackerNews::recentByPoints(100, 10)
        );

        $inserted = 0;
        $existing = 0;
        $urls = [];
        foreach ($candidates as $item) {
            if (isset($urls[$item['url']])) {
                continue;
            }
            $urls[$item['url']] = true;
            $result = ItemRepository::insertIfNew($pdo, [
                'url' => $item['url'],
                'source' => $item['source'],
                'title' => $item['title'],
                'trend_signal' => $item['trend_signal'],
                'status' => 'triaged',
            ]);
            if ($result['action'] === 'inserted') {
                $inserted++;
            } else {
                $existing++;
            }
        }

        learn_json_out([
            'ok' => true,
            'inserted' => $inserted,
            'existing' => $existing,
            'scanned' => count($urls),
        ]);
    }

    private static function nextItems(\PDO $pdo): void
    {
        $stmt = $pdo->query(
            "SELECT id, url, source, title, trend_signal, seen_at
             FROM items
             WHERE one_liner IS NULL
             ORDER BY datetime(seen_at) DESC
             LIMIT 40"
        );
        learn_json_out([
            'ok' => true,
            'items' => $stmt->fetchAll(),
            'hint' => 'Write one_liner + relevance for each, then save-triage',
        ]);
    }

    private static function saveTriage(\PDO $pdo, ?string $path): void
    {
        if ($path === null) {
            learn_fail('Usage: php learn.php save-triage in.json');
        }
        $payload = learn_read_json_file($path);
        $rows = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [$payload];

        $results = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            Validate::triage($row);
            $id = (int) $row['id'];
            $item = ItemRepository::find($pdo, $id);
            if (!$item) {
                throw new \InvalidArgumentException('Unknown item id: ' . $id);
            }
            // Never overwrite completed briefed rows with triage churn.
            if ($item['status'] === 'briefed' && $item['one_liner'] !== null) {
                $results[] = ['id' => $id, 'action' => 'skipped_briefed'];
                continue;
            }
            $stmt = $pdo->prepare(
                'UPDATE items SET one_liner = :one_liner, relevance = :relevance
                 WHERE id = :id AND one_liner IS NULL'
            );
            $stmt->execute([
                ':one_liner' => trim((string) $row['one_liner']),
                ':relevance' => (string) $row['relevance'],
                ':id' => $id,
            ]);
            if ($stmt->rowCount() === 0) {
                // Idempotent update of same natural key (already triaged).
                $upd = $pdo->prepare(
                    'UPDATE items SET one_liner = :one_liner, relevance = :relevance WHERE id = :id'
                );
                $upd->execute([
                    ':one_liner' => trim((string) $row['one_liner']),
                    ':relevance' => (string) $row['relevance'],
                    ':id' => $id,
                ]);
                $results[] = ['id' => $id, 'action' => 'updated'];
            } else {
                $results[] = ['id' => $id, 'action' => 'triaged'];
            }
        }

        learn_json_out(['ok' => true, 'results' => $results]);
    }

    private static function nextBrief(\PDO $pdo, ?int $itemId): void
    {
        if ($itemId) {
            $item = ItemRepository::find($pdo, $itemId);
            if (!$item) {
                learn_fail('Unknown item id: ' . $itemId);
            }
            learn_json_out(['ok' => true, 'item' => $item]);
        }

        $stmt = $pdo->query(
            "SELECT * FROM items
             WHERE status = 'triaged'
               AND brief IS NULL
               AND (relevance IS NULL OR relevance != 'no')
             ORDER BY
               CASE relevance
                 WHEN 'touches-my-work' THEN 1
                 WHEN 'selected' THEN 2
                 WHEN 'adjacent' THEN 3
                 ELSE 4 END,
               datetime(seen_at) DESC
             LIMIT 1"
        );
        $item = $stmt->fetch();
        learn_json_out([
            'ok' => true,
            'item' => $item ?: null,
            'hint' => $item
                ? 'Research this item, write the six-field brief + 3-6 claims, then save-brief'
                : 'No items awaiting a brief',
        ]);
    }

    private static function saveBrief(\PDO $pdo, ?string $path): void
    {
        if ($path === null) {
            learn_fail('Usage: php learn.php save-brief in.json');
        }
        $payload = learn_read_json_file($path);
        if (!isset($payload['item_id'], $payload['brief'], $payload['claims']) || !is_array($payload['claims'])) {
            throw new \InvalidArgumentException('save-brief requires item_id, brief, claims');
        }
        $itemId = (int) $payload['item_id'];
        $item = ItemRepository::find($pdo, $itemId);
        if (!$item) {
            throw new \InvalidArgumentException('Unknown item id: ' . $itemId);
        }

        $brief = $payload['brief'];
        if (!is_array($brief)) {
            throw new \InvalidArgumentException('brief must be an object');
        }
        Validate::brief($brief);

        $claimCount = count($payload['claims']);
        if ($claimCount < 3 || $claimCount > 6) {
            throw new \InvalidArgumentException('claims must contain 3 to 6 entries');
        }

        $pdo->beginTransaction();
        try {
            $briefJson = json_encode($brief, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $stmt = $pdo->prepare(
                "UPDATE items SET brief = :brief, status = 'briefed' WHERE id = :id"
            );
            $stmt->execute([':brief' => $briefJson, ':id' => $itemId]);

            $claimResults = [];
            foreach ($payload['claims'] as $claim) {
                if (!is_array($claim)) {
                    throw new \InvalidArgumentException('Each claim must be an object');
                }
                $claim['item_id'] = $itemId;
                if (!isset($claim['lesson_file'])) {
                    $claim['lesson_file'] = null;
                }
                $claimResults[] = ClaimStore::upsert($pdo, $claim);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        learn_json_out([
            'ok' => true,
            'item_id' => $itemId,
            'status' => 'briefed',
            'claims' => $claimResults,
        ]);
    }

    private static function addRepo(\PDO $pdo, ?string $url): void
    {
        if ($url === null || trim($url) === '') {
            learn_fail('Usage: php learn.php add-repo <github-url>');
        }
        $repo = GitHub::repoByUrl($url);
        $result = ItemRepository::insertIfNew($pdo, [
            'url' => $repo['url'],
            'source' => 'manual',
            'title' => $repo['title'],
            'trend_signal' => $repo['trend_signal'],
            'status' => 'triaged',
            'relevance' => 'selected',
            'one_liner' => 'Manually selected for inspection',
        ]);

        // If it already existed from trending, surface existing row; do not force relevance rewrite.
        if ($result['action'] === 'exists') {
            learn_json_out([
                'ok' => true,
                'action' => 'exists',
                'item' => $result['item'],
                'note' => 'Repo already in store; returning existing row without duplicating',
            ]);
        }

        learn_json_out([
            'ok' => true,
            'action' => 'inserted',
            'item' => $result['item'],
        ]);
    }

    private static function addTopic(\PDO $pdo, array $argv): void
    {
        $topic = $argv[2] ?? null;
        if ($topic === null || trim($topic) === '') {
            learn_fail('Usage: php learn.php add-topic "topic" [--note "..."] [--by me]');
        }
        $note = null;
        $by = 'me';
        for ($i = 3; $i < count($argv); $i++) {
            if ($argv[$i] === '--note' && isset($argv[$i + 1])) {
                $note = $argv[++$i];
            } elseif ($argv[$i] === '--by' && isset($argv[$i + 1])) {
                $by = $argv[++$i];
            }
        }
        $row = TopicRepository::add($pdo, $topic, $by, $note);
        learn_json_out(['ok' => true, 'topic' => $row]);
    }

    private static function nextTopic(\PDO $pdo): void
    {
        $topic = TopicRepository::claimNext($pdo);
        $lens = [];
        if ($topic) {
            $lens = StoreLens::claimsForTopic($pdo, (string) $topic['topic']);
        }
        learn_json_out([
            'ok' => true,
            'topic' => $topic,
            'store_lens' => $lens,
            'hint' => $topic
                ? 'Use store_lens, do live research, write lesson + claims, then save-lesson'
                : 'No pending or in-progress topics',
        ]);
    }

    private static function saveLesson(\PDO $pdo, ?string $path): void
    {
        if ($path === null) {
            learn_fail('Usage: php learn.php save-lesson in.json');
        }
        $payload = learn_read_json_file($path);
        foreach (['topic_id', 'lesson_markdown', 'claims'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new \InvalidArgumentException('save-lesson requires ' . $key);
            }
        }
        if (!is_array($payload['claims'])) {
            throw new \InvalidArgumentException('claims must be an array');
        }

        $topicId = (int) $payload['topic_id'];
        $topic = TopicRepository::find($pdo, $topicId);
        if (!$topic) {
            throw new \InvalidArgumentException('Unknown topic id: ' . $topicId);
        }
        if ($topic['status'] === 'done' && !empty($topic['lesson_file'])) {
            // Idempotent: re-save updates lesson file + upserts claims, does not fork a second done topic.
        } elseif ($topic['status'] === 'pending') {
            throw new \InvalidArgumentException('Topic is still pending; run next-topic first');
        }

        $slug = isset($payload['slug']) ? learn_slugify((string) $payload['slug']) : learn_slugify((string) $topic['topic']);
        $month = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m');
        $relative = 'lessons/' . $slug . '-' . $month . '.md';
        if (!empty($payload['lesson_file'])) {
            $relative = ltrim((string) $payload['lesson_file'], '/');
            if (!str_starts_with($relative, 'lessons/')) {
                $relative = 'lessons/' . basename($relative);
            }
        }

        $absolute = LEARN_ROOT . '/' . $relative;
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo->beginTransaction();
        try {
            if (file_put_contents($absolute, (string) $payload['lesson_markdown']) === false) {
                throw new \RuntimeException('Unable to write lesson file');
            }

            TopicRepository::markDone($pdo, $topicId, $relative);

            $claimResults = [];
            foreach ($payload['claims'] as $claim) {
                if (!is_array($claim)) {
                    throw new \InvalidArgumentException('Each claim must be an object');
                }
                $claim['lesson_file'] = $relative;
                if (!array_key_exists('item_id', $claim)) {
                    $claim['item_id'] = null;
                }
                $claimResults[] = ClaimStore::upsert($pdo, $claim);
            }

            $gapTopics = [];
            if (!empty($payload['gap_topics']) && is_array($payload['gap_topics'])) {
                foreach ($payload['gap_topics'] as $gap) {
                    if (is_string($gap)) {
                        $gapTopics[] = TopicRepository::add(
                            $pdo,
                            $gap,
                            'gap-suggestion',
                            'From lesson ' . $relative
                        );
                    } elseif (is_array($gap) && isset($gap['topic'])) {
                        $gapTopics[] = TopicRepository::add(
                            $pdo,
                            (string) $gap['topic'],
                            'gap-suggestion',
                            isset($gap['source_note']) ? (string) $gap['source_note'] : ('From lesson ' . $relative)
                        );
                    }
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        learn_json_out([
            'ok' => true,
            'topic_id' => $topicId,
            'lesson_file' => $relative,
            'claims' => $claimResults,
            'gap_topics' => $gapTopics,
        ]);
    }

    private static function storeLens(\PDO $pdo, ?string $topic): void
    {
        if ($topic === null || trim($topic) === '') {
            learn_fail('Usage: php learn.php store-lens "topic"');
        }
        learn_json_out([
            'ok' => true,
            'topic' => $topic,
            'claims' => StoreLens::claimsForTopic($pdo, $topic),
        ]);
    }

    private static function claimsQuery(\PDO $pdo, array $argv): void
    {
        $category = null;
        $confidence = null;
        $status = null;
        $relevance = null;
        for ($i = 2; $i < count($argv); $i++) {
            if ($argv[$i] === '--category' && isset($argv[$i + 1])) {
                $category = $argv[++$i];
            } elseif ($argv[$i] === '--confidence' && isset($argv[$i + 1])) {
                $confidence = $argv[++$i];
            } elseif ($argv[$i] === '--status' && isset($argv[$i + 1])) {
                $status = $argv[++$i];
            } elseif ($argv[$i] === '--relevance' && isset($argv[$i + 1])) {
                $relevance = $argv[++$i];
            }
        }

        if ($category) {
            $rows = StoreLens::byCategory($pdo, $category, $confidence);
        } elseif ($relevance) {
            $rows = StoreLens::byRelevance($pdo, $relevance);
        } elseif ($confidence) {
            $rows = StoreLens::byConfidence($pdo, $confidence);
        } elseif ($status) {
            $rows = StoreLens::byStatus($pdo, $status);
        } else {
            learn_fail('Provide --category, --confidence, --status, or --relevance');
        }

        if ($status && $category) {
            $rows = array_values(array_filter($rows, static fn ($r) => $r['status'] === $status));
        }

        learn_json_out(['ok' => true, 'count' => count($rows), 'claims' => $rows]);
    }
}
