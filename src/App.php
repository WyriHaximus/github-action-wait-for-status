<?php

declare(strict_types=1);

namespace WyriHaximus\GithubAction\WaitForStatus;

use ApiClients\Client\Github\AsyncClient;
use ApiClients\Client\Github\AsyncClientInterface;
use ApiClients\Client\Github\AuthenticationInterface;
use ApiClients\Client\Github\RateLimitState;
use Clue\React\Buzz\Message\ResponseException;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use Safe\DateTimeImmutable;
use Throwable;
use WyriHaximus\PSR3\CallableThrowableLogger\CallableThrowableLogger;

use function ApiClients\Tools\Rx\unwrapObservableFromPromise;
use function React\Promise\all;
use function React\Promise\resolve;
use function React\Promise\Stream\buffer;
use function Safe\sprintf;
use function strpos;

use const DATE_RFC3339_EXTENDED;
use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\Numeric\ONE;
use const WyriHaximus\Constants\Numeric\ZERO;

final class App
{
    private LoggerInterface $logger;

    private AsyncClientInterface $github;

    public static function boot(LoggerInterface $logger, AuthenticationInterface $auth): App
    {
        return new self($logger, AsyncClient::create(Loop::get(), $auth));
    }

    private function __construct(LoggerInterface $logger, AsyncClientInterface $github)
    {
        $this->logger = $logger;
        $this->github = $github;
    }

    public function wait(string $repository, string $ignoreActions, float $checkInterval, bool $waitForCheck, string ...$shas): PromiseInterface
    {
        $timer = $this->rateLimitTimer();
        /**
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress MissingClosureReturnType
         */
        $finally = static function ($status) use ($timer) {
            Loop::cancelTimer($timer);

            return $status;
        };

        return unwrapObservableFromPromise((new LookUpRepository($repository, $this->logger))($this->github)->then(
            new LookUpCommits($this->logger, ...$shas)
        ))->flatMap(
            new GetStatusChecksFromCommits(Loop::get(), $this->logger, $ignoreActions, $checkInterval, $waitForCheck)
        )->map(
            new WaitForStatusCheckResult(Loop::get(), $this->logger, $checkInterval)
        )->toArray()->toPromise()->then(static function (array $promises): PromiseInterface {
            return all($promises);
        })->then(static function (array $booleans): string {
            foreach ($booleans as $boolean) {
                if ($boolean === FALSE_) {
                    return 'failure';
                }
            }

            return 'success';
        })->then(null, function (Throwable $throwable): PromiseInterface {
            CallableThrowableLogger::create($this->logger)($throwable);

            $previous = $throwable->getPrevious();
            while ($previous !== null) {
                if ($previous instanceof ResponseException) {
                    $response = $previous->getResponse();
                    $body     = $response->getBody();
                    if ($body instanceof ReadableStreamInterface) {
                        return buffer($body)->then(function (string $body) use ($response): PromiseInterface {
                            $this->logger->debug('Error reason: ' . $body);

                            if (strpos($body, 'API rate limit exceeded') !== FALSE_) {
                                if ($response->hasHeader('X-RateLimit-Reset')) {
                                    $this->logger->debug(sprintf(
                                        'Rate limit resets at %s',
                                        (new DateTimeImmutable('@' . $response->getHeaderLine('X-RateLimit-Reset')))->format(DATE_RFC3339_EXTENDED)
                                    ));
                                }

                                return resolve('rate_limited');
                            }

                            return resolve('error');
                        });
                    }
                }

                $previous = $previous->getPrevious();
            }

            return resolve('error');
        })->then($finally, $finally);
    }

    private function rateLimitTimer(): TimerInterface
    {
        $previousState = '';

        return Loop::addPeriodicTimer(ONE, function () use (&$previousState): void {
            $rateLimitState = $this->github->getRateLimitState();
            $newState       = $rateLimitState->getRemaining() . '_' . $rateLimitState->getReset();
            if ($previousState === $newState) {
                return;
            }

            if ($rateLimitState->getLimit() === ZERO) {
                return;
            }

            $this->logRatelimitStatus($rateLimitState);
            $previousState = $newState;
        });
    }

    private function logRatelimitStatus(RateLimitState $rateLimitState): void
    {
        $this->logger->debug(sprintf(
            'Rate limit (remaining/limit/reset): %s/%s/%s',
            $rateLimitState->getRemaining(),
            $rateLimitState->getLimit(),
            (new DateTimeImmutable('@' . $rateLimitState->getReset()))->format(DATE_RFC3339_EXTENDED)
        ));
    }
}
