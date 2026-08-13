<?php

/**
 * Paths that answer HTTP 200 `text/html` with a truncated body **today**.
 *
 * Every entry here is a broken endpoint, not a tolerated shape. The list exists so that
 * `SmokeTestCase::assertNotWedged()` can be un-skippable — a *new* wedge fails the suite
 * — while the ones that already existed stay visible and countable instead of forcing the
 * check to be deleted. Same contract as `test/Fuzz/known-form-gaps.php`: **no new gaps**,
 * compared as a set, and every line removed is a bug fixed.
 *
 * The wedge is described in `SmokeTestCase::assertNotWedged()` and in
 * `docs/strangler.md`: a throw *after* the response was assembled, with `display_errors`
 * off, so the status is 200 and the body simply stops. Nothing about it is visible to a
 * status assertion, which is why these went unnoticed.
 *
 * To retire an entry: fix the endpoint, delete the line, and the check starts guarding it.
 *
 * @return list<string> request paths, without the locale prefix a caller may add
 */

declare(strict_types=1);

return [
    /**
     * `GET /api/v1/libraries/{id}/books` with a valid bearer token — **0 bytes**, and the
     * only entry that predates the check that found it.
     *
     * Discovered 2026-08-13 by `assertNotWedged()` on its first run, and confirmed
     * present on `master` before batch 6 touched anything, so it is not a regression.
     * `ApiAuthSmokeTest::testGatedRouteAcceptsAValidToken` had been passing against it
     * since the test was written: it asserts `200` and the absence of the string
     * 'Fatal error', and an empty body satisfies both.
     *
     * What throws: `BooksApiController::getList()` hands the rows to
     * `createResponse()`, which JSON-encodes them through php-jwt, and library 3 holds a
     * row that is not valid UTF-8. The exception record from 2026-08-03 on this exact
     * route names it — `DomainException: Malformed UTF-8 characters` at
     * `Firebase\JWT\JWT::handleJsonError`. So it is a data problem the endpoint has no
     * handling for, and the fix is a change to the laminas Books API: encode with
     * `JSON_INVALID_UTF8_SUBSTITUTE`, or refuse the row with a 500 that says so.
     *
     * Its sibling `/api/v1/libraries/3/books/1` is **not** here because it fails
     * honestly: a 500 with a real error page, from
     * `LibrariesApiController::jsonSerializeDateTimeObjects()` receiving null.
     */
    '/api/v1/libraries/3/books',
];
