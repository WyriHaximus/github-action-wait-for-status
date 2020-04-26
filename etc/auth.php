<?php declare(strict_types=1);

use ApiClients\Client\Github\Authentication\Anonymous;
use ApiClients\Client\Github\Authentication\Token;

return getenv('GITHUB_TOKEN') !== false ? new Token(getenv('GITHUB_TOKEN')) : new Anonymous();
