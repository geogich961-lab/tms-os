<?php $ui=tms_ui_settings(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>TMS OS - Mini Android VPS</title>
    <link rel="stylesheet" href="/assets/app.css?v=<?=tms_asset_version()?>">
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
            padding: 30px 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .brand-section {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 20px;
            text-align: left;
        }
        .brand-logo-large {
            width: 120px;
            height: 120px;
            background: #fff;
            border-radius: 24px;
            padding: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .brand-info h1 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .brand-info p {
            margin: 8px 0 0;
            font-size: 1.05rem;
            line-height: 1.4;
            opacity: 0.95;
        }
        .version-tag {
            display: inline-block;
            margin-top: 10px;
            font-weight: 700;
            font-size: 1rem;
        }
        .intro-box {
            background: linear-gradient(45deg, #fcb12b, #fef16d, #fdbc69);
            color: #8b5e00;
            padding: 24px;
            border-radius: 20px;
            text-align: left;
            font-size: 0.98rem;
            line-height: 1.6;
            font-weight: 500;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .btn-landing {
            background: linear-gradient(45deg, #fcb12b, #fef16d, #fdbc69);
            color: #8b5e00;
            padding: 20px;
            border-radius: 20px;
            font-size: 1.4rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
        }
        .btn-landing:active {
            transform: scale(0.97);
        }
        .btn-install {
            background: linear-gradient(45deg, #fcb12b, #fef16d, #fdbc69);
            color: #8b5e00;
            padding: 20px;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            display: block;
        }
        @media (min-width: 768px) {
            .landing-container {
                max-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="brand-section">
            <img src="<?=tms_h(tms_brand_icon('logo'))?>" alt="TMS OS" class="brand-logo-large">
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
        });

        function tmsInstallPWA(e) {
            e.preventDefault();
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    }
                    deferredPrompt = null;
                });
            } else {
                // Check if iOS
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (isIOS) {
                    alert('Để cài đặt trên iOS: Chạm vào nút Chia sẻ trong Safari và chọn "Thêm vào Màn hình chính".');
                } else {
                    alert('Ứng dụng đã được cài đặt hoặc trình duyệt của bạn không hỗ trợ cài đặt tự động. Vui lòng sử dụng menu trình duyệt để "Thêm vào màn hình chính".');
                }
            }
        }
    </script>
</body>
</html>
