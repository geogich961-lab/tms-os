<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    /** Các đường dẫn công khai, không yêu cầu đăng nhập (mặc định là từ chối). */
    private array $publicPaths = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /** Đăng ký đường dẫn không cần xác thực; mọi route khác mặc định yêu cầu đăng nhập. */
    public function public(string $path): void
    {
        $this->publicPaths[$path] = true;
    }

    public function dispatch(string $method, string $uri, ?callable $authCheck = null): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo '404 - Không tìm thấy trang';
            return;
        }

        // Hàng rào tập trung: controller vẫn giữ guard() riêng như lớp phòng thủ
        // thứ hai, nhưng quên guard() không còn làm lộ endpoint nữa.
        if (!isset($this->publicPaths[$path]) && $authCheck !== null && !$authCheck()) {
            $this->denyUnauthenticated($path);
            return;
        }

        $handler();
    }

    private function denyUnauthenticated(string $path): void
    {
        if (str_starts_with($path, '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'code' => 'AUTH_REQUIRED',
                'error' => 'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại để tiếp tục.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        tms_redirect('/login?next=' . rawurlencode($path));
    }
}
