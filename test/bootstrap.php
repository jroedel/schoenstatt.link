<?php

// Smoke tests talk to a running instance over HTTP only — no application
// bootstrap, no vendor autoload. This keeps them valid across the entire
// framework migration.
require __DIR__ . '/Smoke/SmokeTestCase.php';
// Shared by every smoke test that needs a signed-in session. Required here rather
// than autoloaded for the same reason as the base class: there is no autoloader in
// this suite, and PHPUnit loads each test file on its own.
require __DIR__ . '/Smoke/MagicLinkSignIn.php';
