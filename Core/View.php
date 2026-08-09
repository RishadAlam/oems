<?php

declare(strict_types=1);

namespace OEMS\Core;

use Closure;
use RuntimeException;
use Throwable;

final class View
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?Closure $layoutDataProvider = null,
    )
    {
    }

    public function render(string $template, array $data = [], string $layout = 'public'): string
    {
        if ($this->layoutDataProvider !== null) {
            $provided = ($this->layoutDataProvider)($data, $layout);
            if (is_array($provided)) {
                $data = array_merge($provided, $data);
            }
        }

        $templatePath = $this->resolve($template);
        $layoutPath = $this->resolve('layouts/' . $layout);
        $content = $this->capture($templatePath, $data);

        return $this->capture($layoutPath, array_merge($data, ['content' => $content]));
    }

    private function resolve(string $view): string
    {
        if (preg_match('#^[A-Za-z0-9_/-]+$#', $view) !== 1) {
            throw new RuntimeException('Invalid view name.');
        }

        $path = rtrim($this->basePath, '/') . '/' . trim($view, '/') . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("View {$view} was not found.");
        }

        return $path;
    }

    private function capture(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $path;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
