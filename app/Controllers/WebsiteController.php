<?php
declare(strict_types=1);
final class WebsiteController
{
    public function __construct(private AuthService $auth,private WebsiteService $sites,private BackupService $backups){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    private function csrf():void{if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');}
    public function index():void{$this->guard();tms_view('websites.index',['sites'=>$this->sites->all(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
    public function create():void{$this->guard();try{$this->csrf();$this->sites->create((string)($_POST['name']??''),(int)($_POST['port']??0));tms_flash('success','Đã tạo website và kiểm tra Nginx thành công.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function clone():void{$this->guard();try{$this->csrf();$this->sites->cloneSite((string)($_POST['source']??''),(string)($_POST['name']??''),(int)($_POST['port']??0));tms_flash('success','Đã nhân bản website thành công.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function snapshot():void{$this->guard();try{$this->csrf();tms_flash('success',$this->backups->create('website',(string)($_POST['name']??''),'Snapshot thủ công từ Website Control Center',false));}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function delete():void{$this->guard();try{$this->csrf();$name=(string)($_POST['name']??'');$this->backups->quickWebsiteSnapshot($name,'Tự động trước khi xóa website');$this->sites->delete($name,!empty($_POST['delete_files']));tms_flash('success','Đã xóa website. Snapshot an toàn đã được tạo trước thao tác.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function action():void{$this->guard();try{$this->csrf();$this->sites->action((string)($_POST['name']??''),(string)($_POST['action']??''));tms_flash('success','Đã thực hiện thao tác website.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function update():void{$this->guard();try{$this->csrf();$name=(string)($_POST['name']??'');$this->backups->quickWebsiteSnapshot($name,'Tự động trước khi đổi cổng');$this->sites->updatePort($name,(int)($_POST['port']??0));tms_flash('success','Đã cập nhật cổng website.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function domains():void{$this->guard();try{$this->csrf();$name=(string)($_POST['name']??'');$this->backups->quickWebsiteSnapshot($name,'Tự động trước khi đổi tên miền');$this->sites->updateDomains($name,(string)($_POST['local_domain']??''),(string)($_POST['lan_domain']??''));tms_flash('success','Đã áp dụng tên miền local và LAN.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/websites');}
    public function hosts():void{$this->guard();header('Content-Type: text/plain; charset=UTF-8');header('Content-Disposition: attachment; filename="tms-os-hosts.txt"');echo $this->sites->hostsFile();}
    public function logs():void{$this->guard();try{$name=(string)($_GET['name']??'');$logs=$this->sites->logs($name);tms_view('websites.logs',['name'=>$name,'logs'=>$logs]);}catch(Throwable $e){tms_flash('error',$e->getMessage());tms_redirect('/websites');}}
}
