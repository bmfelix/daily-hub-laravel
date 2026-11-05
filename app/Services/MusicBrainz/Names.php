<?php

namespace App\Services\MusicBrainz;

use Illuminate\Support\Str;

class Names
{
    // Separators the stations use between several artists, as in "Tame Impala [+] Jennie".
    private const SEPARATORS = '/\s*(?:\[\+\]|,|&|\bfeat\.?|\bft\.?|\bfeaturing\b|\bwith\b|\bx\b|\/)\s*/i';

    /**
     * Compare names loosely: no accents, case, or punctuation.
     */
    public static function normalize(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($name))));
    }

    /**
     * The full name first (for duos like "Simon & Garfunkel"), then the first listed artist.
     *
     * @return list<string>
     */
    public static function candidates(string $name): array
    {
        $full = trim($name);
        $first = trim(preg_split(self::SEPARATORS, $full, 2)[0]);

        return $first === '' || $first === $full ? [$full] : [$full, $first];
    }

    /**
     * Like normalize, and ignores spaces too, so "3AM" and "3 AM" are the same.
     */
    public static function compact(string $name): string
    {
        return str_replace(' ', '', self::normalize($name));
    }

    /**
     * The same song however a station spells it.
     */
    public static function songKey(string $artist, string $title): string
    {
        return substr(self::compact($artist).'|'.self::compact($title), 0, 400);
    }

    /**
     * The key a radio name is saved under.
     */
    public static function key(string $name): string
    {
        return Str::lower(trim($name));
    }
}
