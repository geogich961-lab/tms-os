<?php
declare(strict_types=1);
final class MonitoringController
{
    public function __construct(private AuthService $auth,private MonitoringService $monitor){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    public function index():void{$this->guard();tms_view('monitoring.index',['data'=>$this->monitor->snapshot()]);}
    public function api():void{$this->guard();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($this->monitor->snapshot(),JSON_UNESCAPED_UNICODE);}
}
