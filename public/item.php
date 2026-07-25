<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

use Learn\ItemRepository;

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$item = $id > 0 ? ItemRepository::find($pdo, $id) : null;
if (!$item) {
    http_response_code(404);
    layout_start('Not found', 'items');
    echo '<p class="flash error">Item not found.</p>';
    layout_end();
    exit;
}

$claimsStmt = $pdo->prepare('SELECT * FROM claims WHERE item_id = :id ORDER BY id ASC');
$claimsStmt->execute([':id' => $id]);
$claims = $claimsStmt->fetchAll();
$brief = null;
if (!empty($item['brief'])) {
    $decoded = json_decode((string) $item['brief'], true);
    if (is_array($decoded)) {
        $brief = $decoded;
    }
}

$agentHint = 'php learn.php next-brief ' . $id . ' && php learn.php save-brief brief.json';

layout_start((string) $item['title'], 'items');
?>
<p class="eyebrow"><?= learn_h((string) $item['source']) ?> · <?= learn_h((string) ($item['trend_signal'] ?? '')) ?></p>
<h2 class="brand" style="font-size:1.7rem;margin:0 0 0.5rem"><?= learn_h((string) $item['title']) ?></h2>
<p class="dim"><a href="<?= learn_h((string) $item['url']) ?>" rel="noopener noreferrer"><?= learn_h((string) $item['url']) ?></a></p>
<p class="lede"><?= learn_h((string) ($item['one_liner'] ?? 'Awaiting triage one-liner')) ?></p>

<div class="actions">
  <span class="pill"><?= learn_h((string) $item['status']) ?></span>
  <span class="pill"><?= learn_h((string) ($item['relevance'] ?? 'untagged')) ?></span>
</div>

<section class="group surface">
  <h2 class="group-title">Generate brief</h2>
  <p class="dim">PHP does not call an LLM. The agent claims this item with <code>next-brief</code>, researches it, then posts structured JSON via <code>save-brief</code>.</p>
  <div class="actions">
    <button class="btn" type="button" data-copy="<?= learn_h($agentHint) ?>">Copy agent command</button>
    <a class="btn btn-secondary" href="#save-brief">Paste brief JSON</a>
  </div>
</section>

<?php if ($brief): ?>
<section class="group">
  <h2 class="group-title">Brief</h2>
  <dl class="brief-grid surface">
    <?php foreach ($brief as $k => $v): ?>
      <div class="brief-field">
        <dt><?= learn_h((string) $k) ?></dt>
        <dd><?= learn_h((string) $v) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
</section>
<?php endif; ?>

<?php if ($claims): ?>
<section class="group surface">
  <h2 class="group-title">Extracted claims</h2>
  <?php foreach ($claims as $claim): ?>
    <article class="claim">
      <p><strong><?= learn_h((string) $claim['claim']) ?></strong></p>
      <p class="dim"><?= learn_h((string) ($claim['evidence'] ?? '')) ?></p>
      <p class="muted">
        <?= learn_h((string) $claim['category']) ?>
        · <?= learn_h((string) $claim['confidence']) ?>
        · <?= learn_h((string) $claim['claim_type']) ?>
        · <?= learn_h((string) $claim['status']) ?>
        · <a href="<?= learn_h((string) $claim['source_url']) ?>">source</a>
      </p>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="group surface" id="save-brief">
  <h2 class="group-title">Save brief JSON</h2>
  <form method="post" action="<?= learn_h(learn_url('actions.php')) ?>">
    <input type="hidden" name="action" value="save-brief">
    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
    <label for="brief_json">JSON payload</label>
    <textarea id="brief_json" name="brief_json" placeholder='{"brief":{...},"claims":[...]}'></textarea>
    <button class="btn" type="submit">Save brief</button>
  </form>
</section>
<?php layout_end(); ?>
