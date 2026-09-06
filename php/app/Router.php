<?php
/**
 * 精简路由：method + path pattern -> [Controller, action]
 */
declare(strict_types=1);

namespace App;

class Router
{
    private static array $routes = [];

    public static function add(string $method, string $pattern, string $handler): void
    {
        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public static function get(string $pattern, string $handler): void  { self::add('GET', $pattern, $handler); }
    public static function post(string $pattern, string $handler): void { self::add('POST', $pattern, $handler); }
    public static function put(string $pattern, string $handler): void  { self::add('PUT', $pattern, $handler); }
    public static function delete(string $pattern, string $handler): void { self::add('DELETE', $pattern, $handler); }
    public static function any(string $pattern, string $handler): void  { self::add('*', $pattern, $handler); }

    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // 某些虚拟主机仅支持 GET/POST：通过 header X-HTTP-Method-Override 或表单 _method 覆盖
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ($_POST['_method'] ?? '');
        if (in_array(strtoupper($override), ['PUT', 'DELETE', 'PATCH'], true)) {
            $method = strtoupper($override);
        }
        // 支持 _method 覆盖（某些虚拟服务器只支持 GET/POST）
        $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rtrim($rawPath ?? '/', '/');
        if ($path === '') $path = '/';

        Log::info("DISPATCH {$method} {$path}");

        // 去掉 base 前缀（若部署在子目录，可在此处理）；默认部署在域名根
        $pathMatched = false;
        foreach (self::$routes as $route) {
            $params = [];
            if (self::match($route['pattern'], $path, $params)) {
                $pathMatched = true;
                // 路径匹配但方法不符 → 继续寻找后续同路径的其它方法
                if ($route['method'] !== '*' && $route['method'] !== $method) {
                    continue;
                }
                [$class, $action] = explode('@', $route['handler']);
                $class = 'App\\Controllers\\' . $class;
                if (!class_exists($class)) {
                    Response::error('控制器不存在: ' . $class, 500, 500);
                    return;
                }
                $controller = new $class();
                $args = self::coerceArgs($class, $action, $params);
                $controller->{$action}(...$args);
                return;
            }
        }
        if ($pathMatched) {
            Log::info("405 on {$method} {$path}");
            Response::json(['code' => 405, 'message' => '请求方法不支持'], 405);
            return;
        }
        Log::info("404 on {$method} {$path}");
        Response::error('接口不存在', 404, 404);
    }

    /** 按控制器方法的参数类型，将路径参数转为对应类型（int/string） */
    private static function coerceArgs(string $class, string $action, array $params): array
    {
        $args = [];
        try {
            $rm = new \ReflectionMethod($class, $action);
            $names = array_keys($params);
            $ordered = [];
            foreach ($rm->getParameters() as $i => $p) {
                $name = $p->getName();
                $val = $params[$name] ?? ($names[$i] ?? null);
                $type = $p->getType();
                if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
                    $ordered[$name] = is_numeric($val) ? (int) $val : $val;
                } else {
                    $ordered[$name] = (string) $val;
                }
            }
            // 按方法参数顺序输出
            foreach ($rm->getParameters() as $p) {
                if (isset($ordered[$p->getName()])) {
                    $args[] = $ordered[$p->getName()];
                }
            }
        } catch (\Throwable $e) {
            return array_values($params);
        }
        return $args;
    }

    private static function match(string $pattern, string $path, array &$params): bool
    {
        // pattern: /skinlib/show/:tid ; :xxx 匹配单个路径段
        $regex = '#^' . preg_replace('/(:[a-zA-Z_][a-zA-Z0-9_]*)/', '([^/]+)', $pattern) . '$#';
        if (preg_match($regex, $path, $m)) {
            array_shift($m);
            preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $pattern, $names);
            foreach ($names[1] as $i => $name) {
                $params[$name] = $m[$i] ?? null;
            }
            return true;
        }
        return false;
    }
}