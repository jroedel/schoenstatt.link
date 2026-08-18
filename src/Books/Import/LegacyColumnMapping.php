<?php

declare(strict_types=1);

namespace App\Books\Import;

use function is_array;
use function is_string;

/**
 * Translates a stored `ColumnMapping` written before 2018-11-02 into current field names.
 *
 * All thirteen mappings in `lib_imports` predate the book entity's field rename and
 * name `author`, `pages`, `language`, `edition` and `copyrightYear` — none of which is
 * a book field today. Reading one straight, which the edit page's `@todo` proposed,
 * would map barcode and title and drop the other ten columns without a word.
 *
 * The translation is one way and only ever applied on read; nothing writes an old name
 * back. A mapping saved by the mapping screen is already in current names, and passing
 * it through here changes nothing.
 */
final class LegacyColumnMapping
{
    /** Old field name => the field that replaced it. */
    private const RENAMED = [
        'author'        => 'authorsText',
        'pages'         => 'numberOfPages',
        'language'      => 'inLanguage',
        'edition'       => 'bookEdition',
        'copyrightYear' => 'publishedYear',
    ];

    /**
     * @param mixed $stored whatever `unserialize()` produced for the row — `false` for
     *        a null column, which is what most of the fourteen rows hold
     * @return array<string, string>|null field => heading, or null when there is
     *         nothing usable
     */
    public static function forward(mixed $stored): ?array
    {
        if (! is_array($stored) || [] === $stored) {
            return null;
        }

        $forward = [];
        foreach ($stored as $field => $heading) {
            if (! is_string($field) || ! is_string($heading)) {
                continue;
            }
            $field = self::RENAMED[$field] ?? $field;
            if (null === ImportColumns::get($field)) {
                //A field the vocabulary no longer has at all. Dropping it is the same
                //outcome as keeping it — the importer would skip it — but dropping it
                //here means the mapping screen can say how many columns were recovered.
                continue;
            }
            $forward[$field] ??= $heading;
        }

        return [] === $forward ? null : $forward;
    }
}
