<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

use Learn\AgentAuth;
use Learn\LessonWriter;
use Learn\StoreLens;
use Learn\TopicRepository;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function agent_api_out(array $payload, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // Ensure token file exists even on failed auth so the owner can recover it via SSH.
    $expected = AgentAuth::token();
} catch (Throwable $e) {
    agent_api_out(['ok' => false, 'error' => $e->getMessage()], 500);
}

if (!AgentAuth::check()) {
    agent_api_out([
        'ok' => false,
        'error' => 'Unauthorized. Pass token as ?token=, POST token, cookie, or X-Learn-Token header.',
        'hint' => 'Read data/agent_token.txt on the server after opening /agent.php once, or ask the site owner for the token.',
    ], 401);
}

$pdo = db();
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    switch ($action) {
        case 'status':
        case 'queue': {
            $groups = TopicRepository::listByStatus($pdo);
            agent_api_out([
                'ok' => true,
                'queue' => [
                    'pending' => $groups['pending'] ?? [],
                    'in-progress' => $groups['in-progress'] ?? [],
                    'done' => array_slice($groups['done'] ?? [], 0, 20),
                ],
                'counts' => [
                    'pending' => count($groups['pending'] ?? []),
                    'in-progress' => count($groups['in-progress'] ?? []),
                    'done' => count($groups['done'] ?? []),
                ],
            ]);
        }

        case 'next-topic': {
            if (!in_array($method, ['GET', 'POST'], true)) {
                agent_api_out(['ok' => false, 'error' => 'Use GET or POST'], 405);
            }
            $topic = TopicRepository::claimNext($pdo);
            $lens = [];
            if ($topic) {
                $lens = StoreLens::claimsForTopic($pdo, (string) $topic['topic']);
            }
            agent_api_out([
                'ok' => true,
                'topic' => $topic,
                'store_lens' => $lens,
                'hint' => $topic
                    ? 'Research this topic, then POST action=save-lesson with lesson_markdown, claims, optional gap_topics'
                    : 'No pending or in-progress topics',
                'prompt_for_cursor' => $topic
                    ? 'Run Learn topic #' . $topic['id'] . ': "' . $topic['topic'] . '". Read store_lens, research current primary sources, write a GATE 3 lesson, then save via agent-api.php action=save-lesson.'
                    : 'No Learn topics are waiting. Add one on topics.php first.',
            ]);
        }

        case 'save-lesson': {
            if ($method !== 'POST') {
                agent_api_out(['ok' => false, 'error' => 'Use POST'], 405);
            }
            $raw = (string) file_get_contents('php://input');
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                // Allow form-encoded JSON body field.
                if (isset($_POST['payload'])) {
                    $payload = json_decode((string) $_POST['payload'], true);
                } elseif (isset($_POST['lesson_markdown'])) {
                    $claims = json_decode((string) ($_POST['claims'] ?? '[]'), true);
                    $gaps = json_decode((string) ($_POST['gap_topics'] ?? '[]'), true);
                    $payload = [
                        'topic_id' => (int) ($_POST['topic_id'] ?? 0),
                        'lesson_markdown' => (string) $_POST['lesson_markdown'],
                        'claims' => is_array($claims) ? $claims : [],
                        'gap_topics' => is_array($gaps) ? $gaps : [],
                    ];
                    if (!empty($_POST['slug'])) {
                        $payload['slug'] = (string) $_POST['slug'];
                    }
                }
            }
            if (!is_array($payload)) {
                agent_api_out(['ok' => false, 'error' => 'Invalid JSON payload'], 400);
            }
            $result = LessonWriter::save($pdo, $payload);
            agent_api_out(['ok' => true] + $result);
        }

        default:
            agent_api_out([
                'ok' => false,
                'error' => 'Unknown action',
                'actions' => ['status', 'queue', 'next-topic', 'save-lesson'],
            ], 400);
    }
} catch (Throwable $e) {
    agent_api_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
