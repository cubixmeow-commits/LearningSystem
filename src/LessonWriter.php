<?php
declare(strict_types=1);

namespace Learn;

use PDO;

final class LessonWriter
{
    /**
     * @param array<string,mixed> $payload
     * @return array{topic_id:int,lesson_file:string,claims:list<array{action:string,id:int}>,gap_topics:list<array<string,mixed>>}
     */
    public static function save(PDO $pdo, array $payload): array
    {
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
            // Idempotent re-save.
        } elseif ($topic['status'] === 'pending') {
            throw new \InvalidArgumentException('Topic is still pending; claim it with next-topic first');
        }

        $slug = isset($payload['slug'])
            ? learn_slugify((string) $payload['slug'])
            : learn_slugify((string) $topic['topic']);
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
                            isset($gap['source_note'])
                                ? (string) $gap['source_note']
                                : ('From lesson ' . $relative)
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

        return [
            'topic_id' => $topicId,
            'lesson_file' => $relative,
            'claims' => $claimResults,
            'gap_topics' => $gapTopics,
        ];
    }
}
