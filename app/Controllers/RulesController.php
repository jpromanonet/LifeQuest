<?php

declare(strict_types=1);

final class RulesController
{
    private RuleService $rules;
    private AuditService $audit;

    public function __construct()
    {
        $this->rules = new RuleService();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $grouped = $this->rules->listGrouped($userId);

        $totalRules = 0;
        $maxCategory = null;
        foreach ($grouped as $section) {
            $count = count($section['rules']);
            $totalRules += $count;
            if ($maxCategory === null || $count > $maxCategory['count']) {
                $maxCategory = ['name' => (string) $section['category']['name'], 'count' => $count];
            }
        }
        $categoryCount = count($grouped);

        $activeCat = (int) ($_GET['cat'] ?? 0);
        if ($activeCat > 0) {
            $catIds = array_map(static fn (array $s): int => (int) $s['category']['id'], $grouped);
            if (!in_array($activeCat, $catIds, true)) {
                $activeCat = 0;
            }
        }

        view('rules/index', [
            'title' => 'Reglas propias',
            'currentNav' => 'rules',
            'grouped' => $grouped,
            'activeCat' => $activeCat,
            'totalRules' => $totalRules,
            'categoryCount' => $categoryCount,
            'maxCategory' => $maxCategory,
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    /** Vuelve a la vista de reglas conservando el filtro de categoría activo. */
    private function backPath(): string
    {
        $r = (string) (input('redirect') ?: '/rules');
        return str_starts_with($r, '/rules') ? $r : '/rules';
    }

    public function storeCategory(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        try {
            $id = $this->rules->createCategory($userId, (string) input('name', ''));
            $this->audit->log($userId, 'rule_category.create', 'rule_category', $id);
            respond_saved('Categoría creada.', $this->backPath());
        } catch (Throwable $e) {
            respond_error($e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo crear la categoría.', $this->backPath());
        }
    }

    public function updateCategory(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $categoryId = (int) $id;

        try {
            $this->rules->updateCategory($userId, $categoryId, (string) input('name', ''));
            $this->audit->log($userId, 'rule_category.update', 'rule_category', $categoryId);
            respond_saved('Categoría actualizada.', $this->backPath());
        } catch (Throwable $e) {
            respond_error($e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo actualizar la categoría.', $this->backPath());
        }
    }

    public function destroyCategory(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $categoryId = (int) $id;

        try {
            $this->rules->deleteCategory($userId, $categoryId);
            $this->audit->log($userId, 'rule_category.delete', 'rule_category', $categoryId);
            respond_saved('Categoría eliminada con sus reglas.', $this->backPath());
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar la categoría.', $this->backPath());
        }
    }

    public function storeRule(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();

        try {
            $id = $this->rules->createRule(
                $userId,
                (int) input('category_id', 0),
                (string) input('title', ''),
                input('body') !== null ? (string) input('body') : null
            );
            $this->audit->log($userId, 'rule.create', 'own_rule', $id);
            respond_saved('Regla agregada.', $this->backPath());
        } catch (Throwable $e) {
            respond_error($e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo crear la regla.', $this->backPath());
        }
    }

    public function updateRule(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $ruleId = (int) $id;

        try {
            $this->rules->updateRule(
                $userId,
                $ruleId,
                (string) input('title', ''),
                input('body') !== null ? (string) input('body') : null
            );
            $this->audit->log($userId, 'rule.update', 'own_rule', $ruleId);
            respond_saved('Regla actualizada.', $this->backPath());
        } catch (Throwable $e) {
            respond_error($e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo actualizar la regla.', $this->backPath());
        }
    }

    public function destroyRule(string $id): void
    {
        Auth::requireLogin();
        verify_csrf();
        $userId = Auth::id();
        $ruleId = (int) $id;

        try {
            $this->rules->deleteRule($userId, $ruleId);
            $this->audit->log($userId, 'rule.delete', 'own_rule', $ruleId);
            respond_saved('Regla eliminada.', $this->backPath());
        } catch (Throwable $e) {
            respond_error('No se pudo eliminar la regla.', $this->backPath());
        }
    }
}
