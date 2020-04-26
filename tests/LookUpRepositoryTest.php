<?php declare(strict_types=1);

namespace WyriHaximus\Tests\GithubAction\WaitForStatus;

use ApiClients\Client\Github\AsyncClientInterface;
use ApiClients\Client\Github\Resource\Async\Repository;
use ApiClients\Client\Github\Resource\Async\User;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GithubAction\WaitForStatus\LookUpRepository;
use function assert;
use function React\Promise\resolve;

/** @internal */
final class LookUpRepositoryTest extends AsyncTestCase
{
    private const REPOSITORY = 'wyrihaximus/github-action-wait-for-status';
    private ObjectProphecy $logger;
    private ObjectProphecy $repository;
    private ObjectProphecy $user;
    private ObjectProphecy $github;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger     = $this->prophesize(LoggerInterface::class);
        $this->repository = $this->prophesize(Repository::class);
        $this->user       = $this->prophesize(User::class);
        $this->github     = $this->prophesize(AsyncClientInterface::class);
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $repository = $this->repository->reveal();
        $logger     = $this->logger->reveal();
        assert($logger instanceof LoggerInterface);
        $this->logger->debug('Looking up owner: wyrihaximus')->shouldBeCalled();
        $this->logger->debug('Looking up repository: github-action-wait-for-status')->shouldBeCalled();
        $github = $this->github->reveal();
        assert($github instanceof AsyncClientInterface);
        $this->user->repository('github-action-wait-for-status')->shouldBeCalled()->willReturn(resolve($repository));
        $this->github->user('wyrihaximus')->shouldBeCalled()->willReturn(resolve($this->user->reveal()));

        $promise = (new LookUpRepository(self::REPOSITORY, $logger))($github);
        $result  = $this->await($promise);
        self::assertSame($repository, $result);
    }
}
