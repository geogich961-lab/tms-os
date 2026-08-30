<?php
declare(strict_types=1);
$authService=new AuthService();$systemService=new SystemService();$fileService=new FileManagerService();$websiteService=new WebsiteService();$databaseService=new DatabaseService();$sqlQueryService=new SqlQueryService();$backupService=new BackupService();$logService=new LogService();$networkService=new NetworkService();$terminalService=new TerminalService();$diagnosticsService=new DiagnosticsService();$pluginService=new PluginService();$monitoringService=new MonitoringService($systemService);$updateService=new UpdateService();$moduleService=new ModuleService();$serviceManagerService=new ServiceManagerService();$guardianService=new GuardianService();
$cronJobService=new CronJobService();$cfdomainService=new CloudflareDomainService();$telegramCommandService=new TelegramCommandService($cronJobService,$monitoringService,$cfdomainService,null,$updateService);$accessReportService=new AccessReportService($cronJobService,$telegramCommandService);$autoBackupService=new AutoBackupService($backupService,$cronJobService);

$authController=new AuthController($authService);$dashboardController=new DashboardController($authService,$systemService,$websiteService,$networkService);$fileController=new FileManagerController($authService,$fileService);$websiteController=new WebsiteController($authService,$websiteService,$backupService);$databaseController=new DatabaseController($authService,$databaseService);$sqlController=new SqlController($authService,$sqlQueryService);$backupController=new BackupController($authService,$backupService,$websiteService,$autoBackupService);$logController=new LogController($authService,$logService);$settingsController=new SettingsController($authService);$networkController=new NetworkController($authService,$networkService,$websiteService);$terminalController=new TerminalController($authService,$terminalService);$diagnosticsController=new DiagnosticsController($authService,$diagnosticsService);$pluginController=new PluginController($authService,$pluginService);$monitoringController=new MonitoringController($authService,$monitoringService);$notificationController=new NotificationController($authService,$systemService);$updateController=new UpdateController($authService,$updateService);$moduleController=new ModuleController($authService,$moduleService);$serviceManagerController=new ServiceManagerController($authService,$serviceManagerService);$guardianController=new GuardianController($authService,$guardianService);
$cronController=new CronController($authService,$cronJobService,$telegramCommandService,$accessReportService);$telegramWebhookController=new TelegramWebhookController($telegramCommandService,$authService);


// ===== Public routes (không yêu cầu đăng nhập) =====
$router->public('/login');
$router->public('/telegram/webhook');
$router->public('/');
$router->public('/status');
$router->public('/api/public-status');
// /api/updates/run tự xác thực bằng token riêng trong controller, không dùng session.
$router->public('/api/updates/run');

$router->get('/login', fn()=>$authController->loginForm());
$router->post('/login', fn()=>$authController->login());
$router->post('/logout', fn()=>$authController->logout());
$router->post('/telegram/webhook', fn()=>$telegramWebhookController->webhook());
// ===== Dashboard =====
$router->get('/', fn()=>$dashboardController->landing());
$router->get('/dashboard', fn()=>$dashboardController->index());
$router->post('/service/action', fn()=>$dashboardController->action());
// ===== File Manager =====
$router->get('/files', fn()=>$fileController->index());
$router->get('/files/editor', fn()=>$fileController->editor());
$router->get('/files/download', fn()=>$fileController->download());
$router->post('/files/upload', fn()=>$fileController->upload());
$router->post('/files/create', fn()=>$fileController->create());
$router->post('/files/rename', fn()=>$fileController->rename());
$router->post('/files/delete', fn()=>$fileController->delete());
$router->post('/files/archive', fn()=>$fileController->archive());
$router->post('/files/extract', fn()=>$fileController->extract());
$router->post('/files/save', fn()=>$fileController->save());
$router->post('/files/chmod', fn()=>$fileController->chmod());
$router->post('/files/copy', fn()=>$fileController->copy());
$router->post('/files/move', fn()=>$fileController->move());
$router->get('/files/perms', fn()=>$fileController->perms());
$router->post('/files/perms/apply', fn()=>$fileController->applyPerms());
// ===== Websites =====
$router->get('/websites', fn()=>$websiteController->index());
$router->get('/websites/logs', fn()=>$websiteController->logs());
$router->post('/websites/create', fn()=>$websiteController->create());
$router->post('/websites/clone', fn()=>$websiteController->clone());
$router->post('/websites/snapshot', fn()=>$websiteController->snapshot());
$router->post('/websites/delete', fn()=>$websiteController->delete());
$router->post('/websites/action', fn()=>$websiteController->action());
$router->post('/websites/update', fn()=>$websiteController->update());
$router->post('/websites/domains', fn()=>$websiteController->domains());
$router->get('/websites/hosts', fn()=>$websiteController->hosts());
// ===== SQL + Databases =====
$router->get('/sql', fn()=>$sqlController->index());
$router->get('/api/sql/databases', fn()=>$sqlController->apiList());
$router->post('/api/sql/query', fn()=>$sqlController->apiQuery());
$router->get('/api/sql/tables', fn()=>$sqlController->apiTables());
$router->get('/api/sql/structure', fn()=>$sqlController->apiStructure());
$router->post('/api/sql/save-cell', fn()=>$sqlController->apiSaveCell());
$router->post('/api/sql/delete-row', fn()=>$sqlController->apiDeleteRow());
$router->post('/api/sql/insert-row', fn()=>$sqlController->apiInsertRow());
$router->get('/databases', fn()=>$databaseController->index());
$router->post('/databases/create', fn()=>$databaseController->create());
$router->post('/databases/drop', fn()=>$databaseController->drop());
$router->post('/databases/export', fn()=>$databaseController->export());
$router->post('/databases/import', fn()=>$databaseController->import());
$router->post('/databases/adopt', fn()=>$databaseController->adopt());
// ===== Network / Terminal / Diagnostics =====
$router->get('/network', fn()=>$networkController->index());
$router->get('/terminal', fn()=>$terminalController->index());
$router->post('/terminal/run', fn()=>$terminalController->run());
$router->get('/diagnostics', fn()=>$diagnosticsController->index());
$router->post('/diagnostics/repair', fn()=>$diagnosticsController->repair());
// ===== Backups / Settings =====
$router->get('/backups', fn()=>$backupController->index());
$router->get('/backups/download', fn()=>$backupController->download());
$router->post('/backups/create', fn()=>$backupController->create());
$router->post('/backups/restore', fn()=>$backupController->restore());
$router->post('/backups/lock', fn()=>$backupController->lock());
$router->post('/backups/delete', fn()=>$backupController->delete());
$router->post('/backups/auto/config', fn()=>$backupController->autoSave());
$router->post('/backups/auto/run', fn()=>$backupController->autoRun());
$router->get('/logs', fn()=>$logController->index());
$router->get('/settings', fn()=>$settingsController->index());
$router->post('/settings/password', fn()=>$settingsController->password());
$router->post('/settings/appearance', fn()=>$settingsController->appearance());
$router->post('/settings/logo', fn()=>$settingsController->logo());
$router->post('/settings/cache', fn()=>$settingsController->cache());
$cfdomainController=new CloudflareDomainController($authService,$cfdomainService,$websiteService);
// ===== Cloudflare hosting (public status + API quản trị) =====
$router->get('/status', fn()=>$cfdomainController->publicStatusPage());
$router->get('/api/public-status', fn()=>$cfdomainController->publicStatus());
$router->get('/cf-hosting', fn()=>$cfdomainController->index());
$router->get('/api/cloudflare-domain/status', fn()=>$cfdomainController->status());
$router->get('/api/cloudflare-domain/account-info', fn()=>$cfdomainController->accountInfo());
$router->get('/api/cloudflare-domain/internal-sites', fn()=>$cfdomainController->internalSites());
$router->post('/api/cloudflare-domain/perf-status', fn()=>$cfdomainController->perfStatus());
$router->post('/api/cloudflare-domain/perf-optimize', fn()=>$cfdomainController->perfOptimize());
$router->post('/api/cloudflare-domain/sync-routes', fn()=>$cfdomainController->syncRoutes());
$router->get('/api/cloudflare-domain/dns-records', fn()=>$cfdomainController->dnsRecords());
$router->post('/api/cloudflare-domain/token', fn()=>$cfdomainController->saveToken());
$router->post('/api/cloudflare-domain/create-tunnel', fn()=>$cfdomainController->createTunnel());
$router->post('/api/cloudflare-domain/attach', fn()=>$cfdomainController->attach());
$router->post('/api/cloudflare-domain/start', fn()=>$cfdomainController->start());
$router->post('/api/cloudflare-domain/stop', fn()=>$cfdomainController->stop());
$router->post('/api/cloudflare-domain/public-wifi-dns', fn()=>$cfdomainController->publicWifiDns());
$router->post('/api/cloudflare-domain/detach', fn()=>$cfdomainController->detach());
$router->post('/api/cloudflare-domain/delete-tunnel', fn()=>$cfdomainController->deleteTunnel());
$router->post('/api/cloudflare-domain/uninstall', fn()=>$cfdomainController->uninstall());
$router->post('/api/cloudflare-domain/attach-panel', fn()=>$cfdomainController->attachPanel());
$router->post('/api/cloudflare-domain/detach-panel', fn()=>$cfdomainController->detachPanel());
$router->get('/internet-access', fn()=>tms_redirect('/cf-hosting'));
$router->get('/cloudflare', fn()=>tms_redirect('/cf-hosting'));
// ===== Guardian / Services / Modules =====
$router->get('/guardian', fn()=>$guardianController->index());
$router->get('/api/guardian', fn()=>$guardianController->api());
$router->post('/guardian/action', fn()=>$guardianController->action());
$router->post('/guardian/settings', fn()=>$guardianController->settings());
$router->get('/services', fn()=>$serviceManagerController->index());
$router->post('/services/action', fn()=>$serviceManagerController->action());
$router->post('/services/restart-all', fn()=>$serviceManagerController->restartAll());
$router->get('/api/services', fn()=>$serviceManagerController->api());
$router->post('/services/autostart', fn()=>$serviceManagerController->autostart());
$router->get('/services/log', fn()=>$serviceManagerController->log());
$router->get('/modules', fn()=>$moduleController->index());
$router->post('/modules/toggle', fn()=>$moduleController->toggle());
$router->post('/modules/repair', fn()=>$moduleController->repair());
$router->get('/plugins', fn()=>tms_redirect('/modules'));
// ===== Packages =====
$router->get('/packages', fn()=>$pluginController->index());
$router->get('/api/packages/status', fn()=>$pluginController->status());
$router->post('/packages/install', fn()=>$pluginController->install());
$router->post('/packages/update', fn()=>$pluginController->update());
$router->post('/packages/remove', fn()=>$pluginController->remove());
$router->post('/plugins/install', fn()=>$pluginController->install());
$router->post('/plugins/update', fn()=>$pluginController->update());
// ===== Cron + Telegram commands =====
$router->get('/cron', fn()=>$cronController->index());
$router->post('/cron/save', fn()=>$cronController->save());
$router->post('/cron/delete', fn()=>$cronController->delete());
$router->post('/cron/telegram', fn()=>$cronController->saveTelegram());
$router->post('/api/access-reports/enable', fn()=>$cronController->enableAccessReport());
$router->post('/api/access-reports/disable', fn()=>$cronController->disableAccessReport());
$router->post('/api/access-reports/test', fn()=>$cronController->testAccessReport());
$router->get('/api/telegram-commands/status', fn()=>$telegramWebhookController->status());
$router->post('/api/telegram-commands/enable', fn()=>$telegramWebhookController->enable());
$router->post('/api/telegram-commands/disable', fn()=>$telegramWebhookController->disable());
// ===== Monitoring / Notifications / Updates =====
$router->get('/monitoring', fn()=>$monitoringController->index());
$router->get('/api/monitoring', fn()=>$monitoringController->api());
$router->get('/notifications', fn()=>$notificationController->index());
$router->get('/api/notifications/status', fn()=>$notificationController->status());
$router->get('/updates', fn()=>$updateController->index());
$router->get('/api/updates/check', fn()=>$updateController->check());
$router->get('/api/updates/diagnose', fn()=>$updateController->diagnose());
$router->get('/api/updates/job-status', fn()=>$updateController->jobStatus());
$router->get('/api/updates/status', fn()=>$updateController->apiStatus());
$router->post('/updates/stage', fn()=>$updateController->stage());
$router->post('/updates/apply', fn()=>$updateController->apply());
$router->post('/updates/staged/apply', fn()=>$updateController->stagedApply());
$router->post('/updates/rollback', fn()=>$updateController->rollback());
$router->post('/updates/delete', fn()=>$updateController->delete());
$router->post('/updates/password', fn()=>$updateController->configurePassword());
$router->post('/updates/password/remove', fn()=>$updateController->clearPassword());
$router->post('/api/updates/run', fn()=>$updateController->apiRun());
