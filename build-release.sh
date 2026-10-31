#!/usr/bin/env bash
# Build the distributable ZIP from this public plugin source tree.
# Release orchestration, version changes, tests, SVN, and publishing live
# in the private venture repository.

set -euo pipefail

PLUGIN_SLUG="janzeman-shared-albums-for-google-photos"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -ne 2 ]]; then
    printf 'Usage: %s VERSION OUTPUT_DIRECTORY\n' "$(basename "$0")" >&2
    exit 2
fi

VERSION="$1"
OUTPUT_DIR="$2"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
    printf 'Invalid plugin version: %s\n' "$VERSION" >&2
    exit 2
fi

MAIN_FILE="${SCRIPT_DIR}/${PLUGIN_SLUG}.php"
README_TXT="${SCRIPT_DIR}/readme.txt"
README_MD="${SCRIPT_DIR}/README.md"

if ! grep -q "^ \* Version: ${VERSION}$" "$MAIN_FILE" || \
   ! grep -q "define( 'JZSA_VERSION', '${VERSION}' );" "$MAIN_FILE" || \
   ! grep -q "^Stable tag: ${VERSION}$" "$README_TXT" || \
   ! grep -q "version-${VERSION}-blue" "$README_MD" || \
   ! grep -q "^= ${VERSION} =$" "$README_TXT"; then
    echo "Version references or changelog do not all match ${VERSION}." >&2
    exit 1
fi

if [[ ! -d "$OUTPUT_DIR" ]]; then
    printf 'Output directory must already exist: %s\n' "$OUTPUT_DIR" >&2
    exit 1
fi
OUTPUT_DIR="$(cd "$OUTPUT_DIR" && pwd)"
PACKAGE_DIR="${OUTPUT_DIR}/${PLUGIN_SLUG}"
# The ZIP file name is free; WordPress installs by the folder inside, which must stay the slug.
ZIP_PATH="${OUTPUT_DIR}/shared-albums-${VERSION}.zip"

if [[ -e "$PACKAGE_DIR" || -e "$ZIP_PATH" ]]; then
    printf 'Refusing to overwrite an existing build artifact in %s.\n' "$OUTPUT_DIR" >&2
    exit 1
fi

mkdir "$PACKAGE_DIR"
cp "$MAIN_FILE" "$PACKAGE_DIR/"
cp "$README_TXT" "$PACKAGE_DIR/"
cp "${SCRIPT_DIR}/LICENSE" "$PACKAGE_DIR/"
cp -R "${SCRIPT_DIR}/includes" "$PACKAGE_DIR/"
cp -R "${SCRIPT_DIR}/assets" "$PACKAGE_DIR/"
if [[ -d "${SCRIPT_DIR}/languages" ]]; then
    cp -R "${SCRIPT_DIR}/languages" "$PACKAGE_DIR/"
fi

find "$PACKAGE_DIR" -type f \( -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.bak' \
    -o -name '*.tmp' -o -name '*~' -o -name '._*' \) -delete

required_files=(
    "${PLUGIN_SLUG}.php"
    "readme.txt"
    "LICENSE"
    "languages/index.php"
    "includes/class-data-provider.php"
    "includes/class-orchestrator.php"
    "includes/class-renderer.php"
    "includes/class-admin-pages.php"
    "includes/admin/reference-parameters.php"
    "includes/admin/reference-placeholders.php"
    "assets/css/admin-menu-icon.css"
    "assets/css/admin-settings.css"
    "assets/css/swiper-style.css"
    "assets/icon-admin.svg"
    "assets/js/admin-settings.js"
    "assets/js/swiper-init.js"
    "assets/vendor/swiper/swiper-bundle.min.css"
    "assets/vendor/swiper/swiper-bundle.min.js"
    "assets/icon-256x256.gif"
)
for relative_path in "${required_files[@]}"; do
    if [[ ! -f "${PACKAGE_DIR}/${relative_path}" ]]; then
        printf 'Required plugin file is missing: %s\n' "$relative_path" >&2
        exit 1
    fi
done

if find "$PACKAGE_DIR" -type l -print -quit | grep -q .; then
    echo 'Refusing to package symlinks.' >&2
    exit 1
fi

if find "$PACKAGE_DIR" -type f \( -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.bak' \
    -o -name '*.tmp' -o -name '*~' -o -name '._*' \) -print -quit | grep -q .; then
    echo 'Refusing to package unwanted files.' >&2
    exit 1
fi

for entry in "$PACKAGE_DIR"/*; do
    case "$(basename "$entry")" in
        "${PLUGIN_SLUG}.php"|readme.txt|LICENSE|includes|assets|languages) ;;
        *) printf 'Unexpected top-level package file: %s\n' "$(basename "$entry")" >&2; exit 1 ;;
    esac
done

(cd "$OUTPUT_DIR" && zip -r -q "$(basename "$ZIP_PATH")" "$PLUGIN_SLUG")
printf 'Built %s\n' "$ZIP_PATH"
