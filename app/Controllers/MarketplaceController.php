<?php
declare(strict_types=1);

final class MarketplaceController
{
    public function __construct(private AuthService $auth, private AppInstallerService $installer) {}

    public function index(): void
    {
        if (!$this->auth->check()) { tms_redirect('/login'); return; }
        $catalog = $this->installer->catalog();
        $installed = $this->installer->installed();
        $csrf = tms_csrf_token();
        require __DIR__ . '/../Views/marketplace/index.php';
    }

    public function install(): void
    {
        header('Content-Type: application/json');
        if (!$this->auth->check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Vui lòng đăng nhập lại để cài ứng dụng.']);
            return;
        }
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? ''));
            if (!tms_verify_csrf($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Phiên làm việc không hợp lệ. Hãy tải lại trang rồi thử lại.']);
                return;
            }
            $result = $this->installer->install($input);
            echo json_encode($result);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
