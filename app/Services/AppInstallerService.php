<?php
declare(strict_types=1);

final class AppInstallerService
{
    private string $home;
    private string $appsFile;

    public function __construct(private WebsiteService $websites, private DatabaseService $databases)
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        @mkdir($this->home . '/.tms-os',0700,true);
        $this->appsFile=$this->home.'/.tms-os/apps.json';
    }

    public function catalog(): array
    {
        return [
            ['id'=>'wordpress','name'=>'WordPress','description'=>'CMS phổ biến cho blog và website doanh nghiệp. Hỗ trợ cấu hình tự động.','requirements'=>'cURL, PHP, MariaDB','database'=>true],
            ['id'=>'adminer','name'=>'Adminer','description'=>'Quản trị MariaDB bằng một file PHP nhỏ gọn.','requirements'=>'cURL, PHP','database'=>false],
            ['id'=>'phpinfo','name'=>'PHP Info','description'=>'Trang kiểm tra cấu hình PHP trên website riêng.','requirements'=>'PHP','database'=>false],
            ['id'=>'static-landing','name'=>'Static Landing','description'=>'Landing page HTML/CSS responsive, không cần database.','requirements'=>'Nginx','database'=>false],
            ['id'=>'php-api','name'=>'PHP JSON API','description'=>'API mẫu có health endpoint và phản hồi JSON an toàn.','requirements'=>'PHP','database'=>false],
        ];
    }

    public function installed(): array
    {
        $data=@json_decode((string)@file_get_contents($this->appsFile),true);
        return is_array($data)?array_values($data):[];
    }

    public function install(array $input): array
    {
        $app=preg_replace('/[^a-z0-9_-]/','',(string)($input['app']??''));
        $name=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($input['name']??''));
        $port=(int)($input['port']??0);
        if(strlen($name)<2) throw new RuntimeException('Tên ứng dụng không hợp lệ.');
        if($port<1024||$port>65535) throw new RuntimeException('Cổng không hợp lệ.');

        $this->websites->create($name,$port);
        $root=$this->home.'/websites/'.$name.'/public';
        try {
            $resultMessage='Đã cài ứng dụng '.$name.'.';
            if($app==='phpinfo') {
                file_put_contents($root.'/index.php',"<?php phpinfo();\n",LOCK_EX);
            } elseif($app==='static-landing') {
                $html='<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($name,ENT_QUOTES).'</title><style>body{font-family:system-ui;margin:0;background:#f4f7ff;color:#17213d}.hero{min-height:100vh;display:grid;place-items:center;padding:24px}.card{max-width:680px;background:white;padding:36px;border-radius:24px;box-shadow:0 20px 60px #1d4ed822}h1{font-size:clamp(34px,8vw,64px);margin:0 0 14px}p{font-size:18px;line-height:1.7}</style><main class="hero"><section class="card"><h1>'.htmlspecialchars($name,ENT_QUOTES).'</h1><p>Website đang chạy trên TMS OS Mini VPS.</p></section></main></html>';
                file_put_contents($root.'/index.html',$html,LOCK_EX);
                @unlink($root.'/index.php');
            } elseif($app==='php-api') {
                $api="<?php\ndeclare(strict_types=1);\nheader('Content-Type: application/json; charset=utf-8');\nheader('Cache-Control: no-store');\n\$path=parse_url(\$_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);\nif(\$path==='/health'){echo json_encode(['ok'=>true,'service'=>'".$name."','time'=>date('c')],JSON_UNESCAPED_UNICODE);exit;}\necho json_encode(['message'=>'TMS OS PHP API','endpoint'=>'/health'],JSON_UNESCAPED_UNICODE);\n";
                file_put_contents($root.'/index.php',$api,LOCK_EX);
            } elseif($app==='adminer') {
                $this->download('https://github.com/vrana/adminer/releases/latest/download/adminer.php',$root.'/index.php');
            } elseif($app==='wordpress') {
                $dbName=preg_replace('/[^a-zA-Z0-9_]/','',(string)($input['db_name']??''));
                $dbUser=preg_replace('/[^a-zA-Z0-9_]/','',(string)($input['db_user']??''));
                $dbPass=(string)($input['db_pass']??'');
                if(strlen($dbName)<2||strlen($dbUser)<2||strlen($dbPass)<8) throw new RuntimeException('Thông tin database chưa hợp lệ; mật khẩu cần ít nhất 8 ký tự.');
                $this->databases->create($dbName);
                $this->databases->createUserForDatabase($dbName,$dbUser,$dbPass);
                $this->verifyDatabaseLogin($dbName,$dbUser,$dbPass);

                $zip=$this->home.'/.tms-os/wordpress.zip';
                $this->download('https://wordpress.org/latest.zip',$zip);
                $this->extractZip($zip,$root);
                $nested=$root.'/wordpress';
                if(is_dir($nested)) $this->moveContents($nested,$root);
                @unlink($zip);
                $sample=$root.'/wp-config-sample.php';
                if(!is_file($sample)) throw new RuntimeException('Không tìm thấy wp-config-sample.php trong gói WordPress.');
                $cfg=(string)file_get_contents($sample);
                $cfg=str_replace(
                    ['database_name_here','username_here','password_here',"define( 'DB_HOST', 'localhost' );"],
                    [$dbName,$dbUser,$dbPass,"define( 'DB_HOST', '127.0.0.1:3306' );"],
                    $cfg
                );
                $cfg=$this->injectWordPressSalts($cfg);
                $dynamicHost="\n/* TMS_DYNAMIC_HOST: support local, LAN and Cloudflare domains */\nif (!empty(\$_SERVER['HTTP_HOST'])) {\n    \$tmsScheme = (!empty(\$_SERVER['HTTP_X_FORWARDED_PROTO']) ? \$_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));\n    define('WP_HOME', \$tmsScheme . '://' . \$_SERVER['HTTP_HOST']);\n    define('WP_SITEURL', \$tmsScheme . '://' . \$_SERVER['HTTP_HOST']);\n}\n";
                $cfg=preg_replace('/\/\* That\'s all, stop editing!.*?\*\//s',$dynamicHost."\n$0",$cfg,1)??($cfg.$dynamicHost);
                if(file_put_contents($root.'/wp-config.php',$cfg,LOCK_EX)===false) throw new RuntimeException('Không thể tạo wp-config.php.');
                @chmod($root.'/wp-config.php',0600);

                $autoSetup=!empty($input['wp_auto_setup']);
                if($autoSetup){
                    $siteTitle=trim((string)($input['wp_site_title']??'')) ?: $name;
                    $adminUser=preg_replace('/[^A-Za-z0-9_.@-]/','',(string)($input['wp_admin_user']??'admin')) ?: 'admin';
                    $adminPass=(string)($input['wp_admin_pass']??'');
                    if(strlen($adminPass)<10) $adminPass=$this->randomPassword(18);
                    $adminEmail=filter_var((string)($input['wp_admin_email']??''),FILTER_VALIDATE_EMAIL) ?: 'admin@localhost.local';
                    $language=in_array((string)($input['wp_language']??'vi'),['vi','en_US'],true)?(string)$input['wp_language']:'vi';
                    $this->installWordPressCore($root,$port,$siteTitle,$adminUser,$adminPass,$adminEmail,$language);
                    $resultMessage="Đã cài WordPress hoàn chỉnh.\nWebsite: http://127.0.0.1:{$port}\nQuản trị: http://127.0.0.1:{$port}/wp-admin\nTài khoản: {$adminUser}\nMật khẩu: {$adminPass}";
                } else {
                    $resultMessage="Đã chuẩn bị WordPress. Mở http://127.0.0.1:{$port} để hoàn tất cài đặt.";
                }
            } else throw new RuntimeException('Ứng dụng không được hỗ trợ.');

            $items=$this->readApps();
            $items[$name]=['app'=>$app,'name'=>$name,'port'=>$port,'installed_at'=>date('c'),'health'=>'ready'];
            file_put_contents($this->appsFile,json_encode($items,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX);
            return ['ok'=>true,'message'=>$resultMessage];
        } catch(Throwable $e) {
            try { $this->websites->delete($name,true); } catch(Throwable) {}
            if ($app === 'wordpress') {
                try { $dbName=preg_replace('/[^a-zA-Z0-9_]/','',(string)($input['db_name']??'')); if($dbName!=='')$this->databases->drop($dbName); } catch(Throwable) {}
                try { $dbUser=preg_replace('/[^a-zA-Z0-9_]/','',(string)($input['db_user']??'')); if($dbUser!=='')$this->databases->dropUser($dbUser); } catch(Throwable) {}
            }
            throw $e;
        }
    }

    private function verifyDatabaseLogin(string $db,string $user,string $pass): void
    {
        if(!class_exists('mysqli')) throw new RuntimeException('PHP mysqli chưa được bật.');
        mysqli_report(MYSQLI_REPORT_OFF);
        $m=@new mysqli('127.0.0.1',$user,$pass,$db,3306);
        if($m->connect_errno){
            $error=$m->connect_error;
            @$m->close();
            throw new RuntimeException('Không thể xác minh tài khoản database vừa tạo: '.$error);
        }
        $m->close();
    }

    private function installWordPressCore(string $root,int $port,string $title,string $user,string $pass,string $email,string $language): void
    {
        $php=trim((string)shell_exec('command -v php 2>/dev/null'));
        if($php===''||!is_executable($php)) throw new RuntimeException('Không tìm thấy PHP CLI để cấu hình WordPress tự động.');
        $bootstrap=$this->home.'/.tms-os/wp-install-'.bin2hex(random_bytes(4)).'.php';
        $code=<<<'CODE'
<?php
$root=$argv[1];$port=(int)$argv[2];$title=$argv[3];$user=$argv[4];$pass=$argv[5];$email=$argv[6];$language=$argv[7];
$_SERVER['HTTP_HOST']='127.0.0.1:'.$port;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['SERVER_PORT']=(string)$port;$_SERVER['REQUEST_URI']='/';$_SERVER['HTTPS']='off';
define('WP_INSTALLING',true);
require $root.'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
$result=wp_install($title,$user,$email,true,'',$pass,$language);
if(is_wp_error($result)){fwrite(STDERR,$result->get_error_message());exit(2);} 
update_option('siteurl','http://127.0.0.1:'.$port);update_option('home','http://127.0.0.1:'.$port);
echo 'OK';
CODE;
        file_put_contents($bootstrap,$code,LOCK_EX);
        @chmod($bootstrap,0700);
        $args=[$php,$bootstrap,$root,(string)$port,$title,$user,$pass,$email,$language];
        $command=implode(' ',array_map('escapeshellarg',$args)).' 2>&1';
        exec($command,$output,$status);
        @unlink($bootstrap);
        if($status!==0) throw new RuntimeException('WordPress đã tải xong nhưng cấu hình tự động thất bại: '.trim(implode("\n",$output)));
    }

    private function injectWordPressSalts(string $cfg): string
    {
        $keys=['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'];
        foreach($keys as $key){
            $value=base64_encode(random_bytes(48));
            $cfg=preg_replace("/define\(\s*'".$key."'\s*,\s*'put your unique phrase here'\s*\);/","define( '".$key."', '".$value."' );",$cfg)??$cfg;
        }
        return $cfg;
    }

    private function randomPassword(int $length): string
    {
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $out='';for($i=0;$i<$length;$i++)$out.=$alphabet[random_int(0,strlen($alphabet)-1)];return $out;
    }

    private function download(string $url,string $dest): void
    {
        if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL chưa được bật.');
        $fp=fopen($dest,'wb'); if(!$fp) throw new RuntimeException('Không thể tạo file tải xuống.');
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>240,CURLOPT_CONNECTTIMEOUT=>25,CURLOPT_USERAGENT=>'TMS-OS-V10']);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);fclose($fp);
        if(!$ok||$status>=400){@unlink($dest);throw new RuntimeException('Tải xuống thất bại: '.($error?:'HTTP '.$status));}
    }

    private function extractZip(string $zip,string $dest): void
    {
        $z=new ZipArchive();if($z->open($zip)!==true)throw new RuntimeException('Không mở được ZIP WordPress.');
        for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i); if(str_contains($n,'../')||str_starts_with($n,'/')){$z->close();throw new RuntimeException('ZIP không an toàn.');}}
        if(!$z->extractTo($dest)){$z->close();throw new RuntimeException('Không giải nén được WordPress.');}$z->close();
    }

    private function moveContents(string $from,string $to): void
    {
        foreach(scandir($from)?:[] as $item){if($item==='.'||$item==='..')continue;@rename($from.'/'.$item,$to.'/'.$item);}@rmdir($from);
    }
    private function readApps(): array { $d=@json_decode((string)@file_get_contents($this->appsFile),true);return is_array($d)?$d:[]; }
}
