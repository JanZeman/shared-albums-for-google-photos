#!/bin/bash

#
# Shared Albums for Google Photos (by JanZeman) - Release Script
# Creates a clean WordPress plugin release package
#

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="janzeman-shared-albums-for-google-photos"
RELEASE_DIR_ROOT="${SCRIPT_DIR}/release"
BUILD_DIR="${RELEASE_DIR_ROOT}/build"
RELEASE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
EXTRACT_DIR="${RELEASE_DIR_ROOT}/${PLUGIN_SLUG}"

# Extract version from main plugin file
VERSION=$(grep -E "^\s*\*\s*Version:" "${SCRIPT_DIR}/janzeman-shared-albums-for-google-photos.php" | awk '{print $3}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from janzeman-shared-albums-for-google-photos.php${NC}"
    exit 1
fi

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}  Shared Albums for Google Photos (by JanZeman) Release Script  ${NC}"
echo -e "${BLUE}================================================================${NC}"
echo ""
echo -e "${GREEN}Plugin:${NC} ${PLUGIN_SLUG}"
echo -e "${GREEN}Version:${NC} ${VERSION}"
echo ""

# Clean previous build artifacts (but keep any SVN working copies under release/)
echo -e "${YELLOW}Cleaning previous release build...${NC}"
rm -rf "$BUILD_DIR" "$EXTRACT_DIR"

# Create build directory structure
echo -e "${YELLOW}Creating build directory...${NC}"
mkdir -p "$RELEASE_DIR"

# Copy plugin files
echo -e "${YELLOW}Copying plugin files...${NC}"

# Main plugin file
cp "${SCRIPT_DIR}/janzeman-shared-albums-for-google-photos.php" "$RELEASE_DIR/"

# WordPress readme
cp "${SCRIPT_DIR}/readme.txt" "$RELEASE_DIR/"

# License file (GPL)
cp "${SCRIPT_DIR}/LICENSE" "$RELEASE_DIR/"

# Copy includes directory
echo -e "  → includes/"
cp -r "${SCRIPT_DIR}/includes" "$RELEASE_DIR/"

# Copy assets directory
echo -e "  → assets/"
cp -r "${SCRIPT_DIR}/assets" "$RELEASE_DIR/"

# Copy languages directory (may be empty, needed for translations)
if [ -d "${SCRIPT_DIR}/languages" ]; then
  echo -e "  → languages/"
  cp -r "${SCRIPT_DIR}/languages" "$RELEASE_DIR/"
fi

# Clean up any unwanted files from copied directories
echo -e "${YELLOW}Cleaning up unwanted files...${NC}"
find "$RELEASE_DIR" -type f -name ".DS_Store" -delete
find "$RELEASE_DIR" -type f -name "Thumbs.db" -delete
find "$RELEASE_DIR" -type f -name "*.bak" -delete
find "$RELEASE_DIR" -type f -name "*.tmp" -delete
find "$RELEASE_DIR" -type f -name "*~" -delete
find "$RELEASE_DIR" -type f -name "._*" -delete

# Validate required files exist
echo -e "${YELLOW}Validating package...${NC}"
REQUIRED_FILES=(
    "janzeman-shared-albums-for-google-photos.php"
    "readme.txt"
    "LICENSE"
    "languages/index.php"
    "includes/class-data-provider.php"
    "includes/class-orchestrator.php"
    "includes/class-renderer.php"
    "includes/class-settings-page.php"
    "assets/css/admin-settings.css"
    "assets/css/swiper-style.css"
    "assets/js/admin-settings.js"
    "assets/js/swiper-init.js"
    "assets/vendor/swiper/swiper-bundle.min.css"
    "assets/vendor/swiper/swiper-bundle.min.js"
)

VALIDATION_FAILED=0
for FILE in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "${RELEASE_DIR}/${FILE}" ]; then
        echo -e "${RED}  ✗ Missing: ${FILE}${NC}"
        VALIDATION_FAILED=1
    else
        echo -e "${GREEN}  ✓ ${FILE}${NC}"
    fi
done

if [ $VALIDATION_FAILED -eq 1 ]; then
    echo -e "${RED}Validation failed! Some required files are missing.${NC}"
    exit 1
fi

# Create ZIP archive
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${RELEASE_DIR_ROOT}/${ZIP_NAME}"

echo -e "${YELLOW}Creating release archive...${NC}"
cd "$BUILD_DIR"
zip -r -q "$ZIP_PATH" "$PLUGIN_SLUG"
cd "$SCRIPT_DIR"

# Get file size
if [ -f "$ZIP_PATH" ]; then
    FILE_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
    echo -e "${GREEN}✓ Archive created: ${ZIP_NAME} (${FILE_SIZE})${NC}"
else
    echo -e "${RED}Error: Failed to create archive${NC}"
    exit 1
fi

# Generate checksums
echo -e "${YELLOW}Generating checksums...${NC}"
if command -v md5 &> /dev/null; then
    MD5_HASH=$(md5 -q "$ZIP_PATH")
    echo -e "${GREEN}MD5:${NC}    ${MD5_HASH}"
elif command -v md5sum &> /dev/null; then
    MD5_HASH=$(md5sum "$ZIP_PATH" | awk '{print $1}')
    echo -e "${GREEN}MD5:${NC}    ${MD5_HASH}"
fi

if command -v shasum &> /dev/null; then
    SHA256_HASH=$(shasum -a 256 "$ZIP_PATH" | awk '{print $1}')
    echo -e "${GREEN}SHA256:${NC} ${SHA256_HASH}"
fi

# Unzip to temporary release directory and sync into SVN trunk (if present)
echo -e "${YELLOW}Extracting to temporary release directory...${NC}"
unzip -q "$ZIP_PATH" -d "$RELEASE_DIR_ROOT"
echo -e "${GREEN}✓ Extracted to: ${EXTRACT_DIR}${NC}"

# Determine SVN trunk path (can be overridden by SVN_TRUNK_PATH env var)
SVN_TRUNK_DEFAULT="${SCRIPT_DIR}/release/wp-svn/${PLUGIN_SLUG}/trunk"
SVN_TRUNK="${SVN_TRUNK_PATH:-$SVN_TRUNK_DEFAULT}"
SYNCED_TO_SVN=0

if [ -d "$SVN_TRUNK" ]; then
    echo -e "${YELLOW}Syncing files into SVN trunk: ${SVN_TRUNK}${NC}"
    # Remove existing plugin files from trunk, but keep .svn metadata
    rm -rf "${SVN_TRUNK}"/*
    cp -R "${EXTRACT_DIR}/"* "$SVN_TRUNK/"
    SYNCED_TO_SVN=1
else
    echo -e "${YELLOW}SVN trunk not found at ${SVN_TRUNK}. Skipping SVN sync.${NC}"
fi

# Summary
echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ Release package created successfully!${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "${GREEN}Package:${NC}     ${ZIP_PATH}"
echo -e "${GREEN}Extracted:${NC}   ${EXTRACT_DIR}"
echo -e "${GREEN}Size:${NC}        ${FILE_SIZE}"
if [ "$SYNCED_TO_SVN" -eq 1 ]; then
    echo -e "${GREEN}SVN trunk:${NC}   ${SVN_TRUNK}"
fi
echo ""
echo -e "${YELLOW}Next steps:${NC}"
if [ "$SYNCED_TO_SVN" -eq 1 ]; then
    echo "  1. Review files in SVN trunk: ${SVN_TRUNK}"
    echo "  2. From that directory, run:"
    echo "       svn status"
    echo "       # If there are new/unversioned files:"
    echo "       svn add . --force"
    echo "       svn status"
    echo "       svn commit -m \"Release ${VERSION}\""
    echo "  3. Tag the release (from the plugin SVN root):"
    echo "       svn copy trunk tags/${VERSION}"
    echo "       svn commit -m \"Tag version ${VERSION}\""
else
    echo "  1. Review extracted files in: ${EXTRACT_DIR}"
    echo "  2. Test by installing ${ZIP_NAME} on a WordPress site"
    echo "  3. Validate with WordPress Plugin Check (if available)"
    echo "  4. Manually copy files into your SVN trunk working copy"
fi
echo ""

# If we synced to SVN, optionally stage new files in SVN and show status
if [ "$SYNCED_TO_SVN" -eq 1 ] && command -v svn &> /dev/null; then
    echo -e "${YELLOW}Running 'svn add . --force' in trunk (no commit)...${NC}"
    (
        cd "$SVN_TRUNK" && \
        svn add . --force >/dev/null 2>&1 || true
    )

    echo -e "${YELLOW}SVN status for trunk:${NC}"
    (
        cd "$SVN_TRUNK" && \
        svn status
    ) || true
fi

# Clean up build directory and temporary extract
echo -e "${YELLOW}Cleaning up build directory...${NC}"
rm -rf "$BUILD_DIR" "$EXTRACT_DIR"

echo -e "${GREEN}Done!${NC}"
