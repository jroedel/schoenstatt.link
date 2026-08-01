<?php

// Smoke tests talk to a running instance over HTTP only — no application
// bootstrap, no vendor autoload. This keeps them valid across the entire
// framework migration.
require __DIR__ . '/Smoke/SmokeTestCase.php';
