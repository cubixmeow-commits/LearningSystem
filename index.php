<?php
declare(strict_types=1);

// Fallback when mod_rewrite is unavailable: send browsers into public/.
header('Location: public/', true, 302);
exit;
