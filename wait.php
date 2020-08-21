<?php declare(strict_types=1);

use ApiClients\Client\Github\Authentication\Token;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Logger;
use React\EventLoop\Factory;
use WyriHaximus\GithubAction\WaitForStatus\App;
use WyriHaximus\Monolog\FormattedPsrHandler\FormattedPsrHandler;
use WyriHaximus\React\PSR3\Stdio\StdioLogger;
use function React\Promise\all;

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
    App::boot($loop, $logger, new Token(getenv(TOKEN)))->wait(
        getenv(REPOSITORY),
        getenv(SHA),
        getenv(ACTIONS),
        (float) getenv(INTERVAL) > 0.0 ? (float) getenv(INTERVAL) : 13,
    )->then(function (string $state) use($logger) {
        $logger->info('Final status: ' . $state);
        echo PHP_EOL, '::set-output name=status::' . $state, PHP_EOL;
    })->done();
    $loop->run();
})();
