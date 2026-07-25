<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$pdo = db();
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$confidence = isset($_GET['confidence']) ? trim((string) $_GET['confidence']) : '';
$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';

$sql = 'SELECT * FROM claims WHERE 1=1';
$params = [];
if ($category !== '') {
    $sql .= ' AND category = :category';
    $params[':category'] = $category;
}
if ($confidence !== '') {
    $sql .= ' AND confidence = :confidence';
    $params[':confidence'] = $confidence;
}
if ($status !== '') {
    $sql .= ' AND status = :status';
    $params[':status'] = $status;
}
$sql .= ' ORDER BY datetime(seen_at) DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

layout_start('Claims', 'claims');
?>
<p class="lede">Queryable claim store. High-stakes planning should read confirmed claims only. Broad synthesis may read everything, but never treat low-confidence or synthesis claims as settled fact.</p>

<section class="surface group">
  <form method="get" action="<?= learn_h(learn_url('claims.php')) ?>">
    <label for="category">Category</label>
    <input id="category" name="category" type="text" value="<?= learn_h($category) ?>" placeholder="dx">
    <label for="confidence">Confidence</label>
    <input id="confidence" name="confidence" type="text" value="<?= learn_h($confidence) ?>" placeholder="high">
    <label for="status">Status</label>
    <input id="status" name="status" type="text" value="<?= learn_h($status) ?>" placeholder="confirmed">
    <button class="btn" type="submit">Filter</button>
  </form>
</section>

<section class="group surface">
  <h2 class="group-title">Results · <?= count($rows) ?></h2>
  <?php if (!$rows): ?>
    <p class="empty">No claims match.</p>
  <?php else: ?>
    <?php foreach ($rows as $claim): ?>
      <article class="claim">
        <p><strong><?= learn_h((string) $claim['claim']) ?></strong></p>
        <p class="dim"><?= learn_h((string) ($claim['evidence'] ?? '')) ?></p>
        <p class="muted">
          <?= learn_h((string) $claim['category']) ?>
          · <?= learn_h((string) $claim['confidence']) ?>
          · <?= learn_h((string) $claim['claim_type']) ?>
          · <?= learn_h((string) $claim['status']) ?>
          <?php if (!empty($claim['item_id'])): ?> · item #<?= (int) $claim['item_id'] ?><?php endif; ?>
          <?php if (!empty($claim['lesson_file'])): ?> · <a href="<?= learn_h(learn_url('lesson.php?file=' . rawurlencode((string) $claim['lesson_file']))) ?>"><?= learn_h((string) $claim['lesson_file']) ?></a><?php endif; ?>
          · <a href="<?= learn_h((string) $claim['source_url']) ?>">source</a>
        </p>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php layout_end(); ?>
