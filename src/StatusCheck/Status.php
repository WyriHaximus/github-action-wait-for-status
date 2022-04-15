<?php

declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus\StatusCheck;

use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheckInterface;

use function React\Promise\resolve;

use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\Boolean\TRUE_;

final class Status implements StatusCheckInterface
{
    private LoggerInterface $logger;
    private Commit\CombinedStatus $combinedStatus;
    private bool $resolved     = FALSE_;
    private bool $successful   = FALSE_;
    private bool $waitForCheck = FALSE_;

    public function __construct(LoggerInterface $logger, Commit\CombinedStatus $combinedStatus, bool $waitForCheck)
    {
        $this->logger         = $logger;
        $this->combinedStatus = $combinedStatus;
        $this->waitForCheck   = $waitForCheck;
    }

    public function refresh(): PromiseInterface
    {
        return $this->combinedStatus->refresh()->then(function (Commit\CombinedStatus $status): PromiseInterface {
            if ($status->totalCount() === 0) {
                if ($this->waitForCheck) {
                    $this->logger->warning('No statuses found yet, waiting');

                    return resolve();
                }

                $this->logger->warning('No statuses found, assuming success');
                $this->resolved   = TRUE_;
                $this->successful = TRUE_;

                return resolve();
            }

            if ($status->state() === 'pending') {
                $this->logger->warning('Statuses are pending');

                return resolve();
            }

            $this->resolved   = TRUE_;
            $this->successful = $status->state() === 'success';

            return resolve();
        });
    }

    public function hasResolved(): bool
    {
        return $this->resolved;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }
}
