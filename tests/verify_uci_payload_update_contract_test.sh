#!/usr/bin/env bash
# Regression: GitHub Release payload is an in-panel Update Center package.
# It intentionally contains scripts/install.sh, not a second root install.sh.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf -- "$WORK"' EXIT
PAYLOAD_ROOT="$WORK/payload"
ZIP="$WORK/TMS_OS_LATEST.zip"
METADATA="$WORK/RELEASE.json"
REPORT="$WORK/report.log"

mkdir -p "$PAYLOAD_ROOT/scripts/lib" "$PAYLOAD_ROOT/config" "$PAYLOAD_ROOT/public"
cat > "$PAYLOAD_ROOT/scripts/install.sh" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT
cat > "$PAYLOAD_ROOT/scripts/lib/installer-compatibility.sh" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT
cat > "$PAYLOAD_ROOT/scripts/lib/installer-safety.sh" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT
cat > "$PAYLOAD_ROOT/scripts/installer-rollback.sh" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT
cat > "$PAYLOAD_ROOT/scripts/tms-php-engine.sh" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT
printf '%s\n' "<?php return ['build' => 'Platform test'];" > "$PAYLOAD_ROOT/config/app.php"
printf '%s\n' '<?php echo "TMS OS";' > "$PAYLOAD_ROOT/public/index.php"

(
  cd "$PAYLOAD_ROOT"
  zip -qr "$ZIP" scripts config public
)
test ! -e "$PAYLOAD_ROOT/install.sh"
SHA256="$(sha256sum "$ZIP" | awk '{print $1}')"
printf '{"checksum_sha256":"%s"}\n' "$SHA256" > "$METADATA"

bash "$ROOT/scripts/verify-uci-payload.sh" \
  --payload "$ZIP" \
  --release-json "$METADATA" \
  --no-matrix \
  --report "$REPORT"

grep -Fqx 'RESULT=PASS' "$REPORT"
echo 'PASS: verifier accepts Update Center payload without root install.sh.'
