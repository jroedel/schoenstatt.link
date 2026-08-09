<?php

/**
 * Two-front-controller, five-locale rendering diff for a route being ported.
 *
 * This is the procedure docs/strangler.md calls "Verifying a port against
 * production, across every locale", turned into a tool. Batch 3 ran it by hand and
 * it is what found the translation defect: the ported pages were diffed
 * byte-for-byte on `/en/…` and passed, while all four other languages rendered
 * English source text. In English a missing translation *is* the source string, so
 * the defect showed up only as a capitalisation nobody would look at twice.
 *
 * Two cheaper checks are both insufficient and that is why this exists:
 *
 * - The capsule cannot compare front controllers by itself. Its vhost sets
 *   SYMFONY_KERNEL=1 unconditionally, so a capsule-only "before and after"
 *   compares Symfony with Symfony. Appending `SetEnv SYMFONY_KERNEL 0` to
 *   public/.htaccess forces laminas (AllowOverride All, and SetEnv beats the
 *   canary's SetEnvIf) — that is the *only* edit the procedure needs, and it is
 *   removed again before the second capture.
 * - English proves almost nothing, per above.
 *
 * Usage, inside the app container:
 *
 *     # 1. append `SetEnv SYMFONY_KERNEL 0` to public/.htaccess
 *     docker compose exec -T app php tools/port-baseline.php capture laminas
 *     # 2. remove that line again
 *     docker compose exec -T app php tools/port-baseline.php capture symfony
 *     docker compose exec -T app php tools/port-baseline.php compare laminas symfony
 *
 * Exit 0 = every response identical once normalized, 1 = drift, 2 = setup failure.
 *
 * ## Both identities, deliberately
 *
 * Every URL is fetched twice: once anonymously and once as an account holding every
 * role. A ported page renders permission-gated markup — the moderator table, the
 * edit pencils — and an anonymous-only comparison would prove nothing about any of
 * it. The signed-in half is also the only way a guarded route is compared at all
 * rather than as its 302.
 *
 * ## What is normalized, and why each one is not cheating
 *
 * Each rule below erases something that differs between two runs of the *same*
 * front controller. Nothing here erases a difference between the two renderings.
 *
 * 1. **302 bodies.** laminas renders the entire sign-in page into the body of its
 *    302 (~9 KB); Symfony's RedirectResponse sends a 378-byte meta-refresh stub.
 *    Same status, same Location, and nothing reads a 302 body. So a non-200 is
 *    compared on status and Location only.
 * 2. **HTML entities.** Twig escapes a `"` inside a translated string to `&quot;`;
 *    the laminas .phtml echoes it raw. Identical in a browser, and Twig's is the
 *    safer of the two.
 * 3. **Visit counters.** SionTable::registerVisit() INSERTs a row on every request,
 *    so /dictionary/es reports a higher "Total views" on the second capture than on
 *    the first purely because the first happened. The digits go; the markup around
 *    them stays, which is what would catch the counter disappearing.
 * 4. **The CSP nonce** and **the throwaway account's address**, both of which are
 *    per-run by construction.
 * 5. **The language chooser's flags.** `flag-icon-gb` or `flag-icon-us` for English,
 *    `ar`/`cl`/`mx`/`es` for Spanish: the chooser picks at random among the countries
 *    that speak each language, on **both** front controllers, so two fetches of one URL
 *    from one front controller already disagree. Measured 2026-08-08 — the anonymous
 *    and signed-in captures of /en/developers came back with different flags.
 * 6. **Whitespace between markup.** Runs of space/tab/newline collapse to one space.
 * 7. **Four fixed chrome differences** that exist on *every* ported page and have
 *    nothing to do with the page: `<head>` in full, the language chooser, every
 *    `application/ld+json` block, `<body >`'s stray space, and Twig's added
 *    `aria-current="page"`.
 *
 * ## Why rules 6 and 7, and what they cost
 *
 * `templates/layout.html.twig` is a *reproduction* of
 * `module/Application/view/layout/layout.phtml`, not a byte copy of it. Its
 * indentation, its `<head>` ordering, its JSON-LD encoder and its navbar markup were
 * all written afresh, so the two front controllers have never emitted the same bytes
 * for the chrome, on any ported route. Measured on 2026-08-08, before this batch
 * touched anything: /en/developers, /en/privacy and /en/shrines all differed from their
 * laminas renderings, and *only* in the ways rules 5, 6 and 7 name. **That corrects
 * docs/strangler.md**, which claims batch 3 came to "65 of 65 responses identical" —
 * as whole documents they were not, and no later batch can make them so.
 *
 * So the comparison is deliberately scoped to what porting a page actually owns:
 *
 *     kept      <title>, the breadcrumb trail, the navbar (including which item is
 *               active — a ported route feeds that from its own name), the flash
 *               region, and the entire page body
 *     dropped   <head>, the language chooser, the JSON-LD blocks, indentation
 *
 * The navbar is deliberately *not* dropped even though it is chrome: `current_route()`
 * reads the ported route's name, so getting that name wrong silently stops the current
 * page lighting up, and this is the check that sees it.
 *
 * `&nbsp;` survives all of it — it decodes to U+00A0 under rule 2, which is why rule 6
 * spells out its character class instead of writing `\s`, and the role labels on /roles
 * are built out of it.
 */

declare(strict_types=1);

const BASE_URL       = 'http://localhost';
const MAILPIT_URL    = 'http://mailpit:8025';
/**
 * The GDPR consent cookie, name **and value**, as Application\View\GdprStrategy
 * reads it: `'true' === $_COOKIE['EU_COOKIE_LAW_CONSENT']`.
 *
 * The value is the part worth stating. tools/form-regression.php sends this cookie
 * with the value `1`, which is not `'true'`, so the strategy sees an unconsented
 * visitor and rewrites every auth route to the sign-in-no-cookies explainer — a page
 * with no form on it. That tool's sign-in therefore cannot work, and its failure
 * mode is the misleading "no CSRF token on the login form". Measured 2026-08-08,
 * after making the same mistake here.
 */
const CONSENT_COOKIE = 'EU_COOKIE_LAW_CONSENT';
const CONSENT_VALUE  = 'true';
const EMAIL_PREFIX   = 'port-baseline-';
const EMAIL_DOMAIN   = '@example.com';
const OUT_ROOT       = __DIR__ . '/../data/port-baseline';

/** The five configured aliases, i.e. every prefix a visitor can actually be on. */
const LOCALES = ['en', 'es', 'de', 'pt', 'it'];

/**
 * Paths under test, without a locale prefix. Each is fetched once per locale and
 * once bare — the bare form is not decoration: SlmLocale answers it with a redirect
 * to the negotiated language rather than serving the page twice, and a ported
 * controller has to reproduce that from the absence of the `_locale` attribute.
 * Getting it wrong serves the same body at two URLs, which no prefixed-only
 * comparison would notice.
 */
const PATHS = [
    // batch 4 — the public browse surface
    '/music',
    '/timeline',
    '/dictionary',
    '/dictionary/es',
    '/dictionary/pt',
    '/literature/150-preguntas-sobre-schoenstatt',
    // batch 4 — restricted indexes
    '/associations',
    '/roles',
    '/libraries',
    // earlier batches, re-compared because every port re-enters the same layout,
    // the same translator and the same authorization listener
    '/',
    '/shrines',
    '/wayside-shrines',
    '/developers',
    '/acknowledgements',
    '/privacy',
    '/shrines/submitting-photos',
    '/admin',
    '/sm/data-problems',
    '/sm/view-changes',
    //Edge cases, and **last on purpose**: an unknown dictionary language is a branch a
    //ported controller has to reproduce. Its laminas twin sets a flash message, and a
    //flash is read and cleared by the *next* page rendered with the same session — so
    //anywhere but last, it decorates an unrelated page with a message on one front
    //controller and not the other. Diagnosed after exactly that.
    '/dictionary/xx',
];

exit(main($argv));

/** @param list<string> $argv */
function main(array $argv): int
{
    $mode = $argv[1] ?? '';

    if ($mode === 'capture') {
        $name = $argv[2] ?? '';
        if ($name === '' || ! preg_match('/^[a-z0-9-]+$/', $name)) {
            fwrite(STDERR, "capture needs a name, e.g. `capture laminas`\n");
            return 2;
        }
        return capture($name);
    }

    if ($mode === 'compare') {
        $left  = $argv[2] ?? '';
        $right = $argv[3] ?? '';
        if ($left === '' || $right === '') {
            fwrite(STDERR, "compare needs two capture names, e.g. `compare laminas symfony`\n");
            return 2;
        }
        return compareCaptures($left, $right);
    }

    if ($mode === 'show') {
        return show($argv[2] ?? '', $argv[3] ?? '');
    }

    fwrite(
        STDERR,
        "Usage: php tools/port-baseline.php capture <name> | compare <name> <name> | show <name> <file>\n"
    );
    return 2;
}

// ------------------------------------------------------------------- capture

function capture(string $name): int
{
    $anonymousJar = tempnam(sys_get_temp_dir(), 'port-baseline-anon-');
    $signedInJar  = tempnam(sys_get_temp_dir(), 'port-baseline-auth-');
    file_put_contents($anonymousJar, netscapeJarWithConsent());
    file_put_contents($signedInJar, netscapeJarWithConsent());

    try {
        $account = signInWithEveryRole($signedInJar);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'SETUP FAILED: ' . $e->getMessage() . "\n");
        @unlink($anonymousJar);
        @unlink($signedInJar);
        return 2;
    }

    $dir = OUT_ROOT . '/' . $name;
    resetDir($dir);
    //Normalization happens at *compare* time, not here, so a capture is the raw
    //response and a change to a rule below never costs another capture — which
    //matters because taking the laminas one means editing public/.htaccess. The one
    //thing compare() cannot recover on its own is which throwaway account this run
    //used, so it is recorded beside the responses.
    file_put_contents($dir . '/ACCOUNT', $account . "\n");

    $count = 0;
    foreach (urls() as $url) {
        foreach (['anonymous' => $anonymousJar, 'signed-in' => $signedInJar] as $identity => $jar) {
            //An unprefixed path is answered by a locale *negotiation*, and SlmLocale
            //negotiates from the slm_locale cookie first. That cookie is set by every
            //prefixed request, so without this the answer depends on which locale
            //happened to be fetched last — /music redirected to /it/music purely
            //because `it` came last in the loop above. Dropping the cookie first makes
            //the negotiation depend on the request alone, which is the thing the two
            //front controllers actually have to agree about. The session cookie stays,
            //so the signed-in identity survives.
            if (! isLocalePrefixed($url)) {
                dropLocaleCookie($jar);
            }
            $response = httpGet($url, $jar);
            file_put_contents(
                $dir . '/' . slugify($identity . $url) . '.txt',
                render($url, $identity, $response)
            );
            $count++;
        }
    }

    @unlink($anonymousJar);
    @unlink($signedInJar);

    printf(
        "Captured %d responses (%d paths x %d locale forms x 2 identities) to data/port-baseline/%s/\n",
        $count,
        count(PATHS),
        count(LOCALES) + 1,
        $name
    );
    printf("Signed-in account: %s\n", $account);

    return 0;
}

function isLocalePrefixed(string $url): bool
{
    return (bool) preg_match('#^/(' . implode('|', LOCALES) . ')(/|$)#', $url);
}

/** Remove slm_locale from a Netscape jar in place, leaving every other cookie. */
function dropLocaleCookie(string $jar): void
{
    $lines = file($jar, FILE_IGNORE_NEW_LINES) ?: [];
    $kept  = array_filter($lines, static fn (string $line): bool => ! str_contains($line, "\tslm_locale\t"));
    file_put_contents($jar, implode("\n", $kept) . "\n");
}

/** @return list<string> every path in every locale form, plus the bare form */
function urls(): array
{
    $urls = [];
    foreach (PATHS as $path) {
        $urls[] = $path;
        foreach (LOCALES as $locale) {
            //'/' is the one path whose prefixed form is '/en/' rather than '/en'
            $urls[] = '/' . $locale . ($path === '/' ? '/' : $path);
        }
    }
    return $urls;
}

/**
 * One captured response as a comparable document.
 *
 * The body is stored **raw**; normalization is compare()'s job. A non-200 body is
 * dropped here rather than at compare time because rule 1 is about what a 302 *is*,
 * not about how two of them are compared: laminas renders a whole sign-in page into
 * one and keeping 9 KB of it per locale per identity is 40 MB of noise on disk.
 *
 * @param array{status: int, redirect: string, body: string} $response
 */
function render(string $url, string $identity, array $response): string
{
    $header = sprintf(
        "URL: %s\nIDENTITY: %s\nSTATUS: %d\nLOCATION: %s\n",
        $url,
        $identity,
        $response['status'],
        $response['redirect']
    );

    if ($response['status'] !== 200) {
        return $header . "BODY: not compared (see rule 1 in tools/port-baseline.php)\n";
    }

    return $header . "\n" . $response['body'];
}

/** Erase what differs between two runs of the same front controller. */
function normalize(string $html, string $account): string
{
    // rule 2 — Twig escapes what the .phtml echoed raw
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // rule 3 — registerVisit() INSERTs on every request, so these climb by
    // themselves. The labels stay, so the counters vanishing still diffs.
    $html = preg_replace(
        '/(Total views|Views this month|Vistas totales|Visitas totales)(<\/strong>)?:\s*\d+/u',
        '$1$2: {{VISITS}}',
        $html
    );

    // rule 4 — per-run by construction
    $html = preg_replace('/nonce="[^"]*"/', 'nonce="{{NONCE}}"', $html);
    $html = str_replace($account, '{{ACCOUNT}}', $html);
    $html = preg_replace('/' . preg_quote(EMAIL_PREFIX, '/') . '\d+/', '{{ACCOUNT}}', $html);

    // rule 5 — the chooser picks a random country per language, on both sides. Its
    // whole markup goes below anyway; this also covers flags elsewhere on a page.
    $html = preg_replace('/flag-icon-[a-z]{2}\b/', 'flag-icon-{{FLAG}}', $html);

    // rule 7 — the fixed chrome differences. <title> is pulled out first because it is
    // the one part of <head> a page owns, and it is compared.
    preg_match('#<title>(.*?)</title>#s', $html, $title);
    $html = preg_replace('#<head>.*?</head>#s', '', $html) ?? $html;
    $html = preg_replace('#<div class="btn-group pull-right">.*?</ul>\s*</div>#s', '', $html) ?? $html;
    $html = preg_replace('#<script type=.application/ld\+json.>.*?</script>#s', '', $html) ?? $html;
    $html = str_replace('<body >', '<body>', $html);
    $html = str_replace(' aria-current="page"', '', $html);
    $html = 'TITLE: ' . ($title[1] ?? '') . "\n" . $html;

    // rule 6 — the two layouts are not whitespace-identical and never were. Two steps:
    // collapse every run to one space, then drop the space *between two tags*
    // altogether, since `</div><h3>` and `</div> <h3>` are the same document and the
    // two layouts disagree about it constantly.
    //
    // Whitespace between *text* and a tag is deliberately kept. That is where a real
    // difference would show — a missing space before a link, a run-together sentence —
    // and collapsing it too would be the point at which this stopped checking anything.
    //
    // The character class is spelled out rather than written `\s` so that U+00A0
    // (`&nbsp;`, already decoded by rule 2) is *not* collapsed: it is real content on
    // the pages that use it, and /roles is built out of it.
    $html = preg_replace('/[ \t\r\n]+/', ' ', $html) ?? $html;

    return trim(preg_replace('/> </', '><', $html) ?? '');
}

// ------------------------------------------------------------------- compare

function compareCaptures(string $left, string $right): int
{
    $leftDir  = OUT_ROOT . '/' . $left;
    $rightDir = OUT_ROOT . '/' . $right;
    foreach ([$leftDir, $rightDir] as $dir) {
        if (! is_dir($dir)) {
            fwrite(STDERR, "no such capture: $dir\n");
            return 2;
        }
    }

    $files = array_map('basename', glob($leftDir . '/*.txt') ?: []);
    sort($files);
    if ([] === $files) {
        fwrite(STDERR, "capture $left is empty\n");
        return 2;
    }

    $leftAccount  = accountOf($leftDir);
    $rightAccount = accountOf($rightDir);

    $same = 0;
    $drift = [];
    foreach ($files as $file) {
        $a = file_get_contents($leftDir . '/' . $file);
        $b = @file_get_contents($rightDir . '/' . $file);
        if ($b === false) {
            $drift[$file] = 'missing from ' . $right;
            continue;
        }
        $a = comparable($a, $leftAccount);
        $b = comparable($b, $rightAccount);
        if ($a === $b) {
            $same++;
            continue;
        }
        $drift[$file] = sprintf('%d bytes vs %d bytes', strlen($a), strlen($b));
    }

    printf("%d of %d responses identical\n", $same, count($files));
    if ([] === $drift) {
        return 0;
    }

    printf("\n%d differ:\n", count($drift));
    foreach ($drift as $file => $why) {
        printf("  %-70s %s\n", $file, $why);
    }
    printf("\ndiff -u %s/<file> %s/<file>\n", $leftDir, $rightDir);
    printf("(raw captures; run `php tools/port-baseline.php show <name> <file>` for the normalized form)\n");

    return 1;
}

/**
 * One captured file reduced to what is actually compared: the header verbatim, and
 * the body normalized. Splitting on the blank line after the header is safe because
 * render() writes exactly one.
 */
function comparable(string $captured, string $account): string
{
    $parts = explode("\n\n", $captured, 2);
    if (! isset($parts[1])) {
        return $captured;
    }

    return $parts[0] . "\n\n" . normalize($parts[1], $account);
}

function accountOf(string $dir): string
{
    $account = @file_get_contents($dir . '/ACCOUNT');

    //an older capture has no ACCOUNT file; a name nothing can match is the safe
    //answer, since str_replace of '' would corrupt every byte of the document
    return false === $account ? '{{NO-ACCOUNT-RECORDED}}' : trim($account);
}

/** Print one captured response in its normalized form, for eyeballing a diff. */
function show(string $name, string $file): int
{
    $dir  = OUT_ROOT . '/' . $name;
    $path = $dir . '/' . basename($file);
    if (! is_file($path)) {
        fwrite(STDERR, "no such capture file: $path\n");
        return 2;
    }
    echo comparable((string) file_get_contents($path), accountOf($dir)), "\n";

    return 0;
}

// ------------------------------------------------------------------- sign-in

/**
 * A throwaway account holding every role in `user_role`, signed in over HTTP.
 *
 * Every role rather than a chosen few: this account exists to see the most
 * privileged rendering of every page at once, and picking roles per page would mean
 * maintaining a second copy of the ACL here. Roles are granted by INSERT before the
 * magic link is redeemed, which is convenience and not a requirement —
 * ZfcUserZendDbPlusSelfAsRole::getIdentityRoles() selects from user_role_linker on
 * every request and bjyauthorize.cache_enabled is false.
 *
 * @return string the account's address, needed by normalize()
 */
function signInWithEveryRole(string $jar): string
{
    $email = EMAIL_PREFIX . time() . EMAIL_DOMAIN;

    $form = httpGet('/en/user/login', $jar);
    if ($form['status'] !== 200) {
        throw new RuntimeException("GET /en/user/login returned {$form['status']} — is the app up?");
    }
    if (! preg_match('/name="security"[^>]*value="([^"]+)"/', $form['body'], $m)) {
        throw new RuntimeException('no CSRF token on the login form');
    }

    $post = httpRequest('POST', '/en/user/login', $jar, [
        'email'    => $email,
        'redirect' => '',
        'security' => $m[1],
        'submit'   => 'Send me a sign-in link',
    ]);
    if ($post['status'] !== 200) {
        throw new RuntimeException("sign-in POST returned {$post['status']}");
    }

    $verifyPath = awaitVerifyPath($email);
    grantEveryRole($email);

    $verify = httpGet($verifyPath, $jar);
    if ($verify['status'] !== 302) {
        throw new RuntimeException("verify link returned {$verify['status']}, expected 302");
    }

    return $email;
}

function awaitVerifyPath(string $email): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $search = json_decode(
            rawHttp(MAILPIT_URL . '/api/v1/search?query=' . rawurlencode('to:' . $email)),
            true
        );
        if (! empty($search['messages'][0]['ID'])) {
            $message = json_decode(
                rawHttp(MAILPIT_URL . '/api/v1/message/' . rawurlencode($search['messages'][0]['ID'])),
                true
            );
            if (preg_match('#https?://\S+(/[a-z]{2}/user/verify\?token=[0-9a-f]+)#', $message['Text'] ?? '', $m)) {
                return $m[1];
            }
        }
        usleep(500000);
    }
    throw new RuntimeException("no sign-in mail arrived for $email");
}

function grantEveryRole(string $email): void
{
    $pdo = new PDO('mysql:host=db;dbname=ourlink_db1;charset=utf8mb4', 'schoenstatt', 'schoenstatt', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->prepare(
        'INSERT INTO user_role_linker (user_id, role_id, create_datetime)
         SELECT u.user_id, r.id, NOW() FROM user u JOIN user_role r
         WHERE u.email = :email
           AND NOT EXISTS (SELECT 1 FROM user_role_linker l WHERE l.user_id = u.user_id AND l.role_id = r.id)'
    )->execute(['email' => $email]);
    $pdo->prepare('UPDATE user SET state = 1 WHERE email = :email')->execute(['email' => $email]);
}

// ---------------------------------------------------------------------- http

/** @return array{status: int, redirect: string, body: string} */
function httpGet(string $url, string $jar): array
{
    return httpRequest('GET', $url, $jar);
}

/**
 * @param array<string, string>|null $post
 * @return array{status: int, redirect: string, body: string}
 */
function httpRequest(string $method, string $url, string $jar, ?array $post = null): array
{
    $ch = curl_init(BASE_URL . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 120,
        //Not decoration. ext/curl sends no User-Agent unless told to, and
        //SionTable::registerVisit() reads $_SERVER['HTTP_USER_AGENT'] unguarded — so a
        //capture without this one line renders every visit-registering page (the
        //dictionaries) with three PHP warnings printed above the doctype,
        //and the second and third of those are "Cannot modify header information",
        //i.e. the page also loses its Content-Security-Policy. That is a real
        //robustness bug in SionModel, not an artefact of this tool; the tool just
        //stops triggering it, because a capture is supposed to look like a browser.
        CURLOPT_USERAGENT      => 'schoenstatt-port-baseline',
    ]);
    if (null !== $post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    //no curl_close(): a no-op since PHP 8.0 and deprecated in 8.5, which the
    //capsule runs. The handle is freed when $ch goes out of scope.
    $body     = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);

    if (false === $body) {
        throw new RuntimeException("request to $url failed");
    }

    return [
        'status'   => $status,
        //the host is the same on both captures, but strip it anyway so a capture
        //taken through a different base URL still compares
        'redirect' => str_replace(BASE_URL, '', $redirect),
        'body'     => $body,
    ];
}

function rawHttp(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);

    return false === $body ? '' : $body;
}

/**
 * A cookie jar that has already accepted cookies.
 *
 * Without it both GDPR strategies call header_remove('Set-Cookie') and no session
 * can ever be established — the sign-in below would loop silently.
 */
function netscapeJarWithConsent(): string
{
    return "# Netscape HTTP Cookie File\n"
        . implode(
            "\t",
            //domain, include-subdomains, path, secure, expires, name, value
            ['localhost', 'FALSE', '/', 'FALSE', '2147483647', CONSENT_COOKIE, CONSENT_VALUE]
        ) . "\n";
}

// --------------------------------------------------------------------- files

function resetDir(string $dir): void
{
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.txt') ?: [] as $file) {
            unlink($file);
        }
        return;
    }
    if (! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
        throw new RuntimeException("could not create $dir");
    }
}

function slugify(string $url): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $url) ?? '', '-');

    return '' === $slug ? 'root' : $slug;
}
