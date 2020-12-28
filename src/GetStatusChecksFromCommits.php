<?php

declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus;

use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Rx\Observable;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheck\Checks;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheck\Status;

use function ApiClients\Tools\Rx\observableFromArray;
use function ApiClients\Tools\Rx\unwrapObservableFromPromise;
use function React\Promise\all;
use function React\Promise\resolve;
use function WyriHaximus\React\timedPromise;

final class GetStatusChecksFromCommits
{
    private LoopInterface $loop;
    private LoggerInterface $logger;
    private string $ignoreActions;
    private float $checkInterval;

    public function __construct(LoopInterface $loop, LoggerInterface $logger, string $ignoreActions, float $checkInterval)
    {
        $this->loop          = $loop;
        $this->logger        = $logger;
        $this->ignoreActions = $ignoreActions;
        $this->checkInterval = $checkInterval;
    }

    public function __invoke(Commit $commit): Observable
    {
        $this->logger->notice('Checking statuses and checks for commit: ' . $commit->sha());

        /** @psalm-suppress InvalidScalarArgument */
        return unwrapObservableFromPromise(
            timedPromise($this->loop, $this->checkInterval, $commit)->then(static function (Commit $commit): PromiseInterface {
                return $commit->refresh();
            })->then(function (Commit $commit): PromiseInterface {
                return all([
                    'checks' => new Checks($commit, $this->logger, $this->ignoreActions),
                    'status' => $commit->status()->then(function (Commit\CombinedStatus $combinedStatus): StatusCheckInterface {
                        return new Status($this->logger, $combinedStatus);
                    }),
                ]);
            })->then(static function (array $statusChecks): PromiseInterface {
                return resolve(observableFromArray($statusChecks));
            })
        );
    }
}
