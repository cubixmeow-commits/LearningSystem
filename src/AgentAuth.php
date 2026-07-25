<?php
declare(strict_types=1);

namespace Learn;

final class AgentAuth
{
    public static function tokenPath(): string
    {
        return LEARN_ROOT . '/data/agent_token.txt';
    }

    /**
     * Ensures a token file exists and returns the token.
     * Creates data/ if needed.
     */
    public static function token(): string
    {
        $path = self::tokenPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!is_file($path) || trim((string) file_get_contents($path)) === '') {
            $token = bin2hex(random_bytes(16));
            if (file_put_contents($path, $token . "\n") === false) {
                throw new \RuntimeException('Unable to write agent token file');
            }
            @chmod($path, 0600);
            return $token;
        }
        return trim((string) file_get_contents($path));
    }

    public static function providedToken(): string
    {
        if (isset($_GET['token'])) {
            return trim((string) $_GET['token']);
        }
        if (isset($_POST['token'])) {
            return trim((string) $_POST['token']);
        }
        $header = $_SERVER['HTTP_X_LEARN_TOKEN'] ?? '';
        if (is_string($header) && $header !== '') {
            return trim($header);
        }
        if (!empty($_COOKIE['learn_agent_token'])) {
            return trim((string) $_COOKIE['learn_agent_token']);
        }
        return '';
    }

    public static function check(?string $provided = null): bool
    {
        $provided = $provided ?? self::providedToken();
        if ($provided === '') {
            return false;
        }
        return hash_equals(self::token(), $provided);
    }

    public static function remember(string $token): void
    {
        setcookie('learn_agent_token', $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => learn_web_base() === '' ? '/' : learn_web_base() . '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
