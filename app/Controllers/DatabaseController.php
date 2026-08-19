<?php
declare(strict_types=1);
final class DatabaseController
{
 public function __construct(private AuthService $auth,private DatabaseService $db){}
 private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
 public function index():void{$this->guard();try{$items=$this->db->all();$error=null;}catch(Throwable $e){$items=[];$error=$e->getMessage();}
$modeFile=(getenv('HOME')?:'/data/data/com.termux/files/home').'/.tms-os/db-mode';
$driver=is_file($modeFile)?trim((string)@file_get_contents($modeFile)):'mariadb';
$driver=$driver==='sqlite'?'SQLite':'MariaDB';
tms_view('databases.index',['databases'=>$items,'error'=>$error,'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token(),'driver'=>$driver]);}
 public function create():void{$this->act(fn()=>$this->db->create((string)$_POST['name']),'Đã tạo database.');}
 public function drop():void{$this->act(fn()=>$this->db->drop((string)$_POST['name']),'Đã xóa database.');}
 public function export():void{$this->act(fn()=>$this->db->export((string)$_POST['name']),'Đã xuất database vào thư mục backups/database.');}
 public function import():void{$this->act(fn()=>$this->db->import((string)$_POST['name'],$_FILES['sql']??[]),'Đã nhập SQL.');}
 private function act(callable $fn,string $msg):void{$this->guard();if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên không hợp lệ.');tms_redirect('/databases');}try{$fn();tms_flash('success',$msg);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/databases');}
}
