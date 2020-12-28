<?php

declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus;

use ApiClients\Client\Github\Resource\Async\Repository;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Rx\Observable;

use function ApiClients\Tools\Rx\observableFromArray;
use function implode;

final class LookUpCommits
{
    private LoggerInterface $logger;
    /** @var string[] */
    private array $shas;

    public function __construct(LoggerInterface $logger, string ...$shas)
    {
        $this->shas   = $shas;
        $this->logger = $logger;
    }

    public function __invoke(Repository $repository): PromiseInterface
    {
        $this->logger->debug('Locating commit: ' . implode(', ', $this->shas));

        return observableFromArray($this->shas)->flatMap(
            static fn (string $sha): Observable => Observable::fromPromise($repository->specificCommit($sha))
        )->toArray()->toPromise()->then(
            static fn (array $commits): Observable => observableFromArray($commits)
        );
    }
}
