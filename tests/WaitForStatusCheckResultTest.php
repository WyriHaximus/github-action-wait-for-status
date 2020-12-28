<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheckInterface;
use WyriHaximus\GithubAction\WaitForStatus\WaitForStatusCheckResult;

use function array_shift;
use function assert;
use function React\Promise\resolve;

use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\Boolean\TRUE_;

/** @internal */
final class WaitForStatusCheckResultTest extends AsyncTestCase
{
    private ObjectProphecy $loop;
    private ObjectProphecy $timer;
    private ObjectProphecy $logger;
    private ObjectProphecy $statusCheck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loop        = $this->prophesize(LoopInterface::class);
        $this->timer       = $this->prophesize(TimerInterface::class);
        $this->logger      = $this->prophesize(LoggerInterface::class);
        $this->statusCheck = $this->prophesize(StatusCheckInterface::class);
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $statusCheck = $this->statusCheck->reveal();
        assert($statusCheck instanceof StatusCheckInterface);
        $this->statusCheck->refresh()->shouldBeCalledTimes(1)->willReturn(resolve(TRUE_));
        $this->statusCheck->isSuccessful()->shouldBeCalledTimes(1)->willReturn(TRUE_);
        $this->statusCheck->hasResolved()->shouldBeCalledTimes(2)->will(new class () {
            /** @var array<int, bool> */
            private array $returns = [FALSE_];

            public function __invoke(): bool
            {
                return array_shift($this->returns) ?? TRUE_;
            }
        });
        $loop = $this->loop->reveal();
        assert($loop instanceof LoopInterface);
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->notice('Checking statuses')->shouldBeCalled();
        $timer = $this->timer->reveal();
        assert($timer instanceof TimerInterface);
        $this->loop->addPeriodicTimer(0.1, Argument::that(static function ($listener) use ($timer): bool {
            $listener($timer);
            $listener($timer);

            return true;
        }))->shouldBeCalled();
        $this->loop->cancelTimer($timer)->shouldBeCalled();

        $promise = (new WaitForStatusCheckResult($loop, $logger, 0.1))($statusCheck);
        $result  = $this->await($promise);
        self::assertTrue($result);
    }
}
