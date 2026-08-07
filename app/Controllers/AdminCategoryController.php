<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Services\CategoryService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminCategoryController extends Controller
{
    private const FIELDS = ['parent_id', 'name', 'slug', 'description', 'icon', 'sort_order'];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly CategoryRepositoryInterface $categories,
        private readonly CategoryService $categoryService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        return $this->render('admin/categories/index', [
            'pageTitle' => 'Categories',
            'categories' => $this->categories->all(),
        ], 'dashboard');
    }

    public function create(Request $request): Response
    {
        return $this->renderForm(null);
    }

    public function store(Request $request): Response
    {
        $data = $this->safeInput($request);
        $result = $this->categoryService->create($data);

        if (!$result['success']) {
            return $this->redirectWithErrors('/admin/categories/create', $result['errors'], $data);
        }

        $this->session->flash('success', 'Category created.');

        return Response::redirect('/admin/categories');
    }

    public function edit(Request $request): Response
    {
        $category = $this->category($request);

        return $category === null ? $this->notFound() : $this->renderForm($category);
    }

    public function update(Request $request): Response
    {
        $categoryId = $this->routeId($request);

        if ($categoryId === null || $this->categories->find($categoryId) === null) {
            return $this->notFound();
        }

        $data = $this->safeInput($request);
        $result = $this->categoryService->update($categoryId, $data);

        if (!$result['success']) {
            return $this->redirectWithErrors('/admin/categories/' . $categoryId . '/edit', $result['errors'], $data);
        }

        $this->session->flash('success', 'Category updated.');

        return Response::redirect('/admin/categories');
    }

    public function setActive(Request $request): Response
    {
        $categoryId = $this->routeId($request);

        if ($categoryId === null || $this->categories->find($categoryId) === null) {
            return $this->notFound();
        }

        $result = $this->categoryService->setActive($categoryId, $request->input('is_active'));

        if (!$result['success']) {
            return $this->redirectWith('/admin/categories', 'error', $this->firstError($result['errors']));
        }

        $this->session->flash('success', $result['is_active'] ? 'Category activated.' : 'Category deactivated.');

        return Response::redirect('/admin/categories');
    }

    private function renderForm(?array $category): Response
    {
        return $this->render('admin/categories/form', [
            'pageTitle' => $category === null ? 'Create category' : 'Edit category',
            'category' => $category,
            'categories' => $this->categories->all(),
        ], 'dashboard');
    }

    private function category(Request $request): ?array
    {
        $categoryId = $this->routeId($request);

        return $categoryId === null ? null : $this->categories->find($categoryId);
    }

    private function safeInput(Request $request): array
    {
        return array_filter(
            $request->only(self::FIELDS),
            static fn (mixed $value): bool => is_scalar($value),
        );
    }

    private function routeId(Request $request): ?int
    {
        $value = $request->route('id');

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_scalar($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'The category action could not be completed.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
