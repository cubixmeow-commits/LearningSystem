<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$file = isset($_GET['file']) ? (string) $_GET['file'] : '';
$file = ltrim(str_replace(['..', '\\'], '', $file), '/');
if (!str_starts_with($file, 'lessons/') || !str_ends_with($file, '.md')) {
    http_response_code(400);
    layout_start('Lesson', 'lessons');
    echo '<p class="flash error">Invalid lesson path.</p>';
    layout_end();
    exit;
}

$absolute = LEARN_ROOT . '/' . $file;
if (!is_file($absolute)) {
    http_response_code(404);
    layout_start('Lesson', 'lessons');
    echo '<p class="flash error">Lesson not found.</p>';
    layout_end();
    exit;
}

$raw = (string) file_get_contents($absolute);
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM claims WHERE lesson_file = :f ORDER BY id ASC');
$stmt->execute([':f' => $file]);
$claims = $stmt->fetchAll();

// Minimal markdown-ish rendering: headings, paragraphs, details blocks already in source.
function render_lesson_markdown(string $md): string
{
    $lines = preg_split("/\r\n|\n|\r/", $md) ?: [];
    $html = '';
    $para = [];
    $flush = static function () use (&$html, &$para): void {
        if ($para) {
            $html .= '<p>' . learn_h(implode(' ', $para)) . '</p>';
            $para = [];
        }
    };
    foreach ($lines as $line) {
        if (str_starts_with($line, '## ')) {
            $flush();
            $html .= '<h2>' . learn_h(substr($line, 3)) . '</h2>';
            continue;
        }
        if (str_starts_with($line, '# ')) {
            $flush();
            $html .= '<h2>' . learn_h(substr($line, 2)) . '</h2>';
            continue;
        }
        if (trim($line) === '') {
            $flush();
            continue;
        }
        if (str_starts_with($line, '<details') || str_starts_with($line, '</details') || str_starts_with($line, '<summary') || str_starts_with($line, '</summary')) {
            $flush();
            $html .= $line;
            continue;
        }
        $para[] = $line;
    }
    $flush();
    return $html;
}

layout_start(basename($file), 'lessons');
?>
<p class="eyebrow">Lesson file</p>
<h2 class="brand" style="font-size:1.5rem;margin:0 0 1rem"><?= learn_h(basename($file)) ?></h2>
<article class="lesson-body surface">
  <?= render_lesson_markdown($raw) ?>
</article>

<section class="group surface">
  <h2 class="group-title">Claims from this lesson</h2>
  <?php if (!$claims): ?>
    <p class="empty">No claims linked.</p>
  <?php else: ?>
    <?php foreach ($claims as $claim): ?>
      <article class="claim">
        <p><strong><?= learn_h((string) $claim['claim']) ?></strong></p>
        <p class="muted">
          <?= learn_h((string) $claim['confidence']) ?>
          · <?= learn_h((string) $claim['claim_type']) ?>
          · <a href="<?= learn_h((string) $claim['source_url']) ?>">source</a>
        </p>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php layout_end(); ?>
