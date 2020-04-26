<?php declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus;

use ApiClients\Client\Github\AsyncClientInterface;
use ApiClients\Client\Github\Resource\Async\User;
use ApiClients\Tools\Psr7\HttpStatusExceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Throwable;
use function explode;
use function React\Promise\reject;
use const WyriHaximus\Constants\HTTPStatusCodes\NOT_FOUND;

final class LookUpRepository
{
    private string $owner;
    private string $repo;
    private LoggerInterface $logger;

    public function __construct(string $repository, LoggerInterface $logger)
    {
        [$this->owner, $this->repo] = explode('/', $repository);
        $this->logger               = $logger;
    }

    public function __invoke(AsyncClientInterface $github): PromiseInterface
    {
        $this->logger->debug('Looking up owner: ' . $this->owner);

        return $github->user($this->owner)->then(function (User $user): PromiseInterface {
            $this->logger->debug('Looking up repository: ' . $this->repo);

            return $user->repository($this->repo);
        }, static function (Throwable $throwable): PromiseInterface {
            if ($throwable instanceof NotFoundException) {
                return reject(new Error('Owner not found', NOT_FOUND, $throwable));
            }

            return reject($throwable);
        })->then(null, static function (Throwable $throwable): PromiseInterface {
            if ($throwable instanceof NotFoundException) {
                return reject(new Error('Repository not found', NOT_FOUND, $throwable));
            }

            return reject($throwable);
        });
    }
}
