#!/data/data/com.termux/files/usr/bin/bash
set -e
URL="${1:-http://127.0.0.1:8888/api/monitoring}"
curl -fsS "$URL" >/dev/null || true
