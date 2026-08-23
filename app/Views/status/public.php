<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#a70e13">
  <title>Trạng thái hệ thống · TMS OS</title>
  <link rel="icon" type="image/png" href="/assets/favicon.png?v=<?=tms_asset_version()?>">
  <style>
    :root{color-scheme:dark;--bg:#12090a;--surface:#241112;--line:#563033;--text:#fff7ed;--muted:#d6c4bd;--red:#ed1d24;--yellow:#fef16d;--ok:#70e09b;--warn:#fcb12b;--bad:#ff7f7f}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at top right,#66151a,transparent 40%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:720px;margin:0 auto;padding:clamp(24px,5vw,56px)20px 40px}.brand{display:flex;align-items:center;gap:12px;color:var(--yellow);font-weight:800;letter-spacing:.03em}.brand img{width:38px;height:38px;object-fit:contain}.eyebrow{margin:46px 0 8px;color:var(--yellow);font-size:.8rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}h1{font-size:clamp(2rem,8vw,3.6rem);line-height:1;margin:0;letter-spacing:-.05em}.lead{max-width:570px;color:var(--muted);line-height:1.6;margin:18px 0 28px}.grid{display:grid;gap:14px}.card{background:linear-gradient(135deg,rgba(255,255,255,.06),rgba(255,255,255,.015));border:1px solid var(--line);border-radius:18px;padding:18px}.label{color:var(--muted);font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.tunnel{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-top:8px}.state{font-size:1.35rem;font-weight:900}.meta{color:var(--muted);font-size:.92rem}.hosts{display:grid;gap:10px;margin-top:14px}.host{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(0,0,0,.16)}.host a{color:var(--text);font-weight:800;text-decoration:none;word-break:break-word}.host a:hover{text-decoration:underline}.badge{white-space:nowrap;padding:6px 9px;border-radius:999px;font-size:.73rem;font-weight:900}.operational{color:#052a16;background:var(--ok)}.configured,.syncing{color:#4a2400;background:var(--warn)}.attention,.offline{color:#43090b;background:var(--bad)}.unknown{color:#32231c;background:#d6c4bd}.footer{color:var(--muted);font-size:.84rem;margin-top:22px;line-height:1.6}.refresh{background:none;border:1px solid var(--line);color:var(--text);padding:9px 12px;border-radius:10px;font:inherit;font-weight:800;cursor:pointer}.refresh:active{transform:scale(.97)}
  </style>
</head>
<body>
  <main class="wrap">
    <div class="brand"><img src="/assets/logo-landing.png" alt=""> <span>TMS OS</span></div>
    <p class="eyebrow">Trạng thái công khai</p>
    <h1>Hệ thống đang ở đâu?</h1>
    <p class="lead">Theo dõi Cloudflare Tunnel và các hostname công khai. Trang này chỉ hiển thị dữ liệu vận hành đã được lọc, không hiển thị thông tin quản trị.</p>
    <section class="grid" aria-live="polite">
      <article class="card"><div class="label">Cloudflare Tunnel</div><div class="tunnel"><div><div id="tunnel-state" class="state">Đang kiểm tra…</div><div id="tunnel-meta" class="meta">Vui lòng chờ trong giây lát</div></div><span id="tunnel-badge" class="badge unknown">ĐANG TẢI</span></div></article>
      <article class="card"><div class="label">Hostname công khai</div><div id="hosts" class="hosts"><div class="meta">Đang tải danh sách hostname…</div></div></article>
    </section>
    <p class="footer"><button class="refresh" id="refresh" type="button">Làm mới</button> <span id="updated">Dữ liệu làm mới tự động mỗi 60 giây.</span></p>
  </main>
  <script src="/assets/public-status.js?v=<?=tms_asset_version()?>" defer></script>
</body>
</html>
