<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

use Learn\TopicRepository;

$pdo = db();
$groups = TopicRepository::listByStatus($pdo);

layout_start('Topics', 'topics');
?>
<p class="lede">Topic queue driven by your curiosity, gap suggestions from lessons, and trending follow-ups. The CLI run loop claims pending topics and writes lessons.</p>

<section class="surface group">
  <h2 class="group-title">Add topic</h2>
  <form method="post" action="/actions.php">
    <input type="hidden" name="action" value="add-topic">
    <label for="topic">Topic</label>
    <input id="topic" name="topic" type="text" required placeholder="SQLite WAL mode for PHP apps">
    <label for="source_note">Source note (optional)</label>
    <input id="source_note" name="source_note" type="text" placeholder="Why this topic">
    <button class="btn" type="submit">Queue topic</button>
  </form>
</section>

<?php foreach (['pending' => 'Pending', 'in-progress' => 'In progress', 'done' => 'Done'] as $key => $label):
    $rows = $groups[$key] ?? [];
    ?>
  <section class="group">
    <h2 class="group-title"><?= learn_h($label) ?> · <?= count($rows) ?></h2>
    <?php if (!$rows): ?>
      <p class="empty">None.</p>
    <?php else: ?>
      <ul class="item-list">
        <?php foreach ($rows as $topic): ?>
          <li class="item-row">
            <div>
              <h3 class="item-title"><?= learn_h((string) $topic['topic']) ?></h3>
              <p class="one-liner"><?= learn_h((string) ($topic['source_note'] ?? '')) ?></p>
              <?php if (!empty($topic['lesson_file'])): ?>
                <p class="dim"><a href="/lesson.php?file=<?= urlencode((string) $topic['lesson_file']) ?>"><?= learn_h((string) $topic['lesson_file']) ?></a></p>
              <?php endif; ?>
            </div>
            <div class="meta">
              <span class="pill"><?= learn_h((string) $topic['added_by']) ?></span>
              <span><?= learn_h((string) $topic['status']) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php layout_end(); ?>
