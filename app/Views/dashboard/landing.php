<?php $ui=tms_ui_settings(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>TMS OS - Mini Android VPS</title>
    <link rel="stylesheet" href="/assets/app.css?v=<?=tms_asset_version()?>">
    <link rel="manifest" href="/tms-pwa-v21.json?v=<?=tms_asset_version()?>">
    <link rel="icon" type="image/png" href="/assets/favicon.png?v=<?=tms_asset_version()?>">
    <meta name="theme-color" content="#a70e13">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(45deg, #a70e13, #ed1d24, #a70e13);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: #fff;
        }
        .landing-container {
            width: 100%;
            max-width: 500px;
            padding: 40px 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }
        .brand-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            text-align: left;
            margin-bottom: 10px;
        }
        .brand-logo-large {
            width: 100px;
            height: 100px;
            object-fit: contain;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.2));
        }
        .brand-info {
            color: #fef16d; /* Màu vàng sáng Bright Yellow */
            font-weight: 600;
            font-size: 1.05rem;
            line-height: 1.4;
        }
        .brand-info p {
            margin: 6px 0;
        }
        .version-tag {
            display: inline-block;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 4px;
        }
        .intro-box {
            background: linear-gradient(45deg, #fcb12b, #fef16d, #fdbc69);
            color: #a70e13; /* Màu đỏ đậm để nổi bật trên nền vàng */
            padding: 28px;
            border-radius: 22px;
            text-align: left;
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .btn-landing {
            background: linear-gradient(45deg, #fcb12b, #fef16d, #fdbc69);
            color: #a70e13; /* Màu đỏ đậm */
            padding: 22px;
            border-radius: 22px;
            font-size: 1.5rem;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
            text-align: center;
        }
        .btn-landing:active {
            transform: scale(0.97);
        }
        .btn-install {
            background: linear-gradient(45deg, #fcb12b, #fef16d, #fdbc69);
            color: #a70e13; /* Màu đỏ đậm */
            padding: 20px;
            border-radius: 22px;
            font-size: 1.25rem;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: block;
            text-align: center;
            line-height: 1.3;
        }
        @media (min-width: 768px) {
            .landing-container {
                max-width: 580px;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="brand-section">
            <img src="/assets/logo-landing.png" alt="TMS OS" class="brand-logo-large">
            <div class="brand-info">
                <p>• Bạn đang sử dụng TMS OS Tận dụng thiết bị Android cũ thành một mini VPS để chạy Webserver.</p>
                <p>• Phiên bản hiện tại<br><span class="version-tag">V <?=tms_h(preg_replace('/^Platform /','',$build))?></span></p>
            </div>
        </div>

        <div class="intro-box">
            Giới thiệu: TMS OS là một dự án được xây dựng hoàn toàn bằng AI (Vibe code) nên không tránh khỏi các sai sót, THCGaming chỉ đưa vào các tính năng phù hợp với nhu cầu cá nhân. Nếu các bạn có nhiều ý tưởng hơn, muốn thêm nhiều tính năng hữu ích hơn có thể góp ý cho mình để tiếp tục cập nhật thêm vào các phiên bản sắp tới, rất cảm ơn sự quan tâm và góp ý của các bạn.
        </div>

        <a href="/dashboard" class="btn-landing">Truy cập Panel</a>

        <a href="#" class="btn-install" onclick="tmsInstallPWA(event)">Thêm TMS OS<br>lên màn hình chính</a>
    </div>

    <script>
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            // Hiện nút cài đặt khi trình duyệt sẵn sàng
            const btn = document.querySelector('.btn-install');
            if (btn) btn.style.display = 'block';
        });

        function tmsInstallPWA(e) {
            e.preventDefault();
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        const btn = document.querySelector('.btn-install');
                        if (btn) btn.style.display = 'none';
                    }
                    deferredPrompt = null;
                });
            } else {
                // Kiểm tra xem có đang ở chế độ standalone không (đã cài đặt)
                const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
                if (isStandalone) {
                    alert('TMS OS đã được cài đặt trên màn hình chính.');
                    return;
                }
                
                // Check if iOS
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (isIOS) {
                    alert('Để cài đặt trên iOS: Chạm vào nút Chia sẻ trong Safari và chọn "Thêm vào Màn hình chính".');
                } else {
                    alert('Trình duyệt của bạn chưa sẵn sàng hoặc không hỗ trợ cài đặt tự động. Vui lòng sử dụng menu trình duyệt (ba chấm) để chọn "Cài đặt ứng dụng" hoặc "Thêm vào màn hình chính".');
                }
            }
        }
        
        // Ẩn nút nếu đã cài đặt
        window.addEventListener('DOMContentLoaded', () => {
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
            if (isStandalone) {
                const btn = document.querySelector('.btn-install');
                if (btn) btn.style.display = 'none';
            }
        });

        // Đăng ký Service Worker tại Landing Page
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js?v=<?=tms_asset_version()?>')
                    .then(reg => console.log('SW Registered'))
                    .catch(err => console.log('SW Error:', err));
            });
        }
    </script>
</body>
</html>
