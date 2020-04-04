<?php declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use ApiClients\Client\Github\Authentication\Token;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
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
        $loop   = Factory::create();
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::containingString('Rate limit (remaining/limit/reset):'))->shouldBeCalled();
        $logger->debug('Looking up owner: WyriHaximus')->shouldBeCalled();
        $logger->debug('Looking up repository: github-action-wait-for-status')->shouldBeCalled();
        $logger->debug('Locating commit: d2ddfe536405fa61cd5f8ae1b3e06f192bac1d64')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: d2ddfe536405fa61cd5f8ae1b3e06f192bac1d64')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: 356f8328688adfe31f35e5126a3abb61b323da53')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: d59515add71f9508d0b6b32890ec5a9d566b99e0')->shouldBeCalled();
        $logger->notice('Checking statuses')->shouldBeCalled();
        $logger->warning('No statuses found, assuming success')->shouldBeCalled();
        $logger->debug('All checks completed, marking resolve and success')->shouldBeCalled();
        $result = $this->await(
            App::boot($loop, $logger->reveal(), (require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc/auth.php'))->wait(
                'WyriHaximus/github-action-wait-for-status',
                'd2ddfe536405fa61cd5f8ae1b3e06f192bac1d64',
                'wait',
                1
            ),
            $loop,
            30
        );

        self::assertSame('success', $result);
    }

    /**
     * @test
     */
    public function failure(): void
    {
        $loop   = Factory::create();
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::containingString('Rate limit (remaining/limit/reset):'))->shouldBeCalled();
        $logger->debug('Looking up owner: WyriHaximus')->shouldBeCalled();
        $logger->debug('Looking up repository: php-broadcast')->shouldBeCalled();
        $logger->debug('Locating commit: 67bdf304b34567e0f434bc0f9f19d3022cc1aa6c')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: 67bdf304b34567e0f434bc0f9f19d3022cc1aa6c')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: 9829e77fb0e5fee35c419265c152bf45d6716b3e')->shouldBeCalled();
        $logger->notice('Checking statuses and checks for commit: 3d92616435db0d66dd598ef26f36a9e36b394c9a')->shouldBeCalled();
        $logger->notice('Checking statuses')->shouldBeCalled();
        $logger->debug('Check (Travis CI - Branch) failed, marking resolve and failure')->shouldBeCalled();
        $logger->debug('Check (Travis CI - Pull Request) failed, marking resolve and failure')->shouldBeCalled();
        $result = $this->await(
            App::boot($loop, $logger->reveal(), (require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc/auth.php'))->wait(
                'WyriHaximus/php-broadcast',
                '67bdf304b34567e0f434bc0f9f19d3022cc1aa6c',
                'wait',
                1
            ),
            $loop,
            30
        );

        self::assertSame('failure', $result);
    }

    /**
     * @test
     */
    public function error(): void
    {
        $loop   = Factory::create();
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::containingString('Rate limit (remaining/limit/reset):'));
        $logger->debug('Error reason: {"message":"Bad credentials","documentation_url":"https://developer.github.com/v3"}')->shouldBeCalled();
        $logger->debug('Looking up owner: WyriHaximus')->shouldBeCalled();
        $logger->log(
            'error',
            Argument::containingString('Uncaught Throwable ApiClients\Tools\Psr7\HttpStatusExceptions\UnauthorizedException: "Unauthorized" at '),
            Argument::type('array')
        )->shouldBeCalled();
        $result = $this->await(
            App::boot($loop, $logger->reveal(), new Token('FAKE_TOKEN_TO_FORCE_ERROR'))->wait(
                'WyriHaximus/github-action-wait-for-status',
                'd2ddfe536405fa61cd5f8ae1b3e06f192bac1d64',
                'wait',
                1
            ),
            $loop,
            30
        );

        self::assertSame('error', $result);
    }
}
