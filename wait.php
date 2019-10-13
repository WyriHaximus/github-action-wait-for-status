<?php

use ApiClients\Client\Github\AsyncClient;
use ApiClients\Client\Github\Authentication\Token;
use ApiClients\Client\Github\Resource\Async\Repository;
use ApiClients\Client\Github\Resource\Async\Repository\Commit;
use ApiClients\Client\Github\Resource\Async\User;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Logger;
use React\EventLoop\Factory;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Rx\Observable;
use WyriHaximus\Monolog\FormattedPsrHandler\FormattedPsrHandler;
use WyriHaximus\PSR3\CallableThrowableLogger\CallableThrowableLogger;
use WyriHaximus\React\PSR3\Stdio\StdioLogger;
use function ApiClients\Tools\Rx\observableFromArray;
use function React\Promise\all;
use function React\Promise\resolve;
use function WyriHaximus\React\timedPromise;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

const REPOSITORY = 'GITHUB_REPOSITORY';
const TOKEN = 'GITHUB_TOKEN';
const SHA = 'GITHUB_SHA';
const ACTIONS = 'INPUT_IGNOREACTIONS';
const INTERVAL = 'INPUT_CHECKINTERVAL';

(function () {
    $loop = Factory::create();
    $consoleHandler = new FormattedPsrHandler(StdioLogger::create($loop)->withHideLevel(true));
    $consoleHandler->setFormatter(new ColoredLineFormatter(
        null,
        '[%datetime%] %channel%.%level_name%: %message%',
        'Y-m-d H:i:s.u',
        true,
        false
    ));
    $logger = new Logger('wait');
    $logger->pushHandler($consoleHandler);
    [$owner, $repo] = explode('/', getenv(REPOSITORY));
    $logger->debug('Looking up owner: ' . $owner);
    /** @var Repository|null $rep */
    $rep = null;
    AsyncClient::create($loop, new Token(getenv(TOKEN)))->user($owner)->then(function (User $user) use ($repo, $logger) {
        $logger->debug('Looking up repository: ' . $repo);
        return $user->repository($repo);
    })->then(function (Repository $repository) use ($logger, &$rep) {
        $rep = $repository;
        $logger->debug('Locating commit: ' . getenv(SHA));
        return $repository->specificCommit(getenv(SHA));
    })->then(function (Commit $commit) use ($logger, &$rep) {
        $commits = [];
        $commits[] = resolve($commit);
        foreach ($commit->parents() as $parent) {
            $commits[] = $rep->specificCommit($parent->sha());
        }
        $logger->debug('Locating checks: ' . getenv(ACTIONS));
        return observableFromArray($commits)->flatMap(function (PromiseInterface $promise) {
            return Observable::fromPromise($promise);
        })->flatMap(function (Commit $commit) {
            return $commit->checks();
        })->filter(function (Commit\Check $check) {
            return in_array($check->name(), explode(',', getenv(ACTIONS)), true);
        })->flatMap(function (Commit\Check $check) use ($logger, &$rep) {
            $logger->debug('Found check and commit holding relevant statuses and checks: ' . $check->headSha());
            return observableFromArray([$rep->specificCommit($check->headSha())]);
        })->take(1)->toPromise();
    })->then(function (Commit $commit) use ($loop, $logger) {
        $logger->notice('Checking statuses and checks');

        return all([
            new Promise(function (callable $resolve, callable $reject) use ($commit, $loop, $logger) {
                $checkStatuses = function (Commit\CombinedStatus $status) use (&$timer, $resolve, $loop, $logger, &$checkStatuses) {
                    if ($status->state() === 'pending') {
                        $logger->warning('Statuses are pending, checking again in ' . getenv(INTERVAL) . ' seconds');
                        timedPromise($loop, getenv(INTERVAL))->then(function () use ($status, $checkStatuses, $logger) {
                            $logger->notice('Checking statuses');
                            $status->refresh()->then($checkStatuses);
                        });
                        return;
                    }

                    $logger->info('Status resolved: ' . $status->state());
                    $resolve($status->state());
                };
                $commit->status()->then($checkStatuses);
            }),
            new Promise(function (callable $resolve, callable $reject) use ($commit, $loop, $logger) {
                $checkChecks = function (array $checks) use (&$timer, $resolve, $loop, $logger, &$checkChecks, $commit) {
                    $state = 'success';
                    /** @var Commit\Check $status */
                    foreach ($checks as $status) {
                        if ($status->status() !== 'completed') {
                            $state = 'pending';
                            break;
                        }

                        if ($status->conclusion() !== 'success') {
                            $state = 'failure';
                            break;
                        }
                    }

                    if ($state === 'pending') {
                        $logger->warning('Checks are pending, checking again in ' . getenv(INTERVAL) . ' seconds');
                        timedPromise($loop, getenv(INTERVAL))->then(function () use ($commit, $checkChecks, $logger) {
                            $logger->notice('Checking statuses');
                            $commit->checks()->filter(function (Commit\Check $check) {
                                return in_array($check->name(), explode(',', getenv(ACTIONS)), true) === false;
                            })->toArray()->toPromise()->then($checkChecks);
                        });
                        return;
                    }

                    $logger->info('Checks resolved: ' . $state);
                    $resolve($state);
                };
                $commit->checks()->filter(function (Commit\Check $check) {
                    return in_array($check->name(), explode(',', getenv(ACTIONS)), true) === false;
                })->toArray()->toPromise()->then($checkChecks);
            }),
        ]);
    })->then(function (array $statuses) {
        foreach ($statuses as $status) {
            if ($status !== 'success') {
                return 'failure';
            }
        }

        return 'success';
    })->then(function (string $status) use ($loop) {
        return timedPromise($loop, 1, $status);
    })->done(function (string $state) {
        echo PHP_EOL, '::set-output name=status::' . $state, PHP_EOL;
    }, CallableThrowableLogger::create($logger));
    $loop->run();
})();
