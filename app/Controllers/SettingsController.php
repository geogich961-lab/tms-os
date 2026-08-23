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
        try {
            $this->auth->changePassword($new);
        } catch (Throwable $error) {
            tms_flash('error', 'Không thể đổi mật khẩu. Vui lòng thử lại.');
            tms_redirect('/settings');
            return;
        }
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
        // 1. Đọc và giải mã ảnh tiết kiệm bộ nhớ
        $src = null;
        $mime = $check['type'];
        if ($mime === IMAGETYPE_PNG) $src = @imagecreatefrompng($upload['tmp_name']);
        elseif ($mime === IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($upload['tmp_name']);
        elseif ($mime === IMAGETYPE_WEBP) $src = @imagecreatefromwebp($upload['tmp_name']);
        
        if(!$src){
            tms_flash('error','Không thể đọc định dạng hình ảnh.');
            tms_redirect('/settings');
        }
        
        // 2. Cắt vuông
        $w=imagesx($src); $h=imagesy($src);
        $side=min($w,$h);
        $square=imagecreatetruecolor($side,$side);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopy($square,$src,0,0,(int)(($w-$side)/2),(int)(($h-$side)/2),$side,$side);
        imagedestroy($src); // Giải phóng ngay ảnh gốc

        $public=dirname(__DIR__,2).'/public/assets';
        $icons=$public.'/icons';
        @mkdir($icons, 0755, true);

        // 3. Render các loại icon
        // Standard icons
        foreach(['logo-tms-os.png'=>512,'icon-512.png'=>512,'icon-192.png'=>192,'logo-splash.png'=>192] as $dest=>$size){
            $canvas=imagecreatetruecolor($size,$size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas,$square,0,0,0,0,$size,$size,$side,$side);
            imagepng($canvas,$brandDir.'/'.$dest);
            if(is_dir($icons)) imagepng($canvas,$icons.'/'.$dest);
            // Đặc biệt: copy logo-tms-os.png ra ngoài assets/ để header.php dùng trực tiếp
            if($dest === 'logo-tms-os.png') imagepng($canvas, $public.'/logo-tms-os.png');
            imagedestroy($canvas);
        }

        // Maskable (có viền an toàn cho Android/iOS)
        $maskSize=512;
        $safe=(int)round($maskSize*0.66);
        $mask=imagecreatetruecolor($maskSize,$maskSize);
        imagealphablending($mask,false);
        imagesavealpha($mask,true);
        $transparent=imagecolorallocatealpha($mask,0,0,0,127);
        imagefill($mask,0,0,$transparent);
        imagecopyresampled($mask,$square,(int)(($maskSize-$safe)/2),(int)(($maskSize-$safe)/2),0,0,$safe,$safe,$side,$side);
        imagepng($mask,$brandDir.'/icon-maskable-512.png');
        if(is_dir($icons)) imagepng($mask,$icons.'/icon-maskable-512.png');
        imagedestroy($mask);

        // Solid (không trong suốt, cho PWA splash/icon)
        foreach(['icon-192-solid.png'=>192,'icon-512-solid.png'=>512] as $dest=>$size){
            $canvas=imagecreatetruecolor($size,$size);
            imagecopyresampled($canvas,$square,0,0,0,0,$size,$size,$side,$side);
            imagepng($canvas,$brandDir.'/'.$dest);
            if(is_dir($icons)) imagepng($canvas,$icons.'/'.$dest);
            imagedestroy($canvas);
        }

        // Clean up
        imagedestroy($square);
        
        // Cập nhật asset version để trình duyệt load lại ngay
        tms_clear_cache();
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
