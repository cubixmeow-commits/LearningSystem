<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

use Learn\ItemRepository;

$pdo = db();
$groups = ItemRepository::listGrouped($pdo);
$labels = [
    'touches-my-work' => 'Touches my work',
    'selected' => 'Selected (manual)',
    'adjacent' => 'Adjacent',
    'untagged' => 'Awaiting triage',
    'no' => 'Not relevant',
];

layout_start('Items', 'items');
?>
<p class="lede">Skimmable intake from GitHub rising repos and Hacker News. Newest first within each relevance band. Briefs and claims are written by the agent runtime and stored here.</p>

<div class="actions">
  <form method="post" action="/actions.php">
    <input type="hidden" name="action" value="fetch">
    <button class="btn" type="submit">Fetch now</button>
  </form>
  <a class="btn btn-secondary" href="/add.php">Add repo by URL</a>
</div>

<?php
$total = 0;
foreach ($groups as $rows) {
    $total += count($rows);
}
if ($total === 0): ?>
  <p class="empty surface">No items yet. Run fetch now, or add a repo by URL.</p>
<?php endif; ?>

<?php foreach ($labels as $key => $label):
    $rows = $groups[$key] ?? [];
    if (!$rows) {
        continue;
    }
    ?>
  <section class="group">
    <h2 class="group-title"><?= learn_h($label) ?> · <?= count($rows) ?></h2>
    <ul class="item-list">
      <?php foreach ($rows as $item): ?>
        <li class="item-row">
          <div>
            <h3 class="item-title"><a href="/item.php?id=<?= (int) $item['id'] ?>"><?= learn_h((string) $item['title']) ?></a></h3>
            <p class="one-liner"><?= learn_h((string) ($item['one_liner'] ?? 'Triage pending')) ?></p>
          </div>
          <div class="meta">
            <span class="pill"><?= learn_h((string) $item['source']) ?></span>
            <span><?= learn_h((string) ($item['trend_signal'] ?? '')) ?></span>
            <span><?= learn_h((string) $item['status']) ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
<?php layout_end(); ?>
