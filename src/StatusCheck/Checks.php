<?php declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus\StatusCheck;

use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use WyriHaximus\GithubAction\WaitForStatus\StatusCheckInterface;
use function assert;
use function count;
use function explode;
use function in_array;
use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\Boolean\TRUE_;

final class Checks implements StatusCheckInterface
{
    private Commit $commit;
    private LoggerInterface $logger;
    /** @var array<int, string> */
    private array $ignoreActions;
    private bool $resolved   = FALSE_;
    private bool $successful = FALSE_;

    public function __construct(Commit $commit, LoggerInterface $logger, string $ignoreActions)
    {
        $this->commit        = $commit;
        $this->logger        = $logger;
        $this->ignoreActions = explode(',', $ignoreActions);
    }

    public function refresh(): PromiseInterface
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        return $this->commit->checks()->filter(function (Commit\Check $check): bool {
            return in_array($check->name(), $this->ignoreActions, TRUE_) === FALSE_;
        })->toArray()->toPromise()->then(function (array $checks): void {
            $return = FALSE_;
            $this->logger->debug('Iterating over ' . count($checks) . ' check(s)');
            foreach ($checks as $status) {
                assert($status instanceof Commit\Check);
                $this->logger->debug('Check "' . $status->name() . '" has the following status "' . $status->status() . '" and conclusion "' . $status->conclusion() . '"');
                if ($status->status() !== 'completed') {
                    $this->logger->debug('Check (' . $status->name() . ') hasn\'t completed yet, checking again next interval');

                    $return = TRUE_;
                }

                if ($status->status() !== 'completed' || $status->conclusion() === 'success') {
                    continue;
                }

                $this->logger->debug('Check (' . $status->name() . ') failed, marking resolve and failure');
                $this->resolved = TRUE_;

                $return = TRUE_;
            }

            if ($return === TRUE_) {
                return;
            }

            $this->logger->debug('All checks completed, marking resolve and success');
            $this->resolved   = TRUE_;
            $this->successful = TRUE_;
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
