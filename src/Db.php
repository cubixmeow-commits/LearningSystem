<?php
declare(strict_types=1);

namespace Learn;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function path(): string
    {
        return LEARN_ROOT . '/data/learn.sqlite';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dir = dirname(self::path());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . self::path(), null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo = $pdo;
        Schema::migrate($pdo);
        return self::$pdo;
    }
}
