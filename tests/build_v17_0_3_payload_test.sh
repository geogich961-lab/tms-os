#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT

BUILD_ROOT="$TMP/build" bash "$ROOT/tests/build_v17_0_3_payload.sh" >/dev/null
ZIP="$TMP/build/out/TMS_OS_LATEST.zip"
MANIFEST="$TMP/build/out/RELEASE.json"
EXTRACT="$TMP/extract"

unzip -tq "$ZIP" >/dev/null
[ "$(sha256sum "$ZIP" | awk '{print $1}')" = "$(sed -n 's/.*"checksum_sha256"[[:space:]]*:[[:space:]]*"\([a-f0-9]\{64\}\)".*/\1/p' "$MANIFEST")" ] || { echo 'V17.0.3 build regression: checksum mismatch.' >&2; exit 1; }
grep -Fq '"version": "17.0.3"' "$MANIFEST" || { echo 'V17.0.3 build regression: version metadata is wrong.' >&2; exit 1; }
unzip -q "$ZIP" -d "$EXTRACT"

grep -Fq 'Platform V17.0.3' "$EXTRACT/config/app.php" || { echo 'V17.0.3 build regression: app version was not overlaid.' >&2; exit 1; }
grep -Fq 'tms-os-v17.0.3' "$EXTRACT/public/service-worker.js" || { echo 'V17.0.3 build regression: PWA cache version missing.' >&2; exit 1; }
grep -Fq 'public function enqueueInstall(string $id): array' "$EXTRACT/app/Services/PluginService.php" || { echo 'V17.0.3 build regression: package queue service missing.' >&2; exit 1; }
grep -Fq 'public function status(): void' "$EXTRACT/app/Controllers/PluginController.php" || { echo 'V17.0.3 build regression: package status controller missing.' >&2; exit 1; }
grep -Fq 'data-package-install-form' "$EXTRACT/app/Views/plugins/index.php" || { echo 'V17.0.3 build regression: package polling UI missing.' >&2; exit 1; }
grep -Fq '(new PluginService())->runQueuedInstalls();' "$EXTRACT/scripts/tms-package-worker.php" || { echo 'V17.0.3 build regression: package worker missing.' >&2; exit 1; }
grep -Fq "'/api/packages/status'" "$EXTRACT/routes/web.php" || { echo 'V17.0.3 build regression: package polling route missing.' >&2; exit 1; }
grep -Fq "'queued' => !empty(\$r['queued'])" "$EXTRACT/app/Controllers/UpdateController.php" || { echo 'V17.0.3 build regression: queued update response missing.' >&2; exit 1; }
grep -Fq "'Cập nhật chưa đổi được source sang V'" "$EXTRACT/app/Services/UpdateService.php" || { echo 'V17.0.3 build regression: source version verification missing.' >&2; exit 1; }
[ ! -e "$EXTRACT/RELEASE.json" ] || { echo 'V17.0.3 build regression: stale RELEASE.json inside payload.' >&2; exit 1; }

echo 'V17.0.3 payload build regression test: OK'
