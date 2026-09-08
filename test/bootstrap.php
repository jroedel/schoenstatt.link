<?php

// Smoke tests talk to a running instance over HTTP only — no application
// bootstrap, no vendor autoload. This keeps them valid across the entire
// framework migration.
require __DIR__ . '/Smoke/SmokeTestCase.php';
// Shared by every smoke test that needs a signed-in session. Required here rather
// than autoloaded for the same reason as the base class: there is no autoloader in
// this suite, and PHPUnit loads each test file on its own.
require __DIR__ . '/Smoke/MagicLinkSignIn.php';
// Same reason, different suite: the integration tests do have vendor/autoload.php,
// but `test/` is not on any PSR-4 path — so a trait shared between two test classes
// is never loaded and PHPUnit dies at *collection* time with "Trait not found",
// before running anything. Adding it here rather than to composer's autoload-dev
// keeps the integration suite runnable from a --no-dev install, which is what CI does.
require __DIR__ . '/Integration/RequiresApcu.php';
