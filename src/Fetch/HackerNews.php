<?php
declare(strict_types=1);

namespace Learn\Fetch;

use Learn\Http;

final class HackerNews
{
    /**
     * @return list<array{url:string,source:string,title:string,trend_signal:string}>
     */
    public static function frontPage(int $limit = 20): array
    {
        $data = Http::getJson('https://hn.algolia.com/api/v1/search?tags=front_page&hitsPerPage=' . $limit);
        return self::mapHits($data['hits'] ?? []);
    }

    /**
     * Recent stories with a points floor.
     *
     * @return list<array{url:string,source:string,title:string,trend_signal:string}>
     */
    public static function recentByPoints(int $minPoints = 100, int $limit = 20): array
    {
        $url = 'https://hn.algolia.com/api/v1/search_by_date?tags=story&hitsPerPage=' . $limit
            . '&numericFilters=' . rawurlencode('points>' . $minPoints);
        $data = Http::getJson($url);
        return self::mapHits($data['hits'] ?? []);
    }

    /**
     * @param list<mixed> $hits
     * @return list<array{url:string,source:string,title:string,trend_signal:string}>
     */
    private static function mapHits(array $hits): array
    {
        $items = [];
        foreach ($hits as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $title = trim((string) ($hit['title'] ?? ''));
            $objectId = (string) ($hit['objectID'] ?? '');
            $url = trim((string) ($hit['url'] ?? ''));
            if ($url === '' && $objectId !== '') {
                $url = 'https://news.ycombinator.com/item?id=' . $objectId;
            }
            if ($title === '' || $url === '') {
                continue;
            }
            $points = (int) ($hit['points'] ?? 0);
            $items[] = [
                'url' => $url,
                'source' => 'hn',
                'title' => $title,
                'trend_signal' => 'hn_points:' . $points,
            ];
        }
        return $items;
    }
}
