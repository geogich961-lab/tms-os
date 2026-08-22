<?php
$title = 'App Marketplace';
require __DIR__ . '/../layouts/header.php';
?>
<div class="os-content">
    <div class="content-header">
        <div>
            <h1>App Marketplace</h1>
            <p>Cài đặt ứng dụng phổ biến chỉ với một chạm.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($catalog as $app): 
            $isInstalled = false;
            foreach ($installed as $inst) {
                if ($inst['app'] === $app['id']) {
                    $isInstalled = true;
                    break;
                }
            }
        ?>
            <div class="card app-card">
                <div class="card-body">
                    <div class="app-icon-wrapper mb-4">
                        <div class="app-icon-placeholder"><?= strtoupper(substr($app['id'], 0, 1)) ?></div>
                    </div>
                    <h3 class="mb-2"><?= tms_h($app['name']) ?></h3>
                    <p class="text-muted mb-4" style="font-size: 14px; min-height: 60px;"><?= tms_h($app['description']) ?></p>
                    <div class="app-meta mb-4">
                        <span class="badge badge-info-soft">Yêu cầu: <?= tms_h($app['requirements']) ?></span>
                    </div>
                    <button class="btn btn-primary btn-block" onclick="openInstallModal('<?= tms_h($app['id']) ?>', '<?= tms_h($app['name']) ?>', <?= $app['database'] ? 'true' : 'false' ?>)">
                        <?= $isInstalled ? 'Cài đặt thêm' : 'Cài đặt ngay' ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Install Modal -->
<div id="installModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Cài đặt ứng dụng</h3>
            <button class="close-modal" onclick="closeInstallModal()">&times;</button>
        </div>
        <form id="installForm" onsubmit="handleInstall(event)">
            <input type="hidden" id="appId" name="app">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tên định danh (không dấu)</label>
                    <input type="text" name="name" id="appName" class="form-control" placeholder="ví dụ: my-site" required>
                </div>
                <div class="form-group">
                    <label>Cổng truy cập (1024-65535)</label>
                    <input type="number" name="port" id="appPort" class="form-control" placeholder="8080" required>
                </div>
                
                <div id="dbFields" style="display: none;">
                    <hr class="my-4">
                    <h4 class="mb-3">Cấu hình Database</h4>
                    <div class="form-group">
                        <label>Tên Database</label>
                        <input type="text" name="db_name" class="form-control" placeholder="wp_db">
                    </div>
                    <div class="form-group">
                        <label>Tên User</label>
                        <input type="text" name="db_user" class="form-control" placeholder="wp_user">
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu User</label>
                        <input type="password" name="db_pass" class="form-control" placeholder="Tối thiểu 8 ký tự">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeInstallModal()">Hủy</button>
                <button type="submit" class="btn btn-primary" id="installBtn">Bắt đầu cài đặt</button>
            </div>
        </form>
    </div>
</div>

<style>
.app-card { transition: transform 0.2s; border: 1px solid rgba(0,0,0,0.05); }
.app-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.app-icon-placeholder { width: 48px; height: 48px; background: var(--primary); color: white; display: grid; place-items: center; border-radius: 12px; font-weight: 800; font-size: 20px; }
.modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
.modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 24px; width: 90%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f9f9f9; }
.modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
.modal-footer { padding: 20px 24px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 12px; background: #f9f9f9; }
.close-modal { background: none; border: none; font-size: 28px; cursor: pointer; color: #999; }
</style>

<script>
function openInstallModal(id, name, hasDb) {
    document.getElementById('appId').value = id;
    document.getElementById('modalTitle').innerText = 'Cài đặt ' + name;
    document.getElementById('dbFields').style.display = hasDb ? 'block' : 'none';
    document.getElementById('installModal').style.display = 'block';
    
    // Auto-fill suggested port
    const ports = [8080, 8081, 8082, 8888, 9000];
    document.getElementById('appPort').value = 8000 + Math.floor(Math.random() * 1000);
}

function closeInstallModal() {
    document.getElementById('installModal').style.display = 'none';
}

async function handleInstall(e) {
    e.preventDefault();
    const btn = document.getElementById('installBtn');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Đang cài đặt...';
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const res = await fetch('/marketplace/install', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.ok) {
            tmsToast(result.message, 'success');
            closeInstallModal();
            setTimeout(() => location.reload(), 2000);
        } else {
            tmsToast(result.message || 'Không thể cài đặt ứng dụng.', 'error');
        }
    } catch (err) {
        tmsToast('Lỗi kết nối máy chủ', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
