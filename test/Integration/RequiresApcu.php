<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use function apcu_enabled;
use function extension_loaded;

/**
 * Skip when this process cannot use APCu, and therefore cannot build the container.
 *
 * ## Why a whole trait for one `if`
 *
 * Because the failure it guards is not a cache miss — it is a
 * `ServiceNotCreatedException` while the **container is still building**, so nothing
 * downstream of it can be reached and no skip inside a test body is ever executed.
 *
 * The service is `BjyAuthorize\Cache`, an APCu storage adapter named explicitly by
 * `config/autoload/acl.global.php`, and laminas-cache refuses to create one when
 * `apc.enabled`/`apc.enable_cli` says the extension is off. What surprises is the
 * *reach* of that: `BjyAuthorize\Service\Authorize` is what SionModel's acting-user
 * provider resolves, the provider is a constructor argument of
 * `JTranslate\Model\TranslationsTable`, and the table is a constructor argument of
 * half the controllers — so on a runner with no APCu, asking for a view helper or a
 * table lands on "ext/apcu is disabled".
 *
 * That is not JTranslate's own cache, which degrades correctly:
 * `PhraseCacheFactory::storage()` catches the same exception and translates without a
 * cache. BjyAuthorize's factory has no such fallback, and it is vendor code.
 *
 * ## Where the number 8 comes from
 *
 * Nine of these tests failed on 2026-09-07, the first CI run after the Actions quota
 * reset — five in `AclCacheTest` and four in `MessengerDataIsNotTranslatedTest`. Every
 * one had landed during the outage (2026-08-14 to 2026-09-01), when each job failed in
 * two seconds with no runner assigned, so not one of them had ever executed on a
 * runner. Integration last really passed there on 2026-08-11.
 *
 * ## What this is not
 *
 * Not a licence to skip anything inconvenient. Use it only where the *container* cannot
 * be built at all, and never in place of a check the test could make itself — a test
 * that needs a database still has to catch its own connection failure, on a runner that
 * has APCu and no database.
 */
trait RequiresApcu
{
    private function requireApcu(): void
    {
        if (! extension_loaded('apcu') || ! apcu_enabled()) {
            self::markTestSkipped(
                'APCu is unusable in this process (apc.enabled / apc.enable_cli), so the '
                . 'container cannot build BjyAuthorize\Cache and everything that resolves '
                . 'through it. The capsule has APCu; a bare CI runner does not.'
            );
        }
    }
}
