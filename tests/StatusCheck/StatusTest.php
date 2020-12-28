<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus\StatusCheck;

use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheck\Status;

use function assert;
use function React\Promise\resolve;

/** @internal */
final class StatusTest extends AsyncTestCase
{
    private ObjectProphecy $logger;
    private ObjectProphecy $combinedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger         = $this->prophesize(LoggerInterface::class);
        $this->combinedStatus = $this->prophesize(Commit\CombinedStatus::class);
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $combinedStatus = $this->combinedStatus->reveal();
        assert($combinedStatus instanceof Commit\CombinedStatus);
        $this->combinedStatus->totalCount()->shouldBeCalled()->willReturn(1);
        $this->combinedStatus->state()->shouldBeCalled()->willReturn('success');
        $this->combinedStatus->refresh()->shouldBeCalled()->willReturn(resolve($combinedStatus));
        $status = new Status($logger, $combinedStatus);
        self::assertFalse($status->hasResolved());
        self::assertFalse($status->isSuccessful());
        $status->refresh();
        self::assertTrue($status->hasResolved());
        self::assertTrue($status->isSuccessful());
    }

    /**
     * @test
     */
    public function failure(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $combinedStatus = $this->combinedStatus->reveal();
        assert($combinedStatus instanceof Commit\CombinedStatus);
        $this->combinedStatus->totalCount()->shouldBeCalled()->willReturn(1);
        $this->combinedStatus->state()->shouldBeCalled()->willReturn('failure');
        $this->combinedStatus->refresh()->shouldBeCalled()->willReturn(resolve($combinedStatus));
        $status = new Status($logger, $combinedStatus);
        self::assertFalse($status->hasResolved());
        self::assertFalse($status->isSuccessful());
        $status->refresh();
        self::assertTrue($status->hasResolved());
        self::assertFalse($status->isSuccessful());
    }

    /**
     * @test
     */
    public function pending(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->warning('Statuses are pending')->shouldBeCalled();
        $combinedStatus = $this->combinedStatus->reveal();
        assert($combinedStatus instanceof Commit\CombinedStatus);
        $this->combinedStatus->totalCount()->shouldBeCalled()->willReturn(1);
        $this->combinedStatus->state()->shouldBeCalled()->willReturn('pending');
        $this->combinedStatus->refresh()->shouldBeCalled()->willReturn(resolve($combinedStatus));
        $status = new Status($logger, $combinedStatus);
        self::assertFalse($status->hasResolved());
        self::assertFalse($status->isSuccessful());
        $status->refresh();
        self::assertFalse($status->hasResolved());
        self::assertFalse($status->isSuccessful());
    }

    /**
     * @test
     */
    public function noStatuses(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->warning('No statuses found, assuming success')->shouldBeCalled();
        $combinedStatus = $this->combinedStatus->reveal();
        assert($combinedStatus instanceof Commit\CombinedStatus);
        $this->combinedStatus->totalCount()->shouldBeCalled()->willReturn(0);
        $this->combinedStatus->refresh()->shouldBeCalled()->willReturn(resolve($combinedStatus));
        $status = new Status($logger, $combinedStatus);
        self::assertFalse($status->hasResolved());
        self::assertFalse($status->isSuccessful());
        $status->refresh();
        self::assertTrue($status->hasResolved());
        self::assertTrue($status->isSuccessful());
    }
}
