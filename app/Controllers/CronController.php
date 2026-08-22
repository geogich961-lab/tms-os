<?php
declare(strict_types=1);

final class CronController
{
    public function __construct(
        private AuthService $auth,
        private CronJobService $cron,
        private TelegramCommandService $commands,
    ) {}

    private function guard(): void
    {
        if (!$this->auth->check()) {
            tms_redirect('/login');
        }
    }

    private function input(): array
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        return is_array($input) ? $input : $_POST;
    }

    private function guardJson(array $input): bool
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!$this->auth->check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Phiên đăng nhập đã hết hạn.']);
            return false;
        }
        $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? ''));
        if (!tms_verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Phiên không hợp lệ. Hãy tải lại trang rồi thử lại.']);
            return false;
        }
        return true;
    }

    public function index(): void
    {
        $this->guard();
        $jobs = $this->cron->all();
        $telegram = $this->cron->getTelegramConfig();
        $telegramCommandStatus = $this->commands->status();
        $csrf = tms_csrf_token();
        require __DIR__ . '/../Views/cron/index.php';
    }

    public function save(): void
    {
        $input = $this->input();
        if (!$this->guardJson($input)) return;
        try {
            $this->cron->save($input);
            echo json_encode(['ok' => true, 'message' => 'Đã lưu tác vụ thành công.']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $input = $this->input();
        if (!$this->guardJson($input)) return;
        try {
            $id = (string)($input['id'] ?? '');
            $this->cron->delete($id);
            echo json_encode(['ok' => true, 'message' => 'Đã xóa tác vụ.']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveTelegram(): void
    {
        $input = $this->input();
        if (!$this->guardJson($input)) return;
        try {
            $token = trim((string)($input['token'] ?? ''));
            $chatId = (string)($input['chat_id'] ?? '');
            if ($token === '') {
                $current = $this->cron->getTelegramConfig();
                $token = (string)($current['token'] ?? '');
            }
            $this->cron->saveTelegramConfig($token, $chatId);
            echo json_encode(['ok' => true, 'message' => 'Đã lưu cấu hình Telegram.']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
