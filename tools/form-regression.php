<?php

/**
 * Form-rendering regression harness for the rung-4a dependency bump.
 *
 * Captures the <form> markup of every form-rendering page and compares it
 * across a vendor-stack change, so a laminas-form 2→3 / TwbBundle swap can be
 * verified byte-for-byte instead of by eyeball. Deliberately asserts on exact
 * markup — the opposite of the smoke suite's rules — because its whole job is
 * to prove the markup did not move. Throw it away when it stops earning rent.
 *
 * Runs INSIDE the app container, like the smoke suite:
 *   docker compose exec -T app php tools/form-regression.php capture
 *   docker compose exec -T app php tools/form-regression.php compare
 *
 * capture: signs in (magic-link flow + role elevation via DB), fetches every
 *          candidate URL, extracts and normalizes all <form> blocks, writes
 *          data/form-regression/baseline/.
 * compare: same fetch into data/form-regression/current/, then diffs against
 *          the baseline. Exit 0 = no drift, 1 = drift, 2 = setup failure.
 */

declare(strict_types=1);

const BASE_URL = 'http://localhost';
const MAILPIT_URL = 'http://mailpit:8025';
const CONSENT_COOKIE = 'EU_COOKIE_LAW_CONSENT';
const EMAIL_PREFIX = 'form-regression-';
const EMAIL_DOMAIN = '@example.com';
const OUT_ROOT = __DIR__ . '/../data/form-regression';

/**
 * Pages that render forms, or should refuse to. Non-200s are captured too:
 * a page that flips from 200 to 302 after the bjy-authorize bump is exactly
 * the kind of regression this exists to catch. IDs are the lowest existing
 * row of each entity in the 2021 dump; they never change between runs.
 */
/** Fetched with a consent-only jar, no identity: the login form 302s away from signed-in visitors. */
const ANONYMOUS_URLS = [
    '/en/user/login',
];

const CANDIDATE_URLS = [
    // Schoenstatt
    '/en/persons/create',
    '/en/persons/1/edit',
    '/en/persons/1/suggest',
    '/en/roles/create',
    '/en/roles/1/edit',
    '/en/assignments/create',
    '/en/assignments/2/edit',
    '/en/assignments/advanced-search',
    '/en/associations/create',
    // Books / libraries / publications / texts / dictionary / blog
    '/en/books/create/1',
    '/en/books/18370/edit',
    '/en/libraries/create',
    '/en/libraries/1/edit',
    '/en/collections/create/1',
    '/en/collections/1/edit',
    '/en/literature/create',
    '/en/SL200001L/edit',   // publication 1 via its schoenstatt.link short id
    '/en/texts/create',
    '/en/SL400001T/edit',   // text 1 via its schoenstatt.link short id
    '/en/dictionary/create',
    '/en/blog/create',
    // JUser
    '/en/users/create',
    '/en/users/5/edit',
    '/en/users/5/delete',
    // JTranslate
    '/en/admin/translations',
];

exit(main($argv));

function main(array $argv): int
{
    $mode = $argv[1] ?? '';
    if (! in_array($mode, ['capture', 'compare', 'probe'], true)) {
        fwrite(STDERR, "Usage: php tools/form-regression.php capture|compare|probe <url>\n");
        return 2;
    }

    if ($mode === 'probe') {
        return probe($argv[2] ?? '');
    }

    $jar = tempnam(sys_get_temp_dir(), 'form-regression-cookies-');
    file_put_contents($jar, netscapeJarWithConsent());
    $anonymousJar = tempnam(sys_get_temp_dir(), 'form-regression-anon-');
    file_put_contents($anonymousJar, netscapeJarWithConsent());

    try {
        $pages = capturePages($anonymousJar, ANONYMOUS_URLS);
        signInAsAdministrator($jar);
        $pages += capturePages($jar, CANDIDATE_URLS);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'SETUP FAILED: ' . $e->getMessage() . "\n");
        return 2;
    } finally {
        @unlink($jar);
        @unlink($anonymousJar);
    }

    if ($mode === 'capture') {
        writePages(OUT_ROOT . '/baseline', $pages);
        printf("Baseline: %d pages captured to data/form-regression/baseline/\n", count($pages));
        foreach ($pages as $slug => $page) {
            printf("  %-45s %s  %d form(s)\n", $page['url'], $page['status'], $page['formCount']);
        }
        return 0;
    }

    writePages(OUT_ROOT . '/current', $pages);
    return comparePages(OUT_ROOT . '/baseline', $pages);
}

/** Sign in and dump one page's raw body — for inspecting error pages by hand. */
function probe(string $url): int
{
    if ($url === '') {
        fwrite(STDERR, "probe needs a URL\n");
        return 2;
    }
    $jar = tempnam(sys_get_temp_dir(), 'form-regression-probe-');
    file_put_contents($jar, netscapeJarWithConsent());
    try {
        signInAsAdministrator($jar);
        $response = httpGet($url, $jar);
    } finally {
        @unlink($jar);
    }
    fwrite(STDERR, sprintf("STATUS: %d  REDIRECT: %s\n", $response['status'], $response['redirect']));
    echo $response['body'];
    return 0;
}

// ---------------------------------------------------------------- sign-in

function signInAsAdministrator(string $jar): void
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
        'email' => $email,
        'redirect' => '',
        'security' => $m[1],
        'submit' => 'Send me a sign-in link',
    ]);
    if ($post['status'] !== 200) {
        throw new RuntimeException("sign-in POST returned {$post['status']}");
    }

    $verifyPath = awaitVerifyPath($email);

    // Elevate BEFORE redeeming the link: BjyAuthorize reads the roles when the
    // session identity is established, so the role has to exist first.
    elevateToAdministrator($email);

    $verify = httpGet($verifyPath, $jar);
    if ($verify['status'] !== 302) {
        throw new RuntimeException("verify link returned {$verify['status']}, expected 302");
    }
}

function awaitVerifyPath(string $email): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $search = json_decode(rawHttp(MAILPIT_URL . '/api/v1/search?query=' . rawurlencode('to:' . $email)), true);
        if (! empty($search['messages'][0]['ID'])) {
            $message = json_decode(rawHttp(MAILPIT_URL . '/api/v1/message/' . rawurlencode($search['messages'][0]['ID'])), true);
            if (preg_match('#https?://\S+(/[a-z]{2}/user/verify\?token=[0-9a-f]+)#', $message['Text'] ?? '', $m)) {
                return $m[1];
            }
        }
        usleep(500000);
    }
    throw new RuntimeException("no sign-in mail arrived for $email");
}

function elevateToAdministrator(string $email): void
{
    $pdo = new PDO('mysql:host=db;dbname=ourlink_db1;charset=utf8mb4', 'schoenstatt', 'schoenstatt', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    // Every role, not just 'administrator': the route guards check the
    // domain-specific roles (sch_moderator, lib_administrator, ...) and this
    // account exists only to see every form once.
    $insert = $pdo->prepare(
        'INSERT INTO user_role_linker (user_id, role_id, create_datetime)
         SELECT u.user_id, r.id, NOW() FROM user u JOIN user_role r
         WHERE u.email = :email
           AND NOT EXISTS (SELECT 1 FROM user_role_linker l WHERE l.user_id = u.user_id AND l.role_id = r.id)'
    );
    $insert->execute(['email' => $email]);
    $pdo->prepare('UPDATE user SET state = 1 WHERE email = :email')->execute(['email' => $email]);
}

// ---------------------------------------------------------------- capture

/**
 * @param string[] $urls
 * @return array<string, array{url: string, status: int, formCount: int, content: string}>
 */
function capturePages(string $jar, array $urls): array
{
    $pages = [];
    foreach ($urls as $url) {
        $response = httpGet($url, $jar);
        $forms = $response['status'] === 200 ? extractForms($response['body']) : [];
        $header = sprintf("URL: %s\nSTATUS: %d\nREDIRECT: %s\nFORMS: %d\n", $url, $response['status'], normalize($response['redirect']), count($forms));
        $pages[slugify($url)] = [
            'url' => $url,
            'status' => $response['status'],
            'formCount' => count($forms),
            'content' => $header . "\n" . implode("\n<!-- ==== next form ==== -->\n", $forms) . "\n",
        ];
    }
    return $pages;
}

/** @return string[] normalized outerHTML of every <form> on the page, document order */
function extractForms(string $html): array
{
    $document = new DOMDocument();
    // The pages are HTML5; DOMDocument whines about modern tags. Silence it —
    // parse errors that matter will show up as markup drift anyway.
    @$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $forms = [];
    foreach ($document->getElementsByTagName('form') as $form) {
        $forms[] = normalize($document->saveHTML($form));
    }
    return $forms;
}

/** Strip the per-session bits so only real markup change diffs. */
function normalize(string $markup): string
{
    // CSRF tokens: laminas-form Csrf elements render as 32hex or 32hex-32hex
    // (hash + salt); magic-link tokens are 64 hex. All appear as input values.
    $markup = preg_replace('/value="[0-9a-f]{32}(-?[0-9a-f]{32})?"/', 'value="{{TOKEN}}"', $markup);
    // CSRF/session ids that leak into URLs (none known today; cheap insurance)
    return preg_replace('/PHPSESSID=[0-9a-z]+/i', 'PHPSESSID={{SESSION}}', $markup);
}

function slugify(string $url): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($url)), '-');
}

/** @param array<string, array{url: string, status: int, formCount: int, content: string}> $pages */
function writePages(string $dir, array $pages): void
{
    if (! is_dir($dir) && ! mkdir($dir, 0777, true)) {
        throw new RuntimeException("cannot create $dir");
    }
    foreach (glob($dir . '/*.html') ?: [] as $stale) {
        unlink($stale);
    }
    foreach ($pages as $slug => $page) {
        file_put_contents($dir . '/' . $slug . '.html', $page['content']);
    }
}

/** @param array<string, array{url: string, status: int, formCount: int, content: string}> $pages */
function comparePages(string $baselineDir, array $pages): int
{
    if (! is_dir($baselineDir)) {
        fwrite(STDERR, "No baseline at $baselineDir — run capture first.\n");
        return 2;
    }
    $drift = 0;
    foreach ($pages as $slug => $page) {
        $baselineFile = $baselineDir . '/' . $slug . '.html';
        if (! is_file($baselineFile)) {
            printf("NEW      %s (no baseline)\n", $page['url']);
            $drift++;
            continue;
        }
        if (file_get_contents($baselineFile) === $page['content']) {
            printf("OK       %s\n", $page['url']);
        } else {
            printf("DRIFT    %s\n", $page['url']);
            $drift++;
        }
    }
    if ($drift > 0) {
        printf(
            "\n%d page(s) drifted. Inspect with:\n  diff -ru data/form-regression/baseline data/form-regression/current\n",
            $drift
        );
        return 1;
    }
    echo "\nNo drift: every page renders identically to the baseline.\n";
    return 0;
}

// ---------------------------------------------------------------- HTTP

/** @return array{status: int, redirect: string, body: string} */
function httpGet(string $path, string $jar): array
{
    return httpRequest('GET', $path, $jar);
}

/**
 * @param array<string, string>|null $postFields
 * @return array{status: int, redirect: string, body: string}
 */
function httpRequest(string $method, string $path, string $jar, ?array $postFields = null): array
{
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'schoenstatt-form-regression',
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept-Language: en'],
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
    ]);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException("$method $path failed: " . curl_error($ch));
    }
    $result = [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'redirect' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
        'body' => (string) $body,
    ];
    return $result;
}

function rawHttp(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $body = curl_exec($ch);
    return is_string($body) ? $body : '';
}

function netscapeJarWithConsent(): string
{
    return "# Netscape HTTP Cookie File\n"
        . implode("\t", ['localhost', 'FALSE', '/', 'FALSE', '2147483647', CONSENT_COOKIE, 'true'])
        . "\n";
}
