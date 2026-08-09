<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SionModel\Error\FatalErrorHandler;
use SionModel\Error\RequestContext;
use SionModel\Service\ErrorHandling;
use Symfony\Component\HttpFoundation\Request;

use function call_user_func;
use function file_exists;
use function is_callable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * A failure on a Symfony-served route is reported the way a laminas-served one is.
 *
 * `public/index.php` registers SionModel\Error\FatalErrorHandler before anything else,
 * but with no container it can only write a record to `data/exceptions` under default
 * settings — **no email, no request context**. What upgrades it to the configured
 * pipeline is `SionModel\Module::onBootstrap()`, and a ported route runs no module's
 * onBootstrap at all. So until `App\Kernel::handle()` installed a resolver of its own,
 * every failure on every ported route was silent: recorded on disk, and nobody told.
 *
 * That has no symptom by construction — the whole point of the pipeline is what happens
 * *after* the page is already broken — and the flip is what makes it matter, since it
 * puts all traffic on ~50 ported routes including the v3 API. Hence a test, and hence
 * one that reads the installed resolver rather than trusting that a line exists.
 *
 * **Why reflection.** The honest behavioural assertion would be to throw something and
 * watch the pipeline run, but the pipeline's job is to write an exception record and mail
 * it — this suite is not entitled to either. So the resolver is read out of the static it
 * is stored in and *invoked*, which is the part that can silently be wrong. It already
 * was once: `ErrorHandling` lives in `SionModel\Service`, and a plausible
 * `SionModel\Error\ErrorHandling` import made the resolver throw ServiceNotFound, get
 * swallowed by its own catch, and degrade to exactly the container-free reporting it
 * exists to replace. Nothing about that is visible from the outside, and asserting the
 * two objects come back non-null is what sees it.
 */
class PortedRouteErrorReportingTest extends TestCase
{
    protected function setUp(): void
    {
        //nothing in this process registered the handler, and a resolver left behind here
        //would be a shared static leaking into whatever runs next in the same process
        FatalErrorHandler::reset();
    }

    protected function tearDown(): void
    {
        FatalErrorHandler::reset();
    }

    /**
     * Handling a request installs the resolver, and it resolves the real reporting
     * services out of the laminas container.
     *
     * `/_health` is used deliberately: it is the one ported route that touches no laminas
     * service, so if the resolver works from *there* it works from anywhere — the
     * ServiceBridge it reaches is built by the resolver itself, on demand, and not as a
     * side effect of the route.
     */
    public function testHandlingARequestWiresTheConfiguredReportingPipeline(): void
    {
        if (! file_exists(__DIR__ . '/../../config/autoload/local.php')) {
            //checked before the container is asked rather than caught after: merely asking
            //for a database-backed service raises a warning, and failOnWarning makes that a
            //failure no later catch can undo
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $kernel   = new Kernel($this->appConfig());
        $response = $kernel->handle(Request::create('/_health'));

        self::assertSame(200, $response->getStatusCode(), '/_health should answer before anything else is judged');

        $resolver = $this->installedResolver();
        self::assertTrue(is_callable($resolver), 'App\Kernel::handle() installed no resolver');

        /** @var array{0: mixed, 1: mixed} $resolved */
        $resolved = call_user_func($resolver);

        self::assertInstanceOf(
            ErrorHandling::class,
            $resolved[0],
            'the resolver did not produce an ErrorHandling, so a failure on a ported route still '
            . 'takes the container-free path: a record on disk and no notification'
        );
        self::assertInstanceOf(
            RequestContext::class,
            $resolved[1],
            'the resolver did not produce a RequestContext, so a ported route\'s failure record '
            . 'would carry default capture settings and no identity'
        );
    }

    /**
     * And when the container itself cannot be built, the resolver says so quietly instead
     * of throwing.
     *
     * This is the property that keeps the wiring from being a net loss.
     * FatalErrorHandler::report() wraps resolving *and* reporting in a single try/catch
     * whose fallback is one line in `data/logs/bootstrap-fatal.log` — so a resolver that
     * throws costs the entire ExceptionRecord, on precisely the failures where the
     * container is the thing that broke. Returning `[null, null]` instead routes the
     * handler to its container-free record, which is what a ported route had before any
     * of this existed.
     *
     * Needs no database, and drives the case with a module that does not exist so that
     * building the ServiceBridge is guaranteed to fail.
     */
    public function testAContainerThatCannotBeBuiltDegradesRatherThanThrowing(): void
    {
        $broken            = $this->appConfig();
        $broken['modules'] = ['NoSuchModuleExistsAnywhere'];

        $kernel   = new Kernel($broken);
        $response = $kernel->handle(Request::create('/_health'));

        self::assertSame(
            200,
            $response->getStatusCode(),
            '/_health depends on no laminas service and must answer even with the modules broken'
        );

        $resolver = $this->installedResolver();
        self::assertTrue(is_callable($resolver));

        /** @var array{0: mixed, 1: mixed} $resolved */
        $resolved = call_user_func($resolver);

        self::assertNull($resolved[0], 'a resolver that cannot build ErrorHandling must report null, not throw');
        self::assertNull($resolved[1], 'likewise for RequestContext');
    }

    /**
     * The config caches are switched off here and only here, for the reason the sibling
     * integration tests give: at runtime ServiceBridge wants them, but a test run must not
     * write data/config/.
     *
     * @return array<string, mixed>
     */
    private function appConfig(): array
    {
        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return $appConfig;
    }

    private function installedResolver(): mixed
    {
        $resolver = new ReflectionProperty(FatalErrorHandler::class, 'resolver');

        return $resolver->getValue();
    }
}
