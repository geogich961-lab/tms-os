<?php
declare(strict_types=1);
final class DatabaseController
{
 public function __construct(private AuthService $auth,private DatabaseService $db){}
 private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
 public function index():void{$this->guard();
 try{$items=$this->db->all();$error=null;}catch(Throwable $e){$items=[];$error=$e->getMessage();}
 $modeFile=(getenv('HOME')?:'/data/data/com.termux/files/home').'/.tms-os/db-mode';
 $driver=is_file($modeFile)?trim((string)@file_get_contents($modeFile)):'mariadb';
 $driver=$driver==='sqlite'?'SQLite':'MariaDB';
 // Chuẩn hóa: trong SQLite mode, items là mảng có cấu trúc (name/source/path/site/size);
 // MariaDB mode trả mảng tên thuần. View nhận cả hai dạng.
 $databases=[];
 foreach($items as $item){
   if(is_array($item)){
     $databases[]=['name'=>$item['name'],'source'=>$item['source'],'site'=>$item['site'],'size'=>$item['size'],'path'=>$item['path'],'db_key'=>$item['db_key'] ?? (($item['source'] ?? '')==='website' ? 'w__'.($item['site'] ?? '').'__'.md5($item['path'] ?? '') : 'm__'.($item['name'] ?? ''))];
   }else{
     $databases[]=['name'=>(string)$item,'source'=>'managed','site'=>'','size'=>0,'path'=>'','db_key'=>'m__'.(string)$item];
   }
 }
 tms_view('databases.index',['databases'=>$databases,'error'=>$error,'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token(),'driver'=>$driver]);}
 public function create():void{$this->act(fn()=>$this->db->create((string)$_POST['name']),'Đã tạo database.');}
 public function drop():void{$this->act(fn()=>$this->db->drop((string)$_POST['name']),'Đã xóa database.');}
 public function export():void{
   $path=(string)($_POST['path'] ?? '');
   $this->act(function() use ($path){
     if($path !== ''){
       // Website DB: xuất theo đường dẫn file (đã kiểm tra safePath trong service)
       $this->db->exportByPath($path);
       return;
     }
     $name=(string)$_POST['name'];
     $this->db->export($name);
   },'Đã xuất database vào thư mục backups/database.');}
 public function import():void{$this->act(fn()=>$this->db->import((string)$_POST['name'],$_FILES['sql']??[]),'Đã nhập SQL.');}
 /** V15.2.1: mang file SQLite từ website về thư mục quản lý của TMS OS. */
 public function adopt():void{
   $path=(string)($_POST['path'] ?? '');
   $this->act(fn()=>$this->db->moveToManaged($path),'Đã mang database về thư mục quản lý. Bạn có thể quản lý nó như database thông thường.');}
 private function act(callable $fn,string $msg):void{$this->guard();if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên không hợp lệ.');tms_redirect('/databases');}try{$fn();tms_flash('success',$msg);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/databases');}
}
