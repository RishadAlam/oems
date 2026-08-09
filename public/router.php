<?php

declare(strict_types=1);

use OEMS\Core\PublicFilePolicy;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PublicFilePolicy::mayServe(__DIR__, (string) ($_SERVER['REQUEST_URI'] ?? '/'))) {
    return false;
}

require __DIR__ . '/index.php';
