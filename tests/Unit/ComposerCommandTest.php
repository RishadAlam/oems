<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class ComposerCommandTest extends TestCase
{
    public function testDeploymentAndDatabaseCommandsAreDiscoverable(): void
    {
        $pipes = [];
        $process = proc_open(
            ['composer', 'run-script', '--list'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        $this->assertTrue(is_resource($process), 'Composer could not be started.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, (string) $stderr);
        $commands = (string) $stdout . (string) $stderr;
        foreach ([
            'build',
            'package:cpanel',
            'db:migrate',
            'db:rollback',
            'db:refresh',
            'db:seed',
        ] as $command) {
            $this->assertTrue(str_contains($commands, $command), $command . ' must be listed by Composer.');
        }
    }
}
