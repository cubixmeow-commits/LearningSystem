<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$dir = LEARN_ROOT . '/lessons';
$files = [];
if (is_dir($dir)) {
    foreach (glob($dir . '/*.md') ?: [] as $path) {
        $files[] = [
            'file' => 'lessons/' . basename($path),
            'name' => basename($path),
            'mtime' => filemtime($path) ?: 0,
        ];
    }
}
usort($files, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

$pdo = db();

layout_start('Lessons', 'lessons');
?>
<p class="lede">Durable lesson library. Each file is long-form teaching material; extracted claims stay queryable in the store.</p>

<?php if (!$files): ?>
  <p class="empty surface">No lessons yet. Queue a topic and run the curriculum loop.</p>
<?php else: ?>
  <ul class="item-list">
    <?php foreach ($files as $file):
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE lesson_file = :f');
        $stmt->execute([':f' => $file['file']]);
        $count = (int) $stmt->fetchColumn();
        ?>
      <li class="item-row">
        <div>
          <h3 class="item-title"><a href="/lesson.php?file=<?= urlencode($file['file']) ?>"><?= learn_h($file['name']) ?></a></h3>
          <p class="one-liner"><?= $count ?> linked claim<?= $count === 1 ? '' : 's' ?></p>
        </div>
        <div class="meta">
          <span><?= learn_h(date('Y-m-d', $file['mtime'])) ?></span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php layout_end(); ?>
