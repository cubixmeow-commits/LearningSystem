<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Learn\Db;

function layout_start(string $title, string $current = 'items'): void
{
    $pageTitle = $title === '' ? 'Learn' : ($title . ' · Learn');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= learn_h($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Serif:wght@400;600&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= learn_h(learn_url('assets/css/twilight.css')) ?>">
</head>
<body>
  <div class="wrap">
    <header class="site-header">
      <div>
        <p class="eyebrow">Cozy Engineering / Twilight Mode</p>
        <h1 class="brand">Learn<span>.</span></h1>
      </div>
      <nav class="nav" aria-label="Primary">
        <a href="<?= learn_h(learn_url()) ?>"<?= $current === 'items' ? ' aria-current="page"' : '' ?>>Items</a>
        <a href="<?= learn_h(learn_url('topics.php')) ?>"<?= $current === 'topics' ? ' aria-current="page"' : '' ?>>Topics</a>
        <a href="<?= learn_h(learn_url('lessons.php')) ?>"<?= $current === 'lessons' ? ' aria-current="page"' : '' ?>>Lessons</a>
        <a href="<?= learn_h(learn_url('claims.php')) ?>"<?= $current === 'claims' ? ' aria-current="page"' : '' ?>>Claims</a>
        <a href="<?= learn_h(learn_url('add.php')) ?>"<?= $current === 'add' ? ' aria-current="page"' : '' ?>>Add repo</a>
      </nav>
    </header>
<?php
}

function layout_end(): void
{
    ?>
  </div>
  <script src="<?= learn_h(learn_url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}

function db(): PDO
{
    return Db::pdo();
}
