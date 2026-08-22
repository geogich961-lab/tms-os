<?php
declare(strict_types=1);

final class CronController
{
    public function __construct(private CronJobService $cron) {}

    public function index(): void
    {
        $jobs = $this->cron->all();
        $telegram = $this->cron->getTelegramConfig();
        require __DIR__ . '/../Views/cron/index.php';
    }

    public function save(): void
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $this->cron->save($input);
            echo json_encode(['ok' => true, 'message' => 'Đã lưu tác vụ thành công.']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
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
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
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
