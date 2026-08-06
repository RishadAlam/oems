<?php

declare(strict_types=1);

namespace OEMS\Core;

use PDO;

abstract class Model
{
    public function __construct(protected readonly Database $database)
    {
    }

    protected function connection(): PDO
    {
        return $this->database->connection();
    }
}

