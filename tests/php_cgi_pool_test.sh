#!/usr/bin/env bash
# V17.1.7 — Test hồi quy sự cố 08/11/2026: máy không có php-fpm rơi về
# php-cgi trần; php-cgi mặc định tự thoát sau 500 request
# (PHP_FCGI_MAX_REQUESTS) và chỉ chạy 1 tiến trình nối tiếp, khiến panel
# + mọi website PHP sập sau vài phút sử dụng cho tới khi cạn hạn mức tự
# sửa của watchdog/Guardian. Test này chạy THẬT tms-php-engine.sh với
# php-cgi thật: yêu cầu engine khởi động dạng pool tự quản (có children)
# và vẫn trả lời sau request thứ 600 (vượt mốc tự thoát cũ 500).
# Môi trường không có php-cgi/python3 -> SKIP (exit 0).
set -u
cd "$(dirname "$0")/.."
ROOT="$PWD"

if ! command -v php-cgi >/dev/null 2>&1 || ! command -v python3 >/dev/null 2>&1; then
  echo "SKIP: môi trường thiếu php-cgi hoặc python3."
  exit 0
fi

FAKEHOME="$(mktemp -d)"
FAKEPREFIX="$(mktemp -d)"
SITE="$FAKEHOME/site"
mkdir -p "$SITE" "$FAKEPREFIX/var/run"
printf '<?php echo "OK";' > "$SITE/index.php"

export HOME="$FAKEHOME" PREFIX="$FAKEPREFIX"

cleanup(){
  bash "$ROOT/scripts/tms-php-engine.sh" stop >/dev/null 2>&1 || true
  pkill -9 -f 'php-cgi (-n )?-b 127.0.0.1:9000' 2>/dev/null || true
  rm -rf "$FAKEHOME" "$FAKEPREFIX"
}
trap cleanup EXIT

fail(){ echo "FAIL: $1" >&2; exit 1; }

# Client FastCGI tối thiểu (python3) — bắn N request tới 127.0.0.1:9000.
FCGI_CLIENT="$FAKEHOME/fcgi_client.py"
cat > "$FCGI_CLIENT" <<'PYEOF'
import socket, struct, sys

HOST, PORT = '127.0.0.1', 9000
BEGIN, PARAMS, STDIN, STDOUT, END = 1, 4, 5, 6, 3

def rec(rtype, content, rid=1):
    return struct.pack('!BBHHBB', 1, rtype, rid, len(content), 0, 0) + content

def length(n):
    return bytes([n]) if n < 128 else struct.pack('!I', n | 0x80000000)

def nv(name, value):
    return length(len(name)) + length(len(value)) + name.encode() + value.encode()

def request(script):
    s = socket.create_connection((HOST, PORT), timeout=10)
    s.sendall(rec(BEGIN, struct.pack('!HB5x', 1, 0)))
    params = b''.join(nv(k, v) for k, v in [
        ('REQUEST_METHOD', 'GET'), ('SCRIPT_FILENAME', script),
        ('SCRIPT_NAME', '/index.php'), ('REQUEST_URI', '/'),
        ('SERVER_PROTOCOL', 'HTTP/1.1'), ('GATEWAY_INTERFACE', 'CGI/1.1'),
        ('SERVER_SOFTWARE', 'tms-test'), ('REMOTE_ADDR', '127.0.0.1'),
        ('SERVER_NAME', 'localhost'), ('SERVER_PORT', '9000'),
        ('CONTENT_TYPE', ''), ('CONTENT_LENGTH', '0'),
    ])
    s.sendall(rec(PARAMS, params))
    s.sendall(rec(PARAMS, b''))
    s.sendall(rec(STDIN, b''))
    out = b''
    while True:
        hdr = b''
        while len(hdr) < 8:
            chunk = s.recv(8 - len(hdr))
            if not chunk:
                break
            hdr += chunk
        if len(hdr) < 8:
            break
        _, rtype, _, clen, plen, _ = struct.unpack('!BBHHBB', hdr)
        data = b''
        while len(data) < clen + plen:
            chunk = s.recv(clen + plen - len(data))
            if not chunk:
                break
            data += chunk
        if rtype == STDOUT:
            out += data[:clen]
        if rtype == END:
            break
    s.close()
    return out

script, total = sys.argv[1], int(sys.argv[2])
for i in range(total):
    out = request(script)
    if b'OK' not in out:
        print(f'FAIL tai request {i + 1}: {out[:120]!r}')
        sys.exit(1)
print(f'OK {total} requests')
PYEOF

bash "$ROOT/scripts/tms-php-engine.sh" start || fail "tms-php-engine.sh start lỗi"
sleep 1

PIDFILE="$FAKEHOME/.tms-os/php-engine.pid"
[ -f "$PIDFILE" ] || fail "không có php-engine.pid"
MANAGER="$(cat "$PIDFILE")"
kill -0 "$MANAGER" 2>/dev/null || fail "tiến trình engine (pid $MANAGER) không sống"

CHILDREN="$(pgrep -P "$MANAGER" 2>/dev/null | wc -l | tr -d ' ')"
[ "${CHILDREN:-0}" -ge 2 ] || fail "php-cgi không chạy dạng pool (children=$CHILDREN) — thiếu PHP_FCGI_CHILDREN"

# 650 request: vượt xa mốc 500 mà php-cgi trần ngày xưa tự thoát.
python3 "$FCGI_CLIENT" "$SITE/index.php" 650 || fail "php-cgi chết trước 650 request (tái diễn lỗi PHP_FCGI_MAX_REQUESTS)"

kill -0 "$MANAGER" 2>/dev/null || fail "manager php-cgi đã chết sau 650 request"

bash "$ROOT/scripts/tms-php-engine.sh" stop >/dev/null 2>&1 || true
echo "PASS: php-cgi pool sống qua 650 request, children=$CHILDREN."
