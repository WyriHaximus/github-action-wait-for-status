<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus\StatusCheck;

use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheck\Checks;

use function ApiClients\Tools\Rx\observableFromArray;
use function assert;

/** @internal */
final class ChecksTest extends AsyncTestCase
{
    private ObjectProphecy $logger;
    private ObjectProphecy $commit;
    private ObjectProphecy $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->commit = $this->prophesize(Commit::class);
        $this->check  = $this->prophesize(Commit\Check::class);
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->debug('Iterating over 1 check(s)')->shouldBeCalled();
        $this->logger->debug('Check "qa" has the following status "completed" and conclusion "success"')->shouldBeCalled();
        $this->logger->debug('All checks completed, marking resolve and success')->shouldBeCalled();
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $check = $this->check->reveal();
        assert($check instanceof Commit\Check);
        $this->check->name()->shouldBeCalled()->willReturn('qa');
        $this->check->status()->shouldBeCalled()->willReturn('completed');
        $this->check->conclusion()->shouldBeCalled()->willReturn('success');
        $this->commit->checks()->shouldBeCalled()->willReturn(observableFromArray([$check]));
        $checks = new Checks($commit, $logger, '');
        self::assertFalse($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
        $checks->refresh();
        self::assertTrue($checks->hasResolved());
        self::assertTrue($checks->isSuccessful());
    }

    /**
     * @test
     */
    public function happySkippedFlow(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->debug('Iterating over 1 check(s)')->shouldBeCalled();
        $this->logger->debug('Check "qa" has the following status "completed" and conclusion "skipped"')->shouldBeCalled();
        $this->logger->debug('All checks completed, marking resolve and success')->shouldBeCalled();
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $check = $this->check->reveal();
        assert($check instanceof Commit\Check);
        $this->check->name()->shouldBeCalled()->willReturn('qa');
        $this->check->status()->shouldBeCalled()->willReturn('completed');
        $this->check->conclusion()->shouldBeCalled()->willReturn('skipped');
        $this->commit->checks()->shouldBeCalled()->willReturn(observableFromArray([$check]));
        $checks = new Checks($commit, $logger, '');
        self::assertFalse($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
        $checks->refresh();
        self::assertTrue($checks->hasResolved());
        self::assertTrue($checks->isSuccessful());
    }

    /**
     * @test
     */
    public function failed(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->debug('Iterating over 1 check(s)')->shouldBeCalled();
        $this->logger->debug('Check "qa" has the following status "completed" and conclusion "failure"')->shouldBeCalled();
        $this->logger->debug('Check (qa) failed, marking resolve and failure')->shouldBeCalled();
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $check = $this->check->reveal();
        assert($check instanceof Commit\Check);
        $this->check->name()->shouldBeCalled()->willReturn('qa');
        $this->check->status()->shouldBeCalled()->willReturn('completed');
        $this->check->conclusion()->shouldBeCalled()->willReturn('failure');
        $this->commit->checks()->shouldBeCalled()->willReturn(observableFromArray([$check]));
        $checks = new Checks($commit, $logger, '');
        self::assertFalse($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
        $checks->refresh();
        self::assertTrue($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
    }

    /**
     * @test
     */
    public function notCompletedYet(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->debug('Iterating over 1 check(s)')->shouldBeCalled();
        $this->logger->debug('Check "qa" has the following status "not_yet_completed" and conclusion "Not supposed to be here"')->shouldBeCalled();
        $this->logger->debug('Check (qa) hasn\'t completed yet, checking again next interval')->shouldBeCalled();
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $check = $this->check->reveal();
        assert($check instanceof Commit\Check);
        $this->check->name()->shouldBeCalled()->willReturn('qa');
        $this->check->status()->shouldBeCalled()->willReturn('not_yet_completed');
        $this->check->conclusion()->shouldBeCalled()->willReturn('Not supposed to be here');
        $this->commit->checks()->shouldBeCalled()->willReturn(observableFromArray([$check]));
        $checks = new Checks($commit, $logger, '');
        self::assertFalse($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
        $checks->refresh();
        self::assertFalse($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
    }

    /**
     * @test
     */
    public function ignore(): void
    {
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $check = $this->check->reveal();
        assert($check instanceof Commit\Check);
        $this->check->name()->shouldBeCalled()->willReturn('qa');
        $this->check->status()->shouldNotBeCalled();
        $this->check->conclusion()->shouldNotBeCalled();
        $this->commit->checks()->shouldBeCalled()->willReturn(observableFromArray([$check]));
        $checks = new Checks($commit, $logger, 'qa');
        self::assertFalse($checks->hasResolved());
        self::assertFalse($checks->isSuccessful());
        $checks->refresh();
        self::assertTrue($checks->hasResolved());
        self::assertTrue($checks->isSuccessful());
    }
}
