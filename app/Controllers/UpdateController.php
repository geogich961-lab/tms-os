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

    /** API chỉ trả JSON; không chuyển hướng sang trang đăng nhập HTML. */
    private function apiGuard(): bool
    {
        if ($this->auth->check()) {
            return true;
        }
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'code' => 'AUTH_REQUIRED',
            'error' => 'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại để tiếp tục.',
        ], JSON_UNESCAPED_UNICODE);
        return false;
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
        if (!$this->apiGuard()) {
            return;
        }
        try {
            $result = $this->updates->check();
        } catch (Throwable $e) {
            $result = ['current' => $this->updates->currentVersion(), 'available' => null, 'error' => $e->getMessage()];
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /** API polling cho job Cập nhật nhanh; không trả token hay đường dẫn nội bộ. */
    public function jobStatus(): void
    {
        if (!$this->apiGuard()) {
            return;
        }
        $status = $this->updates->status();
        $state = is_array($status['state'] ?? null) ? $status['state'] : [];
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'current' => (string)($status['current'] ?? 'unknown'),
            'applying' => !empty($status['applying']),
            'job' => (string)($state['job'] ?? ''),
            'phase' => (string)($state['phase'] ?? 'idle'),
            'update_ok' => array_key_exists('ok', $state) ? (bool)$state['ok'] : null,
            'message' => (string)($state['message'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
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
        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
        if ($isAjax) {
            if (!$this->apiGuard()) {
                return;
            }
        } else {
            $this->guard();
        }
        try {
            $this->verify();
            $r = $this->updates->enqueueGitHubApply();
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => (bool)($r['ok'] ?? true),
                    'queued' => !empty($r['queued']),
                    'job' => (string)($r['job'] ?? ''),
                    'version' => (string)($r['version'] ?? ''),
                    'message' => (string)($r['message'] ?? ''),
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            tms_flash('success', $r['message']);
        } catch (Throwable $e) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                return;
            }
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
        if (!$this->apiGuard()) {
            return;
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'token' => $this->updates->token(),
            'current' => $this->updates->currentVersion(),
            'update' => $this->updates->status(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
