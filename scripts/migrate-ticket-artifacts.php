<?php

declare(strict_types=1);

use OEMS\App\Services\TicketArtifactService;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$artifacts = new TicketArtifactService(
    $basePath . '/storage/tickets',
    'uploads/tickets',
);

$count = $artifacts->migrateLegacyArtifacts($basePath . '/public/uploads/tickets');

fwrite(STDOUT, sprintf("Migrated %d ticket artifact(s) to private storage.\n", $count));
