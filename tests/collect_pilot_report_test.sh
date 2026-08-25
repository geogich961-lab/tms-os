#!/usr/bin/env bash
# Smoke test collector pilot: archive chỉ chứa log sanitized, không chứa raw.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
COLLECTOR="$ROOT/scripts/collect-pilot-report.sh"
TEST_ROOT="$(mktemp -d)"
trap 'rm -rf -- "$TEST_ROOT"' EXIT

mkdir -p "$TEST_ROOT/home" "$TEST_ROOT/prefix"
bash -n "$COLLECTOR"
HOME="$TEST_ROOT/home" PREFIX="$TEST_ROOT/prefix" \
  bash "$COLLECTOR" --output "$TEST_ROOT/reports" --skip-preflight > "$TEST_ROOT/run.log" 2>&1

archive="$(sed -n 's/^archive=//p' "$TEST_ROOT/run.log" | sed -n '1p')"
test -n "$archive"
test -f "$archive"
! tar -tzf "$archive" | grep -q '/raw/'
tar -tzf "$archive" | grep -q '/sanitized/'
tar -xOzf "$archive" -- "$(tar -tzf "$archive" | grep '/summary.txt$' | sed -n '1p')" | grep -q '^collector_version=1$'

echo 'collect_pilot_report_test=PASS'
