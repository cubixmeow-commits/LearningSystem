<?php
declare(strict_types=1);

namespace Learn;

final class Http
{
    public static function getJson(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to init curl');
        }

        $defaultHeaders = [
            'Accept: application/json',
            'User-Agent: LearnSystem/1.0 (+https://iainreid.dev)',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('HTTP request failed: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('HTTP ' . $code . ' for ' . $url . ': ' . substr($body, 0, 200));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON from ' . $url);
        }
        return $data;
    }
}
