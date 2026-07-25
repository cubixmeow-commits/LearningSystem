<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

use Learn\ClaimStore;
use Learn\Fetch\GitHub;
use Learn\Fetch\HackerNews;
use Learn\ItemRepository;
use Learn\TopicRepository;
use Learn\Validate;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'POST only';
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$pdo = db();

try {
    switch ($action) {
        case 'fetch': {
            $candidates = array_merge(
                GitHub::rising(30, 20),
                HackerNews::frontPage(20),
                HackerNews::recentByPoints(100, 10)
            );
            $urls = [];
            foreach ($candidates as $item) {
                if (isset($urls[$item['url']])) {
                    continue;
                }
                $urls[$item['url']] = true;
                ItemRepository::insertIfNew($pdo, [
                    'url' => $item['url'],
                    'source' => $item['source'],
                    'title' => $item['title'],
                    'trend_signal' => $item['trend_signal'],
                    'status' => 'triaged',
                ]);
            }
            header('Location: ' . learn_url('?fetched=1'));
            exit;
        }

        case 'add-repo': {
            $url = trim((string) ($_POST['url'] ?? ''));
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
            $id = (int) $result['item']['id'];
            header('Location: ' . learn_url('item.php?id=' . $id));
            exit;
        }

        case 'add-topic': {
            $topic = trim((string) ($_POST['topic'] ?? ''));
            $note = trim((string) ($_POST['source_note'] ?? ''));
            if ($topic === '') {
                throw new InvalidArgumentException('Topic is required');
            }
            TopicRepository::add($pdo, $topic, 'me', $note !== '' ? $note : null);
            header('Location: ' . learn_url('topics.php'));
            exit;
        }

        case 'save-brief': {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $raw = (string) ($_POST['brief_json'] ?? '');
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Invalid JSON');
            }
            if (!isset($payload['item_id'])) {
                $payload['item_id'] = $itemId;
            }
            $tmp = LEARN_ROOT . '/tmp/web-brief-' . $itemId . '.json';
            file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            // Reuse CLI validation path via internal include of save logic.
            $brief = $payload['brief'] ?? null;
            $claims = $payload['claims'] ?? null;
            if (!is_array($brief) || !is_array($claims)) {
                throw new InvalidArgumentException('Payload needs brief and claims');
            }
            Validate::brief($brief);
            if (count($claims) < 3 || count($claims) > 6) {
                throw new InvalidArgumentException('claims must contain 3 to 6 entries');
            }
            $item = ItemRepository::find($pdo, (int) $payload['item_id']);
            if (!$item) {
                throw new InvalidArgumentException('Unknown item');
            }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE items SET brief = :brief, status = 'briefed' WHERE id = :id")
                ->execute([
                    ':brief' => json_encode($brief, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    ':id' => (int) $payload['item_id'],
                ]);
            foreach ($claims as $claim) {
                if (!is_array($claim)) {
                    throw new InvalidArgumentException('Bad claim');
                }
                $claim['item_id'] = (int) $payload['item_id'];
                $claim['lesson_file'] = $claim['lesson_file'] ?? null;
                ClaimStore::upsert($pdo, $claim);
            }
            $pdo->commit();
            header('Location: ' . learn_url('item.php?id=' . (int) $payload['item_id']));
            exit;
        }

        default:
            throw new InvalidArgumentException('Unknown action');
    }
} catch (Throwable $e) {
    http_response_code(400);
    layout_start('Error', 'items');
    echo '<p class="flash error">' . learn_h($e->getMessage()) . '</p>';
    echo '<p><a href="' . learn_h(learn_url()) . '">Back to items</a></p>';
    layout_end();
    exit;
}
