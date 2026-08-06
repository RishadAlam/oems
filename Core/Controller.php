<?php

declare(strict_types=1);

namespace OEMS\Core;

abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Session $session,
        protected readonly Security $security,
        protected readonly Auth $auth,
        protected readonly Config $config,
    ) {
    }

    protected function render(string $template, array $data = [], string $layout = 'public'): Response
    {
        $common = [
            'app' => $this->config->all(),
            'currentUser' => $this->auth->user(),
            'csrfToken' => $this->security->csrfToken(),
            'errors' => $this->session->pullFlash('errors', []),
            'old' => $this->session->pullFlash('old', []),
            'flash' => [
                'success' => $this->session->pullFlash('success'),
                'error' => $this->session->pullFlash('error'),
                'info' => $this->session->pullFlash('info'),
                'development_link' => $this->session->pullFlash('development_link'),
            ],
        ];

        return Response::html($this->view->render($template, array_merge($common, $data), $layout));
    }

    protected function redirectWith(string $location, string $type, string $message): Response
    {
        $this->session->flash($type, $message);

        return Response::redirect($location);
    }

    protected function redirectWithErrors(string $location, array $errors, array $old = []): Response
    {
        $this->session->flash('errors', $errors);
        $this->session->flash('old', $old);

        return Response::redirect($location);
    }
}

