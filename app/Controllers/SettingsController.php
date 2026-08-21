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
        $toastDuration = max(1, min(60, (int)($_POST['toast_duration'] ?? 5)));
        $home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $dir=$home.'/.tms-os';
        @mkdir($dir,0700,true);
        $file=$dir.'/ui-settings.json';
        $payload=[
            'accent'=>$accent,
            'accent_secondary'=>$secondary,
            'pwa_background'=>$background,
            'default_theme'=>$theme,
            'toast_duration'=>$toastDuration,
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

    public function logo(): void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)) tms_redirect('/settings');
        $upload=$_FILES['logo']??null;
        $check=tms_validate_logo_upload(is_array($upload)?$upload:[]);
        if(!$check['ok']){
            tms_flash('error',(string)$check['message']);
            tms_redirect('/settings');
        }
        $home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $brandDir=$home.'/.tms-os/brand';
        if(!is_dir($brandDir) && !@mkdir($brandDir,0700,true)){
            tms_flash('error','Không thể tạo thư mục lưu logo.');
            tms_redirect('/settings');
        }
        $raw=@file_get_contents($upload['tmp_name']);
        if($raw===false){
            tms_flash('error','Không thể đọc tệp đã tải lên.');
            tms_redirect('/settings');
        }
        $src=imagecreatefromstring($raw);
        if($src===false){
            tms_flash('error','Không thể đọc hình ảnh đã tải lên.');
            tms_redirect('/settings');
        }
        $w=imagesx($src);
        $h=imagesy($src);
        $side=min($w,$h);
        $square=imagecreatetruecolor($side,$side);
        imagecopy($square,$src,0,0,(int)(($w-$side)/2),(int)(($h-$side)/2),$side,$side);
        $public=dirname(__DIR__,2).'/public/assets';
        $icons=$public.'/icons';
        foreach(['logo-tms-os.png'=>512,'icon-512.png'=>512,'icon-192.png'=>192,'logo-splash.png'=>192] as $dest=>$size){
            $canvas=imagecreatetruecolor($size,$size);
            imagecopyresampled($canvas,$square,0,0,0,0,$size,$size,$side,$side);
            imagepng($canvas,$brandDir.'/'.$dest);
            if(is_dir($icons)){
                imagepng($canvas,$icons.'/'.$dest);
                @chmod($icons.'/'.$dest,0644);
            }
            @chmod($brandDir.'/'.$dest,0644);
            imagedestroy($canvas);
        }
        $maskSize=512;
        $safe=(int)round($maskSize*0.66);
        $mask=imagecreatetruecolor($maskSize,$maskSize);
        imagealphablending($mask,false);
        $transparent=imagecolorallocatealpha($mask,0,0,0,127);
        imagefill($mask,0,0,$transparent);
        imagecopyresampled($mask,$square,(int)(($maskSize-$safe)/2),(int)(($maskSize-$safe)/2),0,0,$safe,$safe,$side,$side);
        imagesavealpha($mask,true);
        imagepng($mask,$brandDir.'/icon-maskable-512.png');
        if(is_dir($icons)){
            imagepng($mask,$icons.'/icon-maskable-512.png');
            @chmod($icons.'/icon-maskable-512.png',0644);
        }
        @chmod($brandDir.'/icon-maskable-512.png',0644);
        imagedestroy($mask);
        // Icon solid: logo chiếm đầy icon (không viền trắng, không trong suốt) — dùng làm PWA icon
        foreach(['icon-192-solid.png'=>192,'icon-512-solid.png'=>512] as $dest=>$size){
            $canvas=imagecreatetruecolor($size,$size);
            imagecopyresampled($canvas,$square,0,0,0,0,$size,$size,$side,$side);
            imagepng($canvas,$brandDir.'/'.$dest);
            if(is_dir($icons)){
                imagepng($canvas,$icons.'/'.$dest);
                @chmod($icons.'/'.$dest,0644);
            }
            @chmod($brandDir.'/'.$dest,0644);
            imagedestroy($canvas);
        }
        // Maskable solid: nền trong suốt với logo giữa (Android adaptive icon)
        $maskSize=512;
        $safe=(int)round($maskSize*0.66);
        $mask=imagecreatetruecolor($maskSize,$maskSize);
        imagealphablending($mask,false);
        $transparent=imagecolorallocatealpha($mask,0,0,0,127);
        imagefill($mask,0,0,$transparent);
        imagecopyresampled($mask,$square,(int)(($maskSize-$safe)/2),(int)(($maskSize-$safe)/2),0,0,$safe,$safe,$side,$side);
        imagesavealpha($mask,true);
        imagepng($mask,$brandDir.'/icon-maskable-solid-512.png');
        if(is_dir($icons)){
            imagepng($mask,$icons.'/icon-maskable-solid-512.png');
            @chmod($icons.'/icon-maskable-solid-512.png',0644);
        }
        @chmod($brandDir.'/icon-maskable-solid-512.png',0644);
        imagedestroy($mask);
        imagedestroy($square);
        imagedestroy($src);
        tms_flash('success','Đã cập nhật logo. Hãy xóa biểu tượng cũ khỏi màn hình chính rồi bấm Cài TMS OS để biểu tượng mới có hiệu lực hoàn toàn.');
        tms_redirect('/settings');
    }

    public function cache(): void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)) tms_redirect('/settings');
        $result=tms_clear_cache();
        $removed=(int)$result['removed'];
        tms_flash('success',sprintf('Đã xóa cache thành công (%d tệp tạm/session cũ). Giao diện sẽ tải lại dữ liệu mới — nếu PWA vẫn hiển thị cũ, hãy tắt hẳn và mở lại ứng dụng.', $removed));
        tms_redirect('/settings');
    }
}
