<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

use Learn\AgentAuth;
use Learn\LessonWriter;
use Learn\StoreLens;
use Learn\TopicRepository;

$pdo = db();
$tokenExistsBefore = is_file(AgentAuth::tokenPath())
    && trim((string) @file_get_contents(AgentAuth::tokenPath())) !== '';
$serverToken = AgentAuth::token();
$tokenJustCreated = !$tokenExistsBefore;

$flash = null;
$flashError = null;
$claimed = null;
$lens = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = trim((string) ($_POST['token'] ?? ''));
    if (!AgentAuth::check($postedToken)) {
        $flashError = 'Token rejected. Check data/agent_token.txt on the server.';
    } else {
        AgentAuth::remember($postedToken);
        $_GET['token'] = $postedToken;
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'unlock') {
                $flash = 'Agent access unlocked for this browser.';
            } elseif ($action === 'next-topic') {
                $claimed = TopicRepository::claimNext($pdo);
                if ($claimed) {
                    $lens = StoreLens::claimsForTopic($pdo, (string) $claimed['topic']);
                    $flash = 'Claimed topic #' . $claimed['id'] . '. Research it, then save the lesson below.';
                } else {
                    $flash = 'No pending or in-progress topics.';
                }
            } elseif ($action === 'save-lesson') {
                $claims = json_decode((string) ($_POST['claims'] ?? '[]'), true);
                $gaps = json_decode((string) ($_POST['gap_topics'] ?? '[]'), true);
                if (!is_array($claims)) {
                    throw new InvalidArgumentException('claims must be JSON array');
                }
                if (!is_array($gaps)) {
                    throw new InvalidArgumentException('gap_topics must be JSON array');
                }
                $payload = [
                    'topic_id' => (int) ($_POST['topic_id'] ?? 0),
                    'lesson_markdown' => (string) ($_POST['lesson_markdown'] ?? ''),
                    'claims' => $claims,
                    'gap_topics' => $gaps,
                ];
                $result = LessonWriter::save($pdo, $payload);
                $flash = 'Saved ' . $result['lesson_file'] . ' with ' . count($result['claims']) . ' claims.';
            }
        } catch (Throwable $e) {
            $flashError = $e->getMessage();
        }
    }
}

$authed = AgentAuth::check();
$groups = TopicRepository::listByStatus($pdo);
$inProgress = $groups['in-progress'][0] ?? null;
if ($claimed === null && $inProgress) {
    $claimed = $inProgress;
    $lens = StoreLens::claimsForTopic($pdo, (string) $claimed['topic']);
}

$publicToken = $authed ? AgentAuth::providedToken() : '';
if ($publicToken === '' && $tokenJustCreated) {
    $publicToken = $serverToken;
}

$apiNext = learn_url('agent-api.php?action=next-topic' . ($publicToken !== '' ? '&token=' . rawurlencode($publicToken) : ''));
$apiStatus = learn_url('agent-api.php?action=status' . ($publicToken !== '' ? '&token=' . rawurlencode($publicToken) : ''));
$pageUrl = learn_url('agent.php' . ($publicToken !== '' ? '?token=' . rawurlencode($publicToken) : ''));

$cursorPrompt = 'Open ' . (isset($_SERVER['HTTP_HOST'])
        ? (('https://' . $_SERVER['HTTP_HOST']) . learn_url('agent.php'))
        : learn_url('agent.php'))
    . ' with the Learn agent token, claim the next topic, research it using the store lens and live primary sources, write a GATE 3 lesson, then save it through the Agent Run page or agent-api.php save-lesson endpoint. Do not call an LLM from PHP.';

layout_start('Agent Run', 'agent');
?>
<p class="lede">This page is the web run loop for Cursor / Claude Code. PHP still does not call an LLM. The agent claims a topic here, researches it, then posts the lesson and claims back.</p>

<?php if ($flash): ?><p class="flash"><?= learn_h($flash) ?></p><?php endif; ?>
<?php if ($flashError): ?><p class="flash error"><?= learn_h($flashError) ?></p><?php endif; ?>

<?php if ($tokenJustCreated): ?>
<section class="group surface">
  <h2 class="group-title">New agent token created</h2>
  <p class="dim">Copy this once and keep it private. It also lives in <code>data/agent_token.txt</code> on the server (not web-readable).</p>
  <p><code class="token-box"><?= learn_h($serverToken) ?></code></p>
  <button class="btn" type="button" data-copy="<?= learn_h($serverToken) ?>">Copy token</button>
</section>
<?php endif; ?>

<?php if (!$authed): ?>
<section class="group surface">
  <h2 class="group-title">Unlock agent access</h2>
  <form method="post" action="<?= learn_h(learn_url('agent.php')) ?>">
    <input type="hidden" name="action" value="unlock">
    <label for="token">Agent token</label>
    <input id="token" name="token" type="text" required autocomplete="off" placeholder="Paste token from data/agent_token.txt">
    <button class="btn" type="submit">Unlock</button>
  </form>
</section>
<?php else: ?>

<section class="group surface">
  <h2 class="group-title">Ask Cursor</h2>
  <p class="dim">Paste this into Cursor when you want the agent to run topics against the live site.</p>
  <textarea readonly rows="5" id="cursor-prompt"><?= learn_h($cursorPrompt) ?></textarea>
  <div class="actions">
    <button class="btn" type="button" data-copy="<?= learn_h($cursorPrompt) ?>">Copy Cursor prompt</button>
    <button class="btn btn-secondary" type="button" data-copy="<?= learn_h($pageUrl) ?>">Copy this page URL</button>
  </div>
  <p class="muted">JSON API: <code><?= learn_h($apiStatus) ?></code></p>
</section>

<section class="group">
  <h2 class="group-title">Queue</h2>
  <ul class="item-list">
    <li class="item-row">
      <div>
        <h3 class="item-title">Pending</h3>
        <p class="one-liner"><?= count($groups['pending'] ?? []) ?> waiting</p>
      </div>
      <div class="meta"><span class="pill">pending</span></div>
    </li>
    <li class="item-row">
      <div>
        <h3 class="item-title">In progress</h3>
        <p class="one-liner"><?= count($groups['in-progress'] ?? []) ?> claimed</p>
      </div>
      <div class="meta"><span class="pill">in-progress</span></div>
    </li>
    <li class="item-row">
      <div>
        <h3 class="item-title">Done</h3>
        <p class="one-liner"><?= count($groups['done'] ?? []) ?> completed</p>
      </div>
      <div class="meta"><span class="pill">done</span></div>
    </li>
  </ul>
</section>

<section class="group surface">
  <h2 class="group-title">1. Claim next topic</h2>
  <form method="post" action="<?= learn_h(learn_url('agent.php')) ?>">
    <input type="hidden" name="token" value="<?= learn_h($publicToken) ?>">
    <input type="hidden" name="action" value="next-topic">
    <button class="btn" type="submit">Claim next topic</button>
    <a class="btn btn-secondary" href="<?= learn_h($apiNext) ?>">Open next-topic JSON</a>
  </form>
</section>

<?php if ($claimed): ?>
<section class="group surface">
  <h2 class="group-title">Active topic · #<?= (int) $claimed['id'] ?></h2>
  <p class="item-title" style="margin:0 0 0.5rem"><?= learn_h((string) $claimed['topic']) ?></p>
  <p class="dim"><?= learn_h((string) ($claimed['source_note'] ?? '')) ?></p>
  <p class="muted"><?= learn_h((string) $claimed['status']) ?> · <?= learn_h((string) $claimed['added_by']) ?></p>

  <h3 class="group-title" style="margin-top:1.25rem">Store lens</h3>
  <textarea readonly rows="10"><?= learn_h((string) json_encode($lens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>

  <h3 class="group-title" style="margin-top:1.25rem">2. Save lesson</h3>
  <p class="dim">After research, paste the GATE 3 lesson markdown and  claims JSON. Optional gap_topics queues STUDY NEXT items.</p>
  <form method="post" action="<?= learn_h(learn_url('agent.php')) ?>">
    <input type="hidden" name="token" value="<?= learn_h($publicToken) ?>">
    <input type="hidden" name="action" value="save-lesson">
    <input type="hidden" name="topic_id" value="<?= (int) $claimed['id'] ?>">
    <label for="lesson_markdown">Lesson markdown</label>
    <textarea id="lesson_markdown" name="lesson_markdown" required rows="16" placeholder="## WHAT YOU ALREADY KNOW"></textarea>
    <label for="claims">Claims JSON array</label>
    <textarea id="claims" name="claims" required rows="10" placeholder='[{"category":"architecture","claim":"...","source_url":"https://...","confidence":"high","claim_type":"fact"}]'></textarea>
    <label for="gap_topics">Gap topics JSON array (optional)</label>
    <textarea id="gap_topics" name="gap_topics" rows="4" placeholder='[{"topic":"Next topic","source_note":"From STUDY NEXT"}]'>[]</textarea>
    <button class="btn" type="submit">Save lesson</button>
  </form>
</section>
<?php endif; ?>

<?php endif; ?>

<section class="group surface">
  <h2 class="group-title">How to get Cursor to run topics</h2>
  <ol class="dim" style="padding-left:1.2rem">
    <li>Add topics on the Topics page (or leave gap-suggestions in the queue).</li>
    <li>Unlock this Agent Run page with the token.</li>
    <li>In Cursor, paste the Ask Cursor prompt above (or say: run the next Learn topic on iainreid.dev using the agent page).</li>
    <li>Cursor claims the topic, researches, writes the lesson, and saves it here or via <code>agent-api.php</code>.</li>
  </ol>
</section>
<?php
layout_end();
