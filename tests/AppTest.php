<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use ApiClients\Client\Github\Authentication\Token;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\App;

use function dirname;

use const DIRECTORY_SEPARATOR;

/** @internal */
final class AppTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function success(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::containingString('Rate limit (remaining/limit/reset):'))->shouldBeCalled();
        $logger->debug('Looking up owner: WyriHaximus')->shouldBeCalled();
        $logger->debug('Looking up repository: github-action-wait-for-status')->shouldBeCalled();
        $logger->debug('Locating commit: d2ddfe536405fa61cd5f8ae1b3e06f192bac1d64')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: d2ddfe536405fa61cd5f8ae1b3e06f192bac1d64')->shouldBeCalled();
        $logger->notice('Checking statuses')->shouldBeCalled();
        $logger->warning('No statuses found, assuming success')->shouldBeCalled();
        $logger->debug('Iterating over 0 check(s)')->shouldBeCalled();
        $logger->debug('All checks completed, marking resolve and success')->shouldBeCalled();
        $result = $this->await(
            App::boot($logger->reveal(), (require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc/auth.php'), 'https://api.github.com')->wait(
                'WyriHaximus/github-action-wait-for-status',
                'wait',
                1,
                false,
                'd2ddfe536405fa61cd5f8ae1b3e06f192bac1d64'
            ),
            30
        );

        self::assertSame('success', $result);
    }

    /**
     * @test
     */
    public function failure(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::containingString('Rate limit (remaining/limit/reset):'))->shouldBeCalled();
        $logger->debug('Looking up owner: WyriHaximus')->shouldBeCalled();
        $logger->debug('Looking up repository: php-broadcast')->shouldBeCalled();
        $logger->debug('Locating commit: fd41764a698496699704b11729ba16be936a5143')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: fd41764a698496699704b11729ba16be936a5143')->shouldBeCalled();
        $logger->notice('Checking statuses')->shouldBeCalled();
        $logger->debug('Iterating over 48 check(s)')->shouldBeCalled();
        $logger->debug(Argument::type('string'))->shouldBeCalled();
        $result = $this->await(
            App::boot($logger->reveal(), (require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc/auth.php'), 'https://api.github.com')->wait(
                'WyriHaximus/php-broadcast',
                'wait',
                1,
                false,
                'fd41764a698496699704b11729ba16be936a5143'
            ),
            30
        );

        self::assertSame('failure', $result);
    }

    /**
     * @test
     */
    public function error(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::containingString('Rate limit (remaining/limit/reset):'));
        $logger->debug('Error reason: {"message":"Bad credentials","documentation_url":"https://docs.github.com/rest","status":"401"}')->shouldBeCalled();
        $logger->debug('Looking up owner: WyriHaximus')->shouldBeCalled();
        $logger->log(
            'error',
            Argument::containingString('Uncaught Throwable ApiClients\Tools\Psr7\HttpStatusExceptions\UnauthorizedException: "Unauthorized" at '),
            Argument::type('array')
        )->shouldBeCalled();
        $result = $this->await(
            App::boot($logger->reveal(), new Token('FAKE_TOKEN_TO_FORCE_ERROR'), 'https://api.github.com')->wait(
                'WyriHaximus/github-action-wait-for-status',
                'wait',
                1,
                false,
                'd2ddfe536405fa61cd5f8ae1b3e06f192bac1d64'
            ),
            30
        );

        self::assertSame('error', $result);
    }
}
