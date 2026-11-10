<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Reads Google News' public top-stories feed (RSS), which needs no key.
 */
class GoogleNewsClient
{
    private const FEED_URL = 'https://news.google.com/rss';

    /** US top stories, in English. */
    private const FEED_PARAMS = ['hl' => 'en-US', 'gl' => 'US', 'ceid' => 'US:en'];

    /**
     * @return list<array{title: string, link: string, published: string, source: string}>
     */
    public function topStories(int $limit = 20): array
    {
        $xml = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'DailyHub/1.0'])
            ->get(self::FEED_URL, self::FEED_PARAMS)
            ->throw()
            ->body();

        return array_slice(self::parse($xml), 0, $limit);
    }

    /**
     * The stories in a Google News RSS feed, in order.
     *
     * @return list<array{title: string, link: string, published: string, source: string}>
     */
    public static function parse(string $xml): array
    {
        // Collect XML errors instead of raising PHP warnings, so a bad feed is handled by the caller.
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($feed === false) {
            throw new UnexpectedValueException('Google News returned invalid XML');
        }

        $stories = [];
        foreach ($feed->channel->item as $item) {
            $stories[] = [
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'published' => (string) $item->pubDate,
                'source' => (string) $item->source,
            ];
        }

        return $stories;
    }
}
