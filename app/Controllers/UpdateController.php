<?php
declare(strict_types=1);

final class UpdateController
{
    public function __construct(private AuthService $auth, private UpdateService $updates) {}

    private function guard(): void
    {
        if (!$this->auth->check()) {
            tms_redirect('/login');
        }
    }

    private function verify(): void
    {
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Phiên không hợp lệ.');
        }
    }

    public function index(): void
    {
        $this->guard();
        try {
            $status = $this->updates->status();
        } catch (Throwable) {
            $status = ['current' => 'unknown', 'previous_exists' => false, 'applying' => false, 'state' => []];
        }
        tms_view('updates.index', [
            'items' => $this->updates->staged(),
            'status' => $status,
            'flash' => tms_pull_flash(),
            'csrf' => tms_csrf_token(),
        ]);
    }

    public function check(): void
    {
        $this->guard();
        try {
            $result = $this->updates->check();
        } catch (Throwable $e) {
            $result = ['current' => $this->updates->currentVersion(), 'available' => null, 'error' => $e->getMessage()];
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function stage(): void
    {
        $this->guard();
        try {
            $this->verify();
            $r = $this->updates->stage($_FILES['package'] ?? []);
            tms_flash('success', $r['message']);
        } catch (Throwable $e) {
            tms_flash('error', $e->getMessage());
        }
        tms_redirect('/updates');
    }

    /** Tải bản mới nhất từ GitHub và áp dụng ngay (update 1 chạm). */
    public function apply(): void
    {
        $this->guard();
        try {
            $this->verify();
            $r = $this->updates->applyFromGitHub();
            tms_flash('success', $r['message']);
        } catch (Throwable $e) {
            tms_flash('error', $e->getMessage());
        }
        tms_redirect('/updates');
    }

    /** Áp dụng gói đã staging (upload thủ công). */
    public function stagedApply(): void
    {
        $this->guard();
        try {
            $this->verify();
            $r = $this->updates->apply((string)($_POST['name'] ?? ''));
            tms_flash('success', $r['message']);
        } catch (Throwable $e) {
            tms_flash('error', $e->getMessage());
        }
        tms_redirect('/updates');
    }

    /** Khôi phục về bản trước. */
    public function rollback(): void
    {
        $this->guard();
        try {
            $this->verify();
            $r = $this->updates->rollback();
            tms_flash('success', $r['message']);
        } catch (Throwable $e) {
            tms_flash('error', $e->getMessage());
        }
        tms_redirect('/updates');
    }

    public function delete(): void
    {
        $this->guard();
        try {
            $this->verify();
            $names = $_POST['names'] ?? ($_POST['name'] ?? null);
            if ($names === null) {
                throw new RuntimeException('Chưa chọn gói cần xóa.');
            }
            $this->updates->delete($names);
            tms_flash('success', 'Đã xóa các gói cập nhật được chọn.');
        } catch (Throwable $e) {
            tms_flash('error', $e->getMessage());
        }
        tms_redirect('/updates');
    }

    /** API: tự cập nhật 1 lệnh bằng token (không cần session UI). */
    public function apiRun(): void
    {
        $token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
        if (!$this->updates->verifyApiToken($token !== '' ? $token : null)) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'error' => 'Token không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $r = $this->updates->applyFromGitHub();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => $r['message'], 'backup' => $r['backup'] ?? ''], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** API: lấy token và trạng thái cho lệnh curl. */
    public function apiStatus(): void
    {
        $this->guard();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'token' => $this->updates->token(),
            'current' => $this->updates->currentVersion(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
