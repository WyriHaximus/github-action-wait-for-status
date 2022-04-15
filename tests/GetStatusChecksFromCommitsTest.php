<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\GetStatusChecksFromCommits;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheck\Checks;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheck\Status;

use function assert;
use function React\Promise\resolve;

/** @internal */
final class GetStatusChecksFromCommitsTest extends AsyncTestCase
{
    private const SHA            = 'aoshdljasjdaljads';
    private const IGNORE_ACTIONS = 'foo,bar';

    private ObjectProphecy $loop;
    private ObjectProphecy $logger;
    private ObjectProphecy $commit;
    private ObjectProphecy $combinedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loop           = $this->prophesize(LoopInterface::class);
        $this->logger         = $this->prophesize(LoggerInterface::class);
        $this->commit         = $this->prophesize(Commit::class);
        $this->combinedStatus = $this->prophesize(Commit\CombinedStatus::class);
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $loop = $this->loop->reveal();
        assert($loop instanceof LoopInterface);
        $this->loop->addTimer(Argument::type('float'), Argument::that(static function ($listener): bool {
            $listener();

            return true;
        }))->shouldBeCalled();
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->notice('Checking statuses and checks for commit: aoshdljasjdaljads')->shouldBeCalled();
        $combinedStatus = $this->combinedStatus->reveal();
        assert($combinedStatus instanceof Commit\CombinedStatus);
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $this->commit->sha()->shouldBeCalled()->willReturn(self::SHA);
        $this->commit->refresh()->shouldBeCalled()->willReturn(resolve($commit));
        $this->commit->status()->shouldBeCalled()->willReturn(resolve($combinedStatus));
        $promise = (new GetStatusChecksFromCommits($loop, $logger, self::IGNORE_ACTIONS, 0.1, false))($commit)->toArray()->toPromise();
        $result  = $this->await($promise);
        self::assertInstanceOf(Checks::class, $result[0]);
        self::assertInstanceOf(Status::class, $result[1]);
    }
}
