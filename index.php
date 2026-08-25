<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$router = new Router();

$router->get('/login', [new AuthController(), 'showLogin']);
$router->post('/login', [new AuthController(), 'login']);
$router->post('/logout', [new AuthController(), 'logout']);

$router->get('/', static function (): void {
    Auth::requireLogin();
    redirect('/today');
});

$router->get('/today', [new TodayController(), 'index']);

$goals = new GoalsController();
$router->get('/goals', [$goals, 'index']);
$router->post('/goals', [$goals, 'store']);
$router->post('/goals/years', [$goals, 'addYear']);
$router->post('/goals/years/{year}/delete', [$goals, 'destroyYear']);
$router->get('/goals/{id}', [$goals, 'show']);
$router->post('/goals/{id}', [$goals, 'update']);
$router->post('/goals/{id}/delete', [$goals, 'destroy']);
$router->post('/goals/{id}/progress', [$goals, 'progress']);
$router->post('/goals/{id}/months', [$goals, 'toggleMonth']);
$router->post('/goals/{id}/binary', [$goals, 'toggleBinary']);
$router->post('/goals/{id}/units', [$goals, 'quantity']);

$books = new BooksController();
$router->get('/books', [$books, 'index']);
$router->post('/books', [$books, 'store']);
$router->post('/books/target', [$books, 'target']);
$router->post('/books/{id}', [$books, 'update']);
$router->post('/books/{id}/delete', [$books, 'destroy']);

$habits = new HabitsController();
$router->get('/habits', [$habits, 'index']);
$router->post('/habits', [$habits, 'store']);
$router->post('/habits/reorder', [$habits, 'reorder']);
$router->post('/habits/log', [$habits, 'log']);
$router->post('/habits/{id}/archive', [$habits, 'archive']);
$router->post('/habits/{id}/delete', [$habits, 'destroy']);
$router->post('/habits/{id}/log', [$habits, 'log']);
$router->post('/habits/{id}/months', [$habits, 'toggleMonth']);
$router->post('/habits/{id}/units', [$habits, 'bumpUnits']);
$router->post('/habits/{id}', [$habits, 'update']);

$metrics = new MetricsController();
$router->get('/metrics', [$metrics, 'index']);
$router->get('/api/metrics/goals', [$metrics, 'apiGoals']);
$router->get('/api/metrics/habits', [$metrics, 'apiHabits']);
$router->get('/api/metrics/consolidated', [$metrics, 'apiConsolidated']);

$reviews = new ReviewsController();
$router->get('/reviews', [$reviews, 'index']);
$router->post('/reviews', [$reviews, 'store']);

$router->get('/horizon', [new HorizonController(), 'index']);
$horizon = new HorizonController();
$router->post('/horizon', [$horizon, 'store']);
$router->post('/horizon/{id}', [$horizon, 'update']);
$router->post('/horizon/{id}/delete', [$horizon, 'destroy']);

$weekly = new WeeklyPlanController();
$router->get('/weekly', [$weekly, 'index']);
$router->post('/weekly', [$weekly, 'store']);
$router->post('/weekly/{id}', [$weekly, 'update']);
$router->post('/weekly/{id}/delete', [$weekly, 'destroy']);
$router->post('/weekly/{id}/toggle', [$weekly, 'toggle']);

$router->get('/archive', [new ArchiveController(), 'index']);

$settings = new SettingsController();
$router->get('/settings', [$settings, 'index']);
$router->post('/settings/profile', [$settings, 'updateProfile']);
$router->post('/settings/password', [$settings, 'updatePassword']);
$router->post('/settings/theme', [$settings, 'updateTheme']);
$router->post('/settings/areas', [$settings, 'updateAreas']);
$router->post('/settings/areas/{id}/delete', [$settings, 'destroyArea']);

$method = request_method();
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
