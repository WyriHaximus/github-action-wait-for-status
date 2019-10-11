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
use WyriHaximus\Monolog\FormattedPsrHandler\FormattedPsrHandler;
use WyriHaximus\PSR3\CallableThrowableLogger\CallableThrowableLogger;
use WyriHaximus\React\PSR3\Stdio\StdioLogger;
use function WyriHaximus\React\timedPromise;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

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
    [$owner, $repo] = explode('/', getenv('GITHUB_REPOSITORY'));
    $logger->debug('Looking up owner: ' . $owner);
    AsyncClient::create($loop, new Token(getenv('GITHUB_TOKEN')))->user($owner)->then(function (User $user) use ($repo, $logger) {
        $logger->debug('Looking up repository: ' . $repo);
        return $user->repository($repo);
    })->then(function (Repository $repository) use ($logger) {
        $logger->debug('Locating commit: ' . getenv('GITHUB_SHA'));
        return $repository->commits()->filter(function (Commit $commit) {
            return $commit->sha() === getenv('GITHUB_SHA');
        })->take(1)->toPromise();
    })->then(function (Commit $commit) use ($logger) {
        $logger->notice('Checking status');
        return $commit->status();
    })->then(function (Commit\CombinedStatus $status) use ($loop, $logger) {
        return new Promise(function (callable $resolve, callable $reject) use ($status, $loop, $logger) {
            $check = function ($status) use (&$timer, $resolve, $loop, $logger, &$check) {
                if ($status->state() === 'pending') {
                    $logger->warning('Status is pending, checking again in 10 seconds');
                    timedPromise($loop, 10)->then(function () use ($status, $check, $logger) {
                        $logger->notice('Checking status');
                        $status->refresh()->then($check);
                    });
                    return;
                }

                $logger->info('Status resolved: ' . $status->state());
                $resolve($status->state());
            };
            $check($status);
        });
    })->then(function (string $status) use ($loop) {
        return timedPromise($loop, 1, $status);
    })->done(function (string $state) {
        echo PHP_EOL, $state, PHP_EOL;
    }, CallableThrowableLogger::create($logger));
    $loop->run();
})();
