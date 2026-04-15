#!/usr/bin/env bash
# Build a WordPress-installable zip of jsdw-ai-chat (folder jsdw-ai-chat/ at zip root).
# Excludes dev-only docs and previews from the plugin tree.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PLUGIN_DIR="jsdw-ai-chat"
MAIN="${PLUGIN_DIR}/jsdw-ai-chat.php"
if [[ ! -f "$MAIN" ]]; then
	echo "Missing ${MAIN}" >&2
	exit 1
fi
VERSION="$(grep -m1 'Version:' "$MAIN" | sed -E 's/.*Version:[[:space:]]+([0-9.]+).*/\1/')"
mkdir -p dist
OUT="dist/jsdw-ai-chat-${VERSION}.zip"
rm -f "$OUT"
zip -r "$OUT" "$PLUGIN_DIR" \
	-x "${PLUGIN_DIR}/*.md" \
	-x "${PLUGIN_DIR}/PHASE*" \
	-x "${PLUGIN_DIR}/PREVIEW*" \
	-x "${PLUGIN_DIR}/ADMIN_UX*" \
	-x "${PLUGIN_DIR}/SOURCES_ADMIN_AUDIT.txt" \
	-x "*.DS_Store" \
	-x "**/.DS_Store"
echo "Wrote ${OUT}"
