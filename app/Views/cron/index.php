<?php
$title = 'Cron Jobs';
$showShell = true;
$jobs = is_array($jobs ?? null) ? $jobs : [];
$totalJobs = count($jobs);
$enabledJobs = count(array_filter($jobs, static fn(array $job): bool => !array_key_exists('enabled', $job) || (bool)$job['enabled']));
$successfulJobs = count(array_filter($jobs, static fn(array $job): bool => ($job['last_status'] ?? '') === 'success'));
$telegramJobs = count(array_filter($jobs, static fn(array $job): bool => !empty($job['notify_telegram'])));
$telegramReady = !empty($telegram['token']) && !empty($telegram['chat_id']);
require __DIR__ . '/../layouts/header.php';
?>

<section class="cron-page">
    <header class="page-head cron-page-head">
        <div>
            <p class="eyebrow">Automation Center</p>
            <h1>Cron Jobs</h1>
            <p class="page-subtitle">Lập lịch tác vụ nền, theo dõi kết quả thực thi và nhận thông báo Telegram trong một nơi.</p>
        </div>
        <div class="row-actions head-actions cron-head-actions">
            <button type="button" class="btn btn-secondary" onclick="openTelegramModal()">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m21.8 3-7.3 18-4.2-7.1L3 9.7 21.8 3Z"/><path d="m10.3 13.9 4.2-3.9"/></svg>
                Thông báo Telegram
            </button>
            <button type="button" class="btn btn-primary" onclick="openJobModal()">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Tạo tác vụ
            </button>
        </div>
    </header>

    <div class="cron-summary-grid" aria-label="Tổng quan Cron Jobs">
        <article class="panel-card cron-summary-card">
            <span class="cron-summary-icon cron-summary-icon-primary" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.4v5l3.4 2"/></svg>
            </span>
            <div><span>Tổng tác vụ</span><strong><?= tms_h((string)$totalJobs) ?></strong><small>Đã được tạo trong hệ thống</small></div>
        </article>
        <article class="panel-card cron-summary-card">
            <span class="cron-summary-icon cron-summary-icon-success" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4.1 4.1L19 6.3"/></svg>
            </span>
            <div><span>Đang kích hoạt</span><strong><?= tms_h((string)$enabledJobs) ?></strong><small><?= $totalJobs ? tms_h((string)($totalJobs - $enabledJobs)) . ' đang tạm dừng' : 'Sẵn sàng lập lịch mới' ?></small></div>
        </article>
        <article class="panel-card cron-summary-card">
            <span class="cron-summary-icon cron-summary-icon-info" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 12h3l2-5 4 10 2-5h5"/></svg>
            </span>
            <div><span>Chạy thành công</span><strong><?= tms_h((string)$successfulJobs) ?></strong><small><?= $totalJobs ? 'Theo lần chạy gần nhất' : 'Chưa có dữ liệu thực thi' ?></small></div>
        </article>
        <article class="panel-card cron-summary-card">
            <span class="cron-summary-icon <?= $telegramReady ? 'cron-summary-icon-success' : 'cron-summary-icon-warning' ?>" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m21.8 3-7.3 18-4.2-7.1L3 9.7 21.8 3Z"/><path d="m10.3 13.9 4.2-3.9"/></svg>
            </span>
            <div><span>Telegram</span><strong><?= tms_h((string)$telegramJobs) ?></strong><small><?= $telegramReady ? 'Kênh thông báo đã sẵn sàng' : 'Cần hoàn tất cấu hình bot' ?></small></div>
        </article>
    </div>

    <section class="panel-card cron-notify-panel <?= $telegramReady ? 'is-ready' : 'needs-setup' ?>">
        <div class="cron-notify-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m21.8 3-7.3 18-4.2-7.1L3 9.7 21.8 3Z"/><path d="m10.3 13.9 4.2-3.9"/></svg>
        </div>
        <div class="cron-notify-copy">
            <strong><?= $telegramReady ? 'Thông báo Telegram đang sẵn sàng' : 'Chưa thiết lập thông báo Telegram' ?></strong>
            <span><?= $telegramReady ? 'Tác vụ có bật Telegram sẽ gửi kết quả sau mỗi lần thực thi.' : 'Kết nối bot để nhận kết quả Cron trực tiếp trên Telegram.' ?></span>
        </div>
        <button type="button" class="btn <?= $telegramReady ? 'btn-ghost' : 'btn-primary' ?> btn-small" onclick="openTelegramModal()">
            <?= $telegramReady ? 'Cập nhật cấu hình' : 'Thiết lập ngay' ?>
        </button>
    </section>

    <section class="cron-workspace">
        <div class="section-title-row cron-section-title">
            <div>
                <h2>Tác vụ đã lập lịch</h2>
                <p><?= $totalJobs ? 'Xem trạng thái mới nhất và quản lý từng tác vụ.' : 'Tạo tác vụ đầu tiên để tự động hóa vận hành máy chủ.' ?></p>
            </div>
            <?php if ($totalJobs): ?><span class="cron-count"><?= tms_h((string)$totalJobs) ?> tác vụ</span><?php endif; ?>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="panel-card cron-empty-state">
                <div class="cron-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5l3.5 2"/></svg></div>
                <h3>Chưa có tác vụ nào</h3>
                <p>Tạo Cron Job để chạy backup, đồng bộ hoặc lệnh bảo trì theo lịch của bạn.</p>
                <button type="button" class="btn btn-primary" onclick="openJobModal()">Tạo tác vụ đầu tiên</button>
            </div>
        <?php else: ?>
            <div class="cron-job-list">
                <?php foreach ($jobs as $job):
                    $isEnabled = !array_key_exists('enabled', $job) || (bool)$job['enabled'];
                    $runStatus = $job['last_status'] ?? '';
                    $telegramStatus = $job['telegram_last_status'] ?? '';
                    $telegramMessage = (string)($job['telegram_last_message'] ?? '');
                    $lastRun = !empty($job['last_run']) ? date('H:i · d/m', strtotime((string)$job['last_run'])) : 'Chưa chạy';
                    ?>
                    <article class="panel-card cron-job-card <?= $isEnabled ? '' : 'is-paused' ?>">
                        <div class="cron-job-main">
                            <div class="cron-job-heading">
                                <span class="cron-job-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5l3.5 2"/></svg>
                                </span>
                                <div class="cron-job-title">
                                    <h3><?= tms_h((string)$job['name']) ?></h3>
                                    <div class="cron-job-tags">
                                        <span class="cron-state <?= $isEnabled ? 'state-active' : 'state-paused' ?>"><i></i><?= $isEnabled ? 'Đang bật' : 'Tạm dừng' ?></span>
                                        <?php if (!empty($job['notify_telegram'])): ?>
                                            <span class="cron-telegram-state <?= $telegramStatus === 'sent' ? 'sent' : ($telegramStatus ? 'failed' : '') ?>" <?= $telegramMessage ? 'title="' . tms_h($telegramMessage) . '"' : '' ?>>
                                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m21.8 3-7.3 18-4.2-7.1L3 9.7 21.8 3Z"/><path d="m10.3 13.9 4.2-3.9"/></svg>
                                                <?= $telegramStatus === 'sent' ? 'Đã gửi Telegram' : ($telegramStatus ? 'Telegram lỗi' : 'Telegram bật') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="cron-command"><span>Lệnh thực thi</span><code><?= tms_h((string)$job['command']) ?></code></div>
                        </div>

                        <dl class="cron-job-meta">
                            <div><dt>Lịch chạy</dt><dd><code><?= tms_h((string)$job['schedule']) ?></code></dd></div>
                            <div><dt>Lần chạy cuối</dt><dd><?= tms_h($lastRun) ?></dd></div>
                            <div><dt>Kết quả</dt><dd>
                                <?php if ($runStatus === 'success'): ?><span class="status-pill running">Thành công</span>
                                <?php elseif ($runStatus === 'failed'): ?><span class="status-pill stopped">Thất bại</span>
                                <?php else: ?><span class="status-pill pending">Chờ chạy</span><?php endif; ?>
                            </dd></div>
                        </dl>

                        <div class="cron-job-actions">
                            <button type="button" class="btn btn-secondary btn-small" onclick='editJob(<?= json_encode($job, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z"/></svg>Sửa
                            </button>
                            <button type="button" class="btn btn-danger-soft btn-small" onclick="deleteJob('<?= tms_h((string)$job['id']) ?>')" aria-label="Xóa <?= tms_h((string)$job['name']) ?>">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M10 11v6M14 11v6M9 7l1-3h4l1 3M6 7l1 13h10l1-13"/></svg><span>Xóa</span>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<dialog class="tms-dialog cron-dialog" id="jobModal" aria-labelledby="jobModalTitle">
    <form id="jobForm" class="cron-dialog-shell" onsubmit="handleSaveJob(event)">
        <input type="hidden" name="id" id="jobId">
        <header class="dialog-head cron-dialog-head">
            <div><p class="eyebrow">Automation</p><h2 id="jobModalTitle">Tạo tác vụ mới</h2><small>Thiết lập lịch chạy và lệnh hệ thống an toàn.</small></div>
            <button type="button" data-cron-dialog-close="jobModal" aria-label="Đóng">×</button>
        </header>
        <div class="cron-dialog-body">
            <label class="cron-field cron-field-wide"><span>Tên tác vụ</span><input type="text" name="name" id="jobName" placeholder="Ví dụ: Sao lưu database mỗi ngày" maxlength="100" required></label>
            <label class="cron-field cron-field-wide"><span>Lệnh thực thi</span><textarea name="command" id="jobCommand" rows="4" spellcheck="false" placeholder="Ví dụ: bash ~/scripts/backup-database.sh" required></textarea><small>Lệnh được thực thi trong môi trường Termux của TMS OS.</small></label>
            <div class="cron-field cron-field-wide"><span>Lịch trình Cron</span><div class="cron-schedule-input"><input type="text" name="schedule" id="jobSchedule" value="0 0 * * *" spellcheck="false" placeholder="0 0 * * *" required><code id="cronSchedulePreview">0 0 * * *</code></div><small>Thứ tự: phút · giờ · ngày · tháng · thứ trong tuần.</small><div class="cron-presets" aria-label="Lịch chạy nhanh"><button type="button" data-cron-preset="* * * * *">Mỗi phút</button><button type="button" data-cron-preset="0 * * * *">Mỗi giờ</button><button type="button" data-cron-preset="0 0 * * *">Mỗi ngày</button><button type="button" data-cron-preset="0 0 * * 0">Mỗi tuần</button></div></div>
            <div class="cron-toggle-grid">
                <label class="cron-toggle-card"><input type="checkbox" name="enabled" id="jobEnabled" value="1" checked><span class="cron-toggle-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v10"/><path d="M18.4 5.6a8.5 8.5 0 1 1-12.8 0"/></svg></span><span><strong>Kích hoạt tác vụ</strong><small>Tác vụ sẽ được nạp vào crontab.</small></span><i class="cron-switch" aria-hidden="true"></i></label>
                <label class="cron-toggle-card"><input type="checkbox" name="notify_telegram" id="jobNotify" value="1"><span class="cron-toggle-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m21.8 3-7.3 18-4.2-7.1L3 9.7 21.8 3Z"/><path d="m10.3 13.9 4.2-3.9"/></svg></span><span><strong>Thông báo Telegram</strong><small>Gửi kết quả sau khi chạy xong.</small></span><i class="cron-switch" aria-hidden="true"></i></label>
            </div>
        </div>
        <footer class="dialog-actions cron-dialog-actions"><button type="button" class="btn btn-ghost" data-cron-dialog-close="jobModal">Hủy</button><button type="submit" class="btn btn-primary">Lưu tác vụ</button></footer>
    </form>
</dialog>

<dialog class="tms-dialog cron-dialog" id="telegramModal" aria-labelledby="telegramModalTitle">
    <form id="telegramForm" class="cron-dialog-shell" onsubmit="handleSaveTelegram(event)">
        <header class="dialog-head cron-dialog-head">
            <div><p class="eyebrow">Notification Channel</p><h2 id="telegramModalTitle">Thông báo Telegram</h2><small>Kết nối một chat riêng hoặc nhóm để nhận kết quả Cron.</small></div>
            <button type="button" data-cron-dialog-close="telegramModal" aria-label="Đóng">×</button>
        </header>
        <div class="cron-dialog-body">
            <div class="cron-security-note"><span aria-hidden="true">✓</span><p>Bot Token được giữ trong thiết bị. TMS OS không hiển thị lại Token đã lưu.</p></div>
            <label class="cron-field"><span>Bot Token</span><input type="password" name="token" value="" placeholder="<?= !empty($telegram['token']) ? 'Đã lưu an toàn — chỉ nhập để thay đổi' : '123456789:ABCdef...' ?>" autocomplete="new-password" autocapitalize="off" spellcheck="false"><small><?= !empty($telegram['token']) ? 'Để trống nếu chỉ thay đổi Chat ID.' : 'Tạo bot qua @BotFather trước khi cấu hình.' ?></small></label>
            <label class="cron-field"><span>Chat ID</span><input type="text" name="chat_id" value="<?= tms_h((string)($telegram['chat_id'] ?? '')) ?>" placeholder="987654321" inputmode="numeric" required><small>Dùng ID tài khoản cá nhân hoặc nhóm; không dùng ID của chính bot.</small></label>
        </div>
        <footer class="dialog-actions cron-dialog-actions"><button type="button" class="btn btn-ghost" data-cron-dialog-close="telegramModal">Hủy</button><button type="submit" class="btn btn-primary">Lưu cấu hình</button></footer>
    </form>
</dialog>

<script>
(function () {
    const getDialog = (id) => document.getElementById(id);
    const showDialog = (id) => { const dialog = getDialog(id); if (dialog && !dialog.open) dialog.showModal(); };
    const closeDialog = (id) => { const dialog = getDialog(id); if (dialog && dialog.open) dialog.close(); };
    window.openJobModal = function () {
        const form = document.getElementById('jobForm');
        form.reset();
        document.getElementById('jobId').value = '';
        document.getElementById('jobSchedule').value = '0 0 * * *';
        document.getElementById('jobEnabled').checked = true;
        document.getElementById('jobModalTitle').textContent = 'Tạo tác vụ mới';
        syncSchedulePreview();
        showDialog('jobModal');
    };
    window.editJob = function (job) {
        document.getElementById('jobId').value = job.id || '';
        document.getElementById('jobName').value = job.name || '';
        document.getElementById('jobSchedule').value = job.schedule || '';
        document.getElementById('jobCommand').value = job.command || '';
        document.getElementById('jobNotify').checked = !!job.notify_telegram;
        document.getElementById('jobEnabled').checked = job.enabled !== false;
        document.getElementById('jobModalTitle').textContent = 'Chỉnh sửa tác vụ';
        syncSchedulePreview();
        showDialog('jobModal');
    };
    window.openTelegramModal = () => showDialog('telegramModal');
    document.querySelectorAll('[data-cron-dialog-close]').forEach((button) => button.addEventListener('click', () => closeDialog(button.dataset.cronDialogClose)));
    document.querySelectorAll('.cron-dialog').forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));
    const scheduleInput = document.getElementById('jobSchedule');
    const schedulePreview = document.getElementById('cronSchedulePreview');
    function syncSchedulePreview() { if (scheduleInput && schedulePreview) schedulePreview.textContent = scheduleInput.value.trim() || '—'; }
    scheduleInput.addEventListener('input', syncSchedulePreview);
    document.querySelectorAll('[data-cron-preset]').forEach((button) => button.addEventListener('click', () => { scheduleInput.value = button.dataset.cronPreset; syncSchedulePreview(); scheduleInput.focus(); }));
})();

async function handleSaveJob(event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target).entries());
    data.notify_telegram = document.getElementById('jobNotify').checked;
    data.enabled = document.getElementById('jobEnabled').checked;
    try {
        const response = await fetch('/cron/save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await response.json();
        if (!result.ok) throw new Error(result.message || 'Không thể lưu tác vụ.');
        tmsToast(result.message, 'success');
        document.getElementById('jobModal').close();
        window.setTimeout(() => window.location.reload(), 280);
    } catch (error) { tmsToast(error.message || 'Lỗi kết nối máy chủ', 'error'); }
}

async function deleteJob(id) {
    if (!confirm('Xóa tác vụ này? Lịch chạy tương ứng cũng sẽ được gỡ khỏi hệ thống.')) return;
    try {
        const response = await fetch('/cron/delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const result = await response.json();
        if (!result.ok) throw new Error(result.message || 'Không thể xóa tác vụ.');
        tmsToast(result.message, 'success');
        window.setTimeout(() => window.location.reload(), 280);
    } catch (error) { tmsToast(error.message || 'Lỗi kết nối máy chủ', 'error'); }
}

async function handleSaveTelegram(event) {
    event.preventDefault();
    try {
        const response = await fetch('/cron/telegram', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(event.target).entries())) });
        const result = await response.json();
        if (!result.ok) throw new Error(result.message || 'Không thể lưu cấu hình Telegram.');
        tmsToast(result.message, 'success');
        document.getElementById('telegramModal').close();
        window.setTimeout(() => window.location.reload(), 280);
    } catch (error) { tmsToast(error.message || 'Lỗi kết nối máy chủ', 'error'); }
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
