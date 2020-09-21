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

final class Status implements StatusCheckInterface
{
    private LoggerInterface $logger;
    private Commit\CombinedStatus $combinedStatus;
    private array $ignoreContexts;
    private bool $resolved   = FALSE_;
    private bool $successful = FALSE_;

    public function __construct(LoggerInterface $logger, Commit\CombinedStatus $combinedStatus, string $ignoreContexts)
    {
        $this->logger         = $logger;
        $this->combinedStatus = $combinedStatus;
        $this->ignoreContexts = explode(',', $ignoreContexts);
    }

    public function refresh(): PromiseInterface
    {
        return $this->combinedStatus->refresh()->then(function (Commit\CombinedStatus $combinedStatus): PromiseInterface {
            return $combinedStatus->statuses()->toPromise();
        })->then(function ($statuses): PromiseInterface {
            return $statuses->filter(function (Commit\Status $status): bool {
                return in_array($status->context(), $this->ignoreContexts, TRUE_) === FALSE_;
            })->toArray()->toPromise();
        })->then(function (array $statuses): void {
            $return = FALSE_;
            $this->logger->debug('Iterating over ' . count($statuses) . ' status(es)');
            foreach ($statuses as $status) {
                assert($status instanceof Commit\Status);
                $this->logger->debug('Status "' . $status->context() . '" has the following state "' . $status->state() . '" and description "' . $status->description() . '"');
                if ($status->state() !== 'success') {
                    $this->logger->debug('Status (' . $status->context() . ') hasn\'t completed yet, checking again next interval');

                    $return = TRUE_;
                }

                if ($status->state() !== 'success') {
                    continue;
                }

                $this->logger->debug('Status (' . $status->context() . ') failed, marking resolve and failure');
                $this->resolved = TRUE_;

                $return = TRUE_;
            }

            if ($return === TRUE_) {
                return;
            }
    
            $this->logger->debug('All statuses completed, marking resolve and success');
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
