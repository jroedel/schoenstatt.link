<?php

declare(strict_types=1);

namespace App\Books\Import;

/**
 * One column a library-import spreadsheet may carry.
 *
 * A value object rather than an array so that PHPStan can see the shape and so the
 * template writer, the header matcher and the mapping screen all read the same
 * property names. Instances come from App\Books\Import\ImportColumns and nowhere
 * else.
 */
final class ImportColumn
{
    /**
     * @param string $field the book entity field this column writes, as
     *        `module/Books/config/module.config.php` names it in the book spec's
     *        `update_columns`. Getting this wrong is silent: both
     *        `SionTable::createHelper()` and `updateHelper()` skip a key that is not
     *        in `update_columns`, which is how `copyrightYear` was discarded on every
     *        import from 2018-11-02 until this class was written.
     * @param string $heading the column heading the generated template carries
     * @param list<string> $aliases other headings that mean this column, matched
     *        case- and accent-insensitively. The heading itself is always accepted
     *        and does not need repeating here.
     * @param bool $core whether the blank template includes this column. A column
     *        that is present but empty **erases** the stored value, so the blank
     *        template deliberately carries fewer columns than the importer accepts.
     * @param string $help one line for the template's instructions sheet and for the
     *        mapping screen
     */
    public function __construct(
        public readonly string $field,
        public readonly string $heading,
        public readonly array $aliases = [],
        public readonly bool $required = false,
        public readonly bool $core = true,
        public readonly string $help = '',
        public readonly int $width = 18
    ) {
    }
}
