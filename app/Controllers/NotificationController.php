<?php
declare(strict_types=1);
final class NotificationController
{
    public function __construct(private AuthService $auth,private SystemService $system,private OperationalAlertsService $alerts){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    private function csrf():void{if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');}
    public function index():void{$this->guard();tms_view('notifications.index',['services'=>$this->system->serviceStatus(),'alerts'=>$this->alerts->status(),'csrf'=>tms_csrf_token()]);}
    public function status():void{$this->guard();header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode(['services'=>$this->system->serviceStatus(),'time'=>time()]);}
    public function alertsSave():void{$this->guard();try{$this->csrf();$config=$this->alerts->saveConfig($_POST);tms_flash('success',$config['enabled']?'Đã bật cảnh báo vận hành — kiểm tra mỗi 15 phút qua Telegram.':'Đã tắt cảnh báo vận hành.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/notifications');}
    public function alertsRun():void{$this->guard();try{$this->csrf();$r=$this->alerts->run();tms_flash('success',count($r['alerts'])>0?('Đã gửi '.count($r['alerts']).' cảnh báo qua Telegram.'):'Không có cảnh báo vượt ngưỡng hiện tại.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/notifications');}
}
