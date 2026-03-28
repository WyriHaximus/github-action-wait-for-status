<?php declare(strict_types=1);

use ApiClients\Client\Github\Authentication\Token;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Logger;
use React\EventLoop\Loop;
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
const API_BASE_URL = 'GITHUB_API_URL';
const ACTIONS = 'INPUT_IGNOREACTIONS';
const INTERVAL = 'INPUT_CHECKINTERVAL';
const WAIT_FOR_CHECK = 'INPUT_WAITFORCHECK';
const OVERWRITE_COMMIT_SHA = 'INPUT_COMMITSHA';

(function () {
    $consoleHandler = new FormattedPsrHandler(StdioLogger::create(Loop::get())->withHideLevel(true));
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
    if (getenv(OVERWRITE_COMMIT_SHA) !== '') {
        $shas = [
            getenv(OVERWRITE_COMMIT_SHA),
        ];
    }
    App::boot($logger, new Token(getenv(TOKEN)), getenv(API_BASE_URL))->wait(
        getenv(REPOSITORY),
        getenv(ACTIONS),
        (float) getenv(INTERVAL) > 0.0 ? (float) getenv(INTERVAL) : 13,
        getenv(WAIT_FOR_CHECK) != "",
        ...$shas,
    )->then(function (string $state) use($logger) {
        $logger->info('Final status: ' . $state);
        file_put_contents(getenv('GITHUB_OUTPUT'), 'status=' . $state . "\n", FILE_APPEND);
    })->done();
})();
