<?php declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

final class WaitForStatusCheckResult
{
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private float $interval;

    public function __construct(LoopInterface $loop, LoggerInterface $logger, float $interval)
    {
        $this->loop     = $loop;
        $this->logger   = $logger;
        $this->interval = $interval;
    }

    public function __invoke(StatusCheckInterface $statusCheck): PromiseInterface
    {
        return new Promise(function (callable $resolve, callable $reject) use ($statusCheck): void {
            $this->loop->addPeriodicTimer($this->interval, function (TimerInterface $timer) use ($statusCheck, $resolve): void {
                if ($statusCheck->hasResolved()) {
                    $this->loop->cancelTimer($timer);
                    $resolve($statusCheck->isSuccessful());

                    return;
                }

                $this->logger->notice('Checking statuses');
                $statusCheck->refresh();
            });
        });
    }
}
