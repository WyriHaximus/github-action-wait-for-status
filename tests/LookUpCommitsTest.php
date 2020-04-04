<?php declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use ApiClients\Client\Github\AsyncClientInterface;
use ApiClients\Client\Github\Resource\Async\Repository;
use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\LookUpCommits;
use function ApiClients\Tools\Rx\unwrapObservableFromPromise;
use function assert;
use function React\Promise\resolve;

/** @internal */
final class LookUpCommitsTest extends AsyncTestCase
{
    private const SHA        = 'aoshdljasjdaljads';
    private const PARENT_SHA = ';ljwefows3h4ow34huo;a';
    private ObjectProphecy $logger;
    private ObjectProphecy $repository;
    private ObjectProphecy $github;
    private ObjectProphecy $commit;
    private ObjectProphecy $parentCommit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger       = $this->prophesize(LoggerInterface::class);
        $this->repository   = $this->prophesize(Repository::class);
        $this->github       = $this->prophesize(AsyncClientInterface::class);
        $this->commit       = $this->prophesize(Commit::class);
        $this->parentCommit = $this->prophesize(Commit::class);
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $repository = $this->repository->reveal();
        assert($repository instanceof Repository);
        $logger = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->debug('Locating commit: aoshdljasjdaljads')->shouldBeCalled();
        $github = $this->github->reveal();
        assert($github instanceof AsyncClientInterface);
        $commit = $this->commit->reveal();
        assert($commit instanceof Commit);
        $parentCommit = $this->parentCommit->reveal();
        assert($parentCommit instanceof Commit);
        $this->repository->specificCommit(self::SHA)->shouldBeCalled()->willReturn(resolve($commit));
        $this->repository->specificCommit(self::PARENT_SHA)->shouldBeCalled()->willReturn(resolve($parentCommit));
        $this->commit->parents()->shouldBeCalled()->willReturn([$parentCommit]);
        $this->parentCommit->sha()->shouldBeCalled()->willReturn(self::PARENT_SHA);

        $promise = (new LookUpCommits(self::SHA, $logger))($repository);
        $result  = $this->await(unwrapObservableFromPromise($promise)->toArray()->toPromise());
        self::assertSame([$commit, $parentCommit], $result);
    }
}
