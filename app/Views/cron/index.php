<?php
$title = 'Cron Job Manager';
require __DIR__ . '/../layouts/header.php';
?>
<div class="os-content">
    <div class="content-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Cron Job Manager</h1>
            <p>Lập lịch tác vụ tự động và thông báo qua Telegram.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="openJobModal()">+ Thêm tác vụ</button>
            <button class="btn btn-ghost" onclick="openTelegramModal()">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" class="mr-1"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                Cấu hình Telegram
            </button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên tác vụ</th>
                        <th>Lịch trình</th>
                        <th>Lệnh thực thi</th>
                        <th>Lần chạy cuối</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có tác vụ nào được lập lịch.</td></tr>
                    <?php else: foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <strong><?= tms_h($job['name']) ?></strong>
                                <?php if ($job['notify_telegram']): ?>
                                    <span class="badge badge-info-soft ml-1">Telegram</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= tms_h($job['schedule']) ?></code></td>
                            <td><code class="text-muted"><?= tms_h(substr($job['command'], 0, 30)) ?><?= strlen($job['command']) > 30 ? '...' : '' ?></code></td>
                            <td><?= $job['last_run'] ? date('H:i d/m', strtotime($job['last_run'])) : '---' ?></td>
                            <td>
                                <?php if ($job['last_status'] === 'success'): ?>
                                    <span class="badge badge-success">Thành công</span>
                                <?php elseif ($job['last_status'] === 'failed'): ?>
                                    <span class="badge badge-danger">Thất bại</span>
                                <?php else: ?>
                                    <span class="badge badge-ghost">Chờ chạy</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-ghost" onclick='editJob(<?= json_encode($job) ?>)'>Sửa</button>
                                    <button class="btn btn-sm btn-danger-soft" onclick="deleteJob('<?= $job['id'] ?>')">Xóa</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Job Modal -->
<div id="jobModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="jobModalTitle">Thêm tác vụ mới</h3>
            <button class="close-modal" onclick="closeJobModal()">&times;</button>
        </div>
        <form id="jobForm" onsubmit="handleSaveJob(event)">
            <input type="hidden" name="id" id="jobId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tên tác vụ</label>
                    <input type="text" name="name" id="jobName" class="form-control" placeholder="ví dụ: Backup Database" required>
                </div>
                <div class="form-group">
                    <label>Lịch trình (Cron format)</label>
                    <input type="text" name="schedule" id="jobSchedule" class="form-control" placeholder="* * * * *" value="0 0 * * *" required>
                    <small class="text-muted">Phút | Giờ | Ngày | Tháng | Thứ (ví dụ: 0 0 * * * là hàng ngày lúc 0h)</small>
                </div>
                <div class="form-group">
                    <label>Lệnh thực thi</label>
                    <textarea name="command" id="jobCommand" class="form-control" rows="3" placeholder="ví dụ: bash /path/to/script.sh" required></textarea>
                </div>
                <div class="form-group d-flex align-items-center gap-2 mt-3">
                    <input type="checkbox" name="notify_telegram" id="jobNotify" value="1">
                    <label for="jobNotify" class="mb-0">Thông báo qua Telegram khi hoàn thành</label>
                </div>
                <div class="form-group d-flex align-items-center gap-2 mt-2">
                    <input type="checkbox" name="enabled" id="jobEnabled" value="1" checked>
                    <label for="jobEnabled" class="mb-0">Kích hoạt tác vụ này</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeJobModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu tác vụ</button>
            </div>
        </form>
    </div>
</div>

<!-- Telegram Modal -->
<div id="telegramModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Cấu hình Telegram Bot</h3>
            <button class="close-modal" onclick="closeTelegramModal()">&times;</button>
        </div>
        <form id="telegramForm" onsubmit="handleSaveTelegram(event)">
            <div class="modal-body">
                <p class="text-muted mb-4" style="font-size: 14px;">Tạo Bot qua @BotFather để nhận thông báo từ hệ thống.</p>
                <div class="form-group">
                    <label>Bot Token</label>
                    <input type="text" name="token" class="form-control" value="<?= tms_h($telegram['token']) ?>" placeholder="123456789:ABCdef..." required>
                </div>
                <div class="form-group">
                    <label>Chat ID (Cá nhân hoặc Nhóm)</label>
                    <input type="text" name="chat_id" class="form-control" value="<?= tms_h($telegram['chat_id']) ?>" placeholder="987654321" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeTelegramModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
.modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 24px; width: 90%; max-width: 550px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f9f9f9; }
.modal-body { padding: 24px; }
.modal-footer { padding: 20px 24px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 12px; background: #f9f9f9; }
.close-modal { background: none; border: none; font-size: 28px; cursor: pointer; color: #999; }
</style>

<script>
function openJobModal() {
    document.getElementById('jobId').value = '';
    document.getElementById('jobForm').reset();
    document.getElementById('jobModalTitle').innerText = 'Thêm tác vụ mới';
    document.getElementById('jobModal').style.display = 'block';
}

function editJob(job) {
    document.getElementById('jobId').value = job.id;
    document.getElementById('jobName').value = job.name;
    document.getElementById('jobSchedule').value = job.schedule;
    document.getElementById('jobCommand').value = job.command;
    document.getElementById('jobNotify').checked = !!job.notify_telegram;
    document.getElementById('jobEnabled').checked = !!job.enabled;
    document.getElementById('jobModalTitle').innerText = 'Chỉnh sửa tác vụ';
    document.getElementById('jobModal').style.display = 'block';
}

function closeJobModal() {
    document.getElementById('jobModal').style.display = 'none';
}

function openTelegramModal() {
    document.getElementById('telegramModal').style.display = 'block';
}

function closeTelegramModal() {
    document.getElementById('telegramModal').style.display = 'none';
}

async function handleSaveJob(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    data.notify_telegram = document.getElementById('jobNotify').checked;
    data.enabled = document.getElementById('jobEnabled').checked;
    
    try {
        const res = await fetch('/cron/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.ok) {
            tms_toast(result.message, 'success');
            closeJobModal();
            location.reload();
        } else {
            tms_toast(result.message, 'danger');
        }
    } catch (err) {
        tms_toast('Lỗi kết nối máy chủ', 'danger');
    }
}

async function deleteJob(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa tác vụ này?')) return;
    try {
        const res = await fetch('/cron/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.ok) {
            tms_toast(result.message, 'success');
            location.reload();
        }
    } catch (err) {
        tms_toast('Lỗi kết nối máy chủ', 'danger');
    }
}

async function handleSaveTelegram(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const res = await fetch('/cron/telegram', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.ok) {
            tms_toast(result.message, 'success');
            closeTelegramModal();
        }
    } catch (err) {
        tms_toast('Lỗi kết nối máy chủ', 'danger');
    }
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
