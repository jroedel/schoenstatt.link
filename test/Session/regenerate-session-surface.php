<?php

/**
 * Rewrite `test/Session/session-surface.php` from laminas-session as it stands.
 *
 *     docker compose exec -T -u www-data app php test/Session/regenerate-session-surface.php
 *
 * **This can only be regenerated while `laminas/laminas-session` is installed.** It is the
 * record of what that package wrote into `$_SESSION`, kept so the replacement can be held
 * to reading it. Once the package is gone the recording is the only copy of the format,
 * which is the point — a parity test would have died with its subject.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SessionSurface.php';

use SchoenstattTest\Session\SessionSurface;

$surface = SessionSurface::collect();

$header = <<<'PHP_HEADER'
<?php

/**
 * What laminas-session wrote into `$_SESSION`, and what a reader must recover from it.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Session/regenerate-session-surface.php
 *
 * and only while `laminas/laminas-session` is still installed.
 *
 * `session` is `serialize($_SESSION)` — the bytes PHP's session handler writes to storage,
 * so it is the real compatibility contract and not a paraphrase of one. `values` is what
 * the application has to get back out, written by hand.
 *
 * A visitor's cookie lives 30 days. Anything that cannot read these bytes signs that
 * visitor out for 30 days, and `App\Http\SessionListener` records what that looked like
 * the last time it happened: empty 200s on every ported route, 2026-08-07.
 *
 * @return array<string, array{session: string, values: array<string, mixed>}>
 */

declare(strict_types=1);

return
PHP_HEADER;

file_put_contents(__DIR__ . '/session-surface.php', $header . ' ' . var_export($surface, true) . ";\n");

printf("Recorded %d session shapes.\n", count($surface));
foreach ($surface as $label => $case) {
    printf("  %-34s %5d bytes\n", $label, strlen($case['session']));
}
