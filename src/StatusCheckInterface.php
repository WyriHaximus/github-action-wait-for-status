<?php

declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus;

use React\Promise\PromiseInterface;

interface StatusCheckInterface
{
    public function refresh(): PromiseInterface;

    public function hasResolved(): bool;

    public function isSuccessful(): bool;
}
