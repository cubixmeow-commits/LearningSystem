<?php
declare(strict_types=1);

namespace Learn;

use PDO;

final class Schema
{
    public static function migrate(PDO $pdo): void
    {
        $sql = file_get_contents(LEARN_ROOT . '/schema.sql');
        if ($sql === false) {
            throw new \RuntimeException('Missing schema.sql');
        }
        $pdo->exec($sql);
    }
}
