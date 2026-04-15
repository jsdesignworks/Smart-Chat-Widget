#!/usr/bin/env bash
# Build a WordPress-uploadable zip: jsdw-ai-chat/jsdw-ai-chat.php at archive root.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/jsdw-ai-chat"
DIST="${ROOT}/dist"
VERSION="$(grep -m1 "Version:" "${SRC}/jsdw-ai-chat.php" | sed 's/.*Version:[[:space:]]*//')"

if [[ ! -f "${SRC}/jsdw-ai-chat.php" ]]; then
  echo "Missing ${SRC}/jsdw-ai-chat.php" >&2
  exit 1
fi

mkdir -p "${DIST}"
OUT="${DIST}/jsdw-ai-chat-${VERSION}.zip"
rm -f "${OUT}"

TMP="$(mktemp -d)"
cleanup() { rm -rf "${TMP}"; }
trap cleanup EXIT

DEST="${TMP}/jsdw-ai-chat"
mkdir -p "${DEST}"

rsync -a \
  --exclude='.DS_Store' \
  --exclude='*.md' \
  --exclude='PREVIEW*.html' \
  --exclude='SOURCES_ADMIN_AUDIT.txt' \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='*.zip' \
  "${SRC}/" "${DEST}/"

(
  cd "${TMP}"
  zip -rq "${OUT}" jsdw-ai-chat
)

echo "Wrote ${OUT}"
unzip -l "${OUT}" | sed -n '1,30p'
