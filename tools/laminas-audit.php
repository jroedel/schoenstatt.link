<?php

/**
 * For every installed `laminas/*` package: does this application's own code use its
 * namespace, and does composer.json declare it?
 *
 * Written after dropping `laminas-captcha` in step 1a silently dropped `laminas-session`
 * too — captcha was the only package requiring it, and we had never declared it, though
 * `App\Http\SessionListener` and `SionModel\Messaging\FlashMessages` use it on every
 * request. An undeclared dependency is invisible until something else stops pulling it in,
 * and then it leaves in a lock file nobody reads closely.
 *
 * `USED / NOT DECLARED` is the row to act on: add it to composer.json.
 * `- / NOT DECLARED` is correct and expected — a transitive nothing of ours touches.
 *
 * Comments are stripped before matching, so prose about a package is not a use of it.
 * The three submodules are separate composer packages and are excluded; they declare
 * their own requirements (and have their own gaps — SionModel used laminas-math without
 * declaring it, which is what made the same trap bite there).
 *
 * Run it from the project root, in the capsule:
 *     docker compose exec -T app php tools/laminas-audit.php
 */
$lock = json_decode(file_get_contents('composer.lock'), true);
$declared = json_decode(file_get_contents('composer.json'), true)['require'];
$installed = [];
foreach ($lock['packages'] as $p) {
    if (str_starts_with($p['name'], 'laminas/')) $installed[$p['name']] = $p['version'];
}
// namespace for each package, from its autoload
$ns = [];
foreach ($lock['packages'] as $p) {
    if (!str_starts_with($p['name'], 'laminas/')) continue;
    foreach ($p['autoload']['psr-4'] ?? [] as $prefix => $_) $ns[$p['name']][] = rtrim($prefix, '\\');
}
// our code, comments stripped. module/{SionModel,JUser,JTranslate} are separate packages.
$roots = ['src', 'config', 'public', 'bin', 'tools', 'test', 'module/Application', 'module/Books', 'module/Schoenstatt'];
$code = '';
foreach ($roots as $root) {
    if (is_file($root)) { $files = [new SplFileInfo($root)]; }
    else { $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)); }
    foreach ($files as $file) {
        if (!$file->isFile()) continue;
        if (!in_array($file->getExtension(), ['php', ''], true)) continue;
        $src = file_get_contents($file->getPathname());
        if (!str_contains($src, '<?php')) continue;
        foreach (token_get_all($src) as $t) {
            if (is_array($t)) { if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue; $code .= $t[1]; }
            else $code .= $t;
        }
    }
}
$rows = [];
foreach ($installed as $name => $version) {
    $used = false;
    foreach ($ns[$name] ?? [] as $prefix) {
        if ($prefix !== '' && str_contains($code, $prefix . '\\')) { $used = true; break; }
    }
    $rows[] = [$name, $version, $used ? 'USED' : '-', isset($declared[$name]) ? 'declared' : 'NOT DECLARED'];
}
usort($rows, fn($a, $b) => [$a[2] === 'USED' ? 0 : 1, $a[3]] <=> [$b[2] === 'USED' ? 0 : 1, $b[3]]);
printf("%-46s %-10s %-6s %s\n", 'PACKAGE', 'VERSION', 'USED', 'IN composer.json');
foreach ($rows as $r) printf("%-46s %-10s %-6s %s\n", ...$r);
