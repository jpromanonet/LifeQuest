<?php

declare(strict_types=1);

final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,handler:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $pattern = $this->normalize($path);
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize($this->resolvePath($uri, $method));

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            ($route['handler'])(...array_values($params));
            return;
        }

        http_response_code(404);
        view('errors/404', ['title' => 'No encontrada']);
    }

    private function resolvePath(string $uri, string $method = 'GET'): string
    {
        // En POST, el body gana: los forms de modales envían r hidden (más fiable que ?r= en action).
        if ($method === 'POST' && isset($_POST['r']) && is_string($_POST['r']) && $_POST['r'] !== '') {
            $r = $_POST['r'];
            if (str_contains($r, '?')) {
                $r = explode('?', $r, 2)[0];
            }
            return $r;
        }

        if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
            $r = $_GET['r'];
            if (str_contains($r, '?')) {
                $r = explode('?', $r, 2)[0];
            }
            return $r;
        }

        if (isset($_POST['r']) && is_string($_POST['r']) && $_POST['r'] !== '') {
            $r = $_POST['r'];
            if (str_contains($r, '?')) {
                $r = explode('?', $r, 2)[0];
            }
            return $r;
        }

        foreach (['PATH_INFO', 'ORIG_PATH_INFO'] as $key) {
            $info = $_SERVER[$key] ?? '';
            if (is_string($info) && $info !== '' && $info !== '/') {
                return $info;
            }
        }

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $this->stripBasePath($path);

        if (str_starts_with($path, '/index.php')) {
            $rest = substr($path, strlen('/index.php'));
            return ($rest === false || $rest === '') ? '/' : $rest;
        }

        return $path === '' ? '/' : $path;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function stripBasePath(string $path): string
    {
        $base = function_exists('base_path') ? base_path() : '';
        if ($base !== '' && (str_starts_with($path, $base . '/') || $path === $base)) {
            return substr($path, strlen($base)) ?: '/';
        }

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            return substr($path, strlen($scriptDir)) ?: '/';
        }
        return $path;
    }
}
