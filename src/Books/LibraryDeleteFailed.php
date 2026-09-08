<?php

declare(strict_types=1);

namespace App\Books;

use RuntimeException;

/**
 * A library delete that did not complete, and whose transaction was rolled back.
 *
 * Its own type rather than a bare RuntimeException because the controller has to tell two
 * situations apart in the response it writes: this one, where **nothing** was destroyed
 * and the visitor can safely try again, and an unexpected throwable, where it cannot make
 * that promise. {@see LibraryDelete::delete()} wraps everything it catches in this, having
 * rolled back first, which is what earns the promise.
 */
final class LibraryDeleteFailed extends RuntimeException
{
}
