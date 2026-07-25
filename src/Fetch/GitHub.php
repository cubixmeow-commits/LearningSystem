<?php
declare(strict_types=1);

namespace Learn\Fetch;

use Learn\Http;

final class GitHub
{
    /**
     * Recently created, high-star repos. trend_signal stores current_stars, not growth.
     *
     * @return list<array{url:string,source:string,title:string,trend_signal:string}>
     */
    public static function rising(int $days = 30, int $perPage = 20): array
    {
        $since = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d');
        $q = rawurlencode('created:>' . $since);
        $url = 'https://api.github.com/search/repositories?q=' . $q
            . '&sort=stars&order=desc&per_page=' . $perPage;

        $data = Http::getJson($url);
        $items = [];
        foreach (($data['items'] ?? []) as $repo) {
            if (!is_array($repo)) {
                continue;
            }
            $htmlUrl = (string) ($repo['html_url'] ?? '');
            $fullName = (string) ($repo['full_name'] ?? '');
            if ($htmlUrl === '' || $fullName === '') {
                continue;
            }
            $stars = (int) ($repo['stargazers_count'] ?? 0);
            $items[] = [
                'url' => $htmlUrl,
                'source' => 'github',
                'title' => $fullName,
                'trend_signal' => 'current_stars:' . $stars,
            ];
        }
        return $items;
    }

    /**
     * @return array{url:string,source:string,title:string,trend_signal:string}
     */
    public static function repoByUrl(string $repoUrl): array
    {
        $parsed = self::parseRepo($repoUrl);
        $api = 'https://api.github.com/repos/' . rawurlencode($parsed['owner']) . '/' . rawurlencode($parsed['repo']);
        $repo = Http::getJson($api);
        $htmlUrl = (string) ($repo['html_url'] ?? $parsed['url']);
        $fullName = (string) ($repo['full_name'] ?? ($parsed['owner'] . '/' . $parsed['repo']));
        $stars = (int) ($repo['stargazers_count'] ?? 0);
        return [
            'url' => $htmlUrl,
            'source' => 'manual',
            'title' => $fullName,
            'trend_signal' => 'current_stars:' . $stars,
        ];
    }

    /**
     * @return array{owner:string,repo:string,url:string}
     */
    public static function parseRepo(string $repoUrl): array
    {
        $repoUrl = trim($repoUrl);
        if (preg_match('~^https?://github\.com/([^/]+)/([^/?]+)~i', $repoUrl, $m)) {
            $repo = preg_replace('~[#].*$~', '', $m[2]) ?? $m[2];
            return [
                'owner' => $m[1],
                'repo' => rtrim($repo, '.git'),
                'url' => 'https://github.com/' . $m[1] . '/' . rtrim($repo, '.git'),
            ];
        }
        if (preg_match('~^([^/]+)/([^/?]+)$~', $repoUrl, $m)) {
            return [
                'owner' => $m[1],
                'repo' => rtrim($m[2], '.git'),
                'url' => 'https://github.com/' . $m[1] . '/' . rtrim($m[2], '.git'),
            ];
        }
        throw new \InvalidArgumentException('Not a GitHub repo URL: ' . $repoUrl);
    }
}
