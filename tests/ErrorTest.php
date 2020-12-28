<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\Error;

/** @internal */
final class ErrorTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function inheritance(): void
    {
        self::assertInstanceOf(Throwable::class, new Error());
    }
}
