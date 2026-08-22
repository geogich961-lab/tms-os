#!/usr/bin/env bash
# TMS OS V15.0.6 — Bật tối ưu hiệu năng cho máy đã cài
# Áp dụng: gzip toàn cục, tcp_nopush/nodelay, open_file_cache, cache tĩnh 1 năm, OPcache PHP.
# An toàn: backup cấu hình cũ trước khi ghi. Có thể chạy lại nhiều lần (idempotent).
set -euo pipefail
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
HOME="${HOME:-$PREFIX/../home}"
NGINX="$PREFIX/etc/nginx/nginx.conf"
PHP_DIR="$PREFIX/etc/php/conf.d"
SITES="$PREFIX/etc/nginx/sites-enabled"
BACKUP="$HOME/.tms-os/backups/optimize"
STATE_FILE="$HOME/.tms-os/nginx-optimized"

mkdir -p "$BACKUP" "$HOME/logs/nginx"

# 1. Backup cấu hình hiện tại
ts=$(date +%s)
[ -f "$NGINX" ] && cp "$NGINX" "$BACKUP/nginx.conf.$ts"
find "$SITES" -maxdepth 1 -name '*.conf' -exec cp {} "$BACKUP/site.{}.$ts" \; 2>/dev/null || true

# 2. Viết lại nginx.conf global với gzip + cache (giữ include sites)
python3 - "$NGINX" <<'PY'
import sys, re
conf_path = sys.argv[1]
try:
    old = open(conf_path).read()
except Exception:
    old = ''

opt_block = """  # V15.0.6: tối ưu hiệu năng — nén gzip + keepalive + cache file
  gzip on;
  gzip_vary on;
  gzip_proxied any;
  gzip_comp_level 4;
  gzip_min_length 256;
  gzip_buffers 16 8k;
  gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;

  tcp_nopush on;
  tcp_nodelay on;
  keepalive_timeout 65;
	client_max_body_size 500M;
	server_tokens off;

	# Chỉ tin CF-Connecting-IP từ cloudflared chạy trên loopback.
	# Request LAN trực tiếp không thể giả IP bằng header này.
	set_real_ip_from 127.0.0.1;
	set_real_ip_from ::1;
	real_ip_header CF-Connecting-IP;
	real_ip_recursive off;

	open_file_cache max=1000 inactive=60s;
  open_file_cache_valid 60s;
  open_file_cache_min_uses 2;
  open_file_cache_errors on;
"""
# Loại các directive đã tồn tại trong file (tránh lỗi 'directive is duplicate')
for directive in ['tcp_nopush', 'tcp_nodelay', 'keepalive_timeout', 'client_max_body_size', 'server_tokens', 'open_file_cache', 'gzip ', 'set_real_ip_from', 'real_ip_header', 'real_ip_recursive']:
    if re.search(r'(?m)\b' + directive, old, re.M):
        opt_block = re.sub(r'^\s*' + re.escape(directive).replace(r'\ ', r' ') + r'.*$', '', opt_block, flags=re.M)

# Nếu đã có http block tối ưu rồi thì giữ nguyên
if re.search(r'gzip on;\s*\n\s*gzip_vary', old):
    print('ALREADY')
    sys.exit(0)

# Chiến lược an toàn: không parse block — chỉ chèn block tối ưu ngay sau 'http {'
# (không trùng lặp với cấu hình cũ vì đã kiểm tra ALREADY ở trên)
idx = old.find('http {')
if idx < 0:
    # Không có http block — prepend vào đầu file
    new = 'worker_processes auto;\nevents { worker_connections 1024; }\n' + opt_block + old
else:
    insert_at = idx + len('http {')
    new = old[:insert_at] + '\n' + opt_block + old[insert_at:]
open(conf_path, 'w').write(new)
print('REWRITTEN')
PY

# 3. Thêm cache tĩnh vào mọi server block trong sites-enabled (chưa có thì thêm)
for f in "$SITES"/*.conf; do
  [ -f "$f" ] || continue
  if ! grep -q 'expires 1y' "$f" 2>/dev/null; then
    python3 - "$f" <<'PY'
import sys
p = sys.argv[1]
cache_block = """
    # V15.0.6: cache browser 1 năm cho file tĩnh
    location ~* \.(jpg|jpeg|png|gif|webp|ico|svg|css|js|woff2?|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
"""
text = open(p).read()
if 'expires 1y' not in text:
    NL = '\n'
    marker = 'location ~ /\\. { deny all; } }'
    if marker in text:
        text = text.replace(marker, cache_block + NL + marker, 1)
    else:
        # chèn trước } cuối cùng (cuối server block)
        idx = text.rfind('}')
        text = text[:idx] + cache_block + NL + text[idx:]
    open(p, 'w').write(text)
    print('SITE ' + p + ' updated')
else:
    print('SITE ' + p + ' already')
PY
  fi
done

# 4. Bật OPcache cho PHP
cat > "$PHP_DIR/99-tms-os.ini" <<INI
memory_limit=256M
upload_max_filesize=512M
post_max_size=520M
max_execution_time=300
max_input_time=300
display_errors=Off
log_errors=On
session.save_path="$HOME/tms-os/storage/sessions"
; V15.0.6: OPcache tăng tốc biên dịch PHP
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=64
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.save_comments=0
INI

# 5. Test rồi reload nginx + restart PHP
nginx -t >>"$HOME/logs/nginx/optimize.log" 2>&1
nginx -s reload >>"$HOME/logs/nginx/optimize.log" 2>&1 || nginx
bash "$HOME/tms-os/scripts/tms-php-engine.sh" php restart >>"$HOME/logs/nginx/optimize.log" 2>&1 || true
echo "$ts" > "$STATE_FILE"
echo "OK: Đã bật tối ưu hiệu năng (gzip + cache tĩnh + OPcache). Nginx & PHP đã khởi động lại."
