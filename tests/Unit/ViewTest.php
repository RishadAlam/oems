<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;

final class ViewTest extends TestCase
{
    public function testRendersEscapedTemplateContentInsideTheLayout(): void
    {
        $view = new View(dirname(__DIR__) . '/Fixtures/views');

        $html = $view->render('content', ['name' => '<script>alert(1)</script>'], 'test');

        $this->assertTrue(str_contains($html, '<html><body>'));
        $this->assertTrue(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertFalse(str_contains($html, '<script>alert(1)</script>'));
    }

    public function testRejectsTemplateTraversalOutsideTheViewDirectory(): void
    {
        $view = new View(dirname(__DIR__) . '/Fixtures/views');

        $threw = false;

        try {
            $view->render('../bootstrap', [], 'test');
        } catch (\RuntimeException) {
            $threw = true;
        }

        $this->assertTrue($threw);
    }
}

