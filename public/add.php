<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

layout_start('Add repo', 'add');
?>
<p class="lede">Paste a GitHub repo URL. Manual adds skip triage, land as source=manual and relevance=selected, and are immediately eligible for a brief.</p>

<section class="surface">
  <form method="post" action="/actions.php">
    <input type="hidden" name="action" value="add-repo">
    <label for="url">GitHub repo URL</label>
    <input id="url" name="url" type="url" required placeholder="https://github.com/owner/repo">
    <button class="btn" type="submit">Add repo</button>
  </form>
</section>
<?php layout_end(); ?>
