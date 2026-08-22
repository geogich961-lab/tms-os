<?php
declare(strict_types=1);

/** Webhook công khai: chỉ nhận Update Telegram đã xác thực bằng secret header. */
final class TelegramWebhookController
{
    public function __construct(
        private TelegramCommandService $commands,
        private AuthService $auth,
    ) {}

    public function webhook(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code(200);

        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength < 1 || $contentLength > 65536) {
            echo 'OK';
            return;
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || strlen($raw) > 65536) {
            echo 'OK';
            return;
        }
        $update = json_decode($raw, true);
        if (!is_array($update)) {
            echo 'OK';
            return;
        }

        try {
            $this->commands->processIncomingUpdate($update, $this->telegramSecretHeader());
        } catch (Throwable) {
            // Phản hồi chung để không làm lộ trạng thái hay nội dung Update.
        }
        echo 'OK';
    }

    public function status(): void
    {
        if (!$this->guardJson(false)) {
            return;
        }
        $this->json(['ok' => true, 'status' => $this->commands->status()]);
    }

    public function enable(): void
    {
        if (!$this->guardJson(true)) {
            return;
        }
        try {
            $result = $this->commands->enable();
            $this->json(['ok' => true, 'message' => $result['message'], 'status' => $this->commands->status()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $this->safeMessage($e)], 400);
        }
    }

    public function disable(): void
    {
        if (!$this->guardJson(true)) {
            return;
        }
        try {
            $result = $this->commands->disable();
            $this->json(['ok' => true, 'message' => $result['message'], 'status' => $this->commands->status()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $this->safeMessage($e)], 400);
        }
    }

    private function guardJson(bool $csrfRequired): bool
    {
        if (!$this->auth->check()) {
            $this->json(['ok' => false, 'message' => 'Phiên đăng nhập đã hết hạn.'], 401);
            return false;
        }
        if (!$csrfRequired) {
            return true;
        }
        $input = json_decode((string)file_get_contents('php://input'), true);
        $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? (is_array($input) ? ($input['csrf'] ?? '') : ($_POST['csrf'] ?? '')));
        if (!tms_verify_csrf($csrf)) {
            $this->json(['ok' => false, 'message' => 'Phiên không hợp lệ. Hãy tải lại trang rồi thử lại.'], 403);
            return false;
        }
        return true;
    }

    private function telegramSecretHeader(): string
    {
        $direct = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }
        if (function_exists('getallheaders')) {
            foreach ((array)getallheaders() as $name => $value) {
                if (strcasecmp((string)$name, 'X-Telegram-Bot-Api-Secret-Token') === 0) {
                    return (string)$value;
                }
            }
        }
        return '';
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function safeMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        return $message !== '' && strlen($message) <= 240 ? $message : 'Không thể hoàn tất thao tác Telegram.';
    }
}
