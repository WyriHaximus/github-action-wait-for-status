<?php declare(strict_types=1);

use ApiClients\Client\Github\Authentication\Token;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Logger;
use React\EventLoop\Factory;
use WyriHaximus\GithubAction\WaitForStatus\App;
use WyriHaximus\Monolog\FormattedPsrHandler\FormattedPsrHandler;
use WyriHaximus\React\PSR3\Stdio\StdioLogger;
use function Safe\json_decode;
use function Safe\file_get_contents;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

const REPOSITORY = 'GITHUB_REPOSITORY';
const TOKEN = 'GITHUB_TOKEN';
const SHA = 'GITHUB_SHA';
const EVENT = 'GITHUB_EVENT_NAME';
const EVENT_PATH = 'GITHUB_EVENT_PATH';
const ACTIONS = 'INPUT_IGNOREACTIONS';
const CONTEXTS = 'INPUT_IGNORECONTEXTS';
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
    $shas = [];
    $shas[] = getenv(SHA);
    if (getenv(EVENT) === 'pull_request') {
        $logger->notice('Pull Request detected');
        $shas[] = json_decode(file_get_contents(getenv(EVENT_PATH)))->pull_request->head->sha;
    }
    App::boot($loop, $logger, new Token(getenv(TOKEN)))->wait(
        getenv(REPOSITORY),
        getenv(ACTIONS),
        getenv(CONTEXTS),
        (float) getenv(INTERVAL) > 0.0 ? (float) getenv(INTERVAL) : 13,
        ...$shas,
    )->then(function (string $state) use($logger) {
        $logger->info('Final status: ' . $state);
        echo PHP_EOL, '::set-output name=status::' . $state, PHP_EOL;
    })->done();
    $loop->run();
})();
