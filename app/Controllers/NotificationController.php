<?php
declare(strict_types=1);
final class NotificationController
{
    public function __construct(private AuthService $auth,private SystemService $system){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    public function index():void{$this->guard();tms_view('notifications.index',['services'=>$this->system->serviceStatus()]);}
    public function status():void{$this->guard();header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode(['services'=>$this->system->serviceStatus(),'time'=>time()]);}
}
