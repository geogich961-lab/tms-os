<?php
declare(strict_types=1);
final class SettingsController
{
    public function __construct(private AuthService $auth){}
    private function guard(): void { if(!$this->auth->check()) tms_redirect('/login'); }

    public function index():void
    {
        $this->guard();
        tms_view('settings.index',[
            'flash'=>tms_pull_flash(),
            'csrf'=>tms_csrf_token(),
            'ui'=>tms_ui_settings(),
        ]);
    }

    public function password():void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)) tms_redirect('/settings');
        $new=(string)($_POST['new_password']??'');
        $confirm=(string)($_POST['confirm_password']??'');
        if(strlen($new)<8||$new!==$confirm){
            tms_flash('error','Mật khẩu phải từ 8 ký tự và xác nhận phải khớp.');
            tms_redirect('/settings');
        }
        $home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $dir=$home.'/.tms-os/config'; @mkdir($dir,0700,true); @chmod($dir,0700); $file=$dir.'/panel-secret.php';
        $hash=password_hash($new,PASSWORD_DEFAULT);
        $data="<?php\nreturn ['username'=>'admin','password_hash'=>".var_export($hash,true)."]\n;";
        file_put_contents($file,$data,LOCK_EX);
        chmod($file,0600);
        tms_flash('success','Đã đổi mật khẩu.');
        tms_redirect('/settings');
    }

    public function appearance(): void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)) tms_redirect('/settings');
        $defaults=tms_ui_defaults();
        $accent=tms_valid_hex_color($_POST['accent']??'', $defaults['accent']);
        $secondary=tms_valid_hex_color($_POST['accent_secondary']??'', $defaults['accent_secondary']);
        $background=tms_valid_hex_color($_POST['pwa_background']??'', $defaults['pwa_background']);
        $theme=in_array($_POST['default_theme']??'', ['light','dark'], true)?(string)$_POST['default_theme']:'light';
        $home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $dir=$home.'/.tms-os';
        @mkdir($dir,0700,true);
        $file=$dir.'/ui-settings.json';
        $payload=[
            'accent'=>$accent,
            'accent_secondary'=>$secondary,
            'pwa_background'=>$background,
            'default_theme'=>$theme,
        ];
        if(file_put_contents($file,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false){
            tms_flash('error','Không thể lưu cấu hình giao diện.');
            tms_redirect('/settings');
        }
        @chmod($file,0600);
        setcookie('tms_theme',$theme,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);
        tms_flash('success','Đã lưu màu giao diện. Hãy đóng và mở lại PWA để màu thanh trạng thái cập nhật hoàn toàn.');
        tms_redirect('/settings');
    }
}
