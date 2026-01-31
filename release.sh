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

# ---------------------------------------------------------------------------
# Parse required version argument
# ---------------------------------------------------------------------------
REQUESTED_VERSION="$1"
if [ -z "$REQUESTED_VERSION" ]; then
    echo -e "${RED}Usage:${NC} $(basename "$0") <version>"
    echo "Example: $(basename "$0") 1.0.2"
    exit 1
fi

# Basic sanity check for version format (X.Y or X.Y.Z); do not be too strict
if ! echo "$REQUESTED_VERSION" | grep -Eq '^[0-9]+\.[0-9]+(\.[0-9]+)?$'; then
    echo -e "${YELLOW}Warning:${NC} Requested version '$REQUESTED_VERSION' does not look like a typical semantic version. Continuing anyway..."
fi

# Extract version from main plugin file
VERSION=$(grep -E "^\s*\*\s*Version:" "${SCRIPT_DIR}/janzeman-shared-albums-for-google-photos.php" | awk '{print $3}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from janzeman-shared-albums-for-google-photos.php${NC}"
    exit 1
fi

if [ "$VERSION" != "$REQUESTED_VERSION" ]; then
    echo -e "${RED}Error:${NC} Version in main plugin file is '${VERSION}', but you requested '${REQUESTED_VERSION}'."
    echo "Please bump the plugin header version first."
    exit 1
fi

# ---------------------------------------------------------------------------
# Validate that all versioned files reference the requested version
# ---------------------------------------------------------------------------
VERSION_ERRORS=0

# 1) Main plugin constant
if ! grep -q "define( 'JZSA_VERSION', '${REQUESTED_VERSION}'" "${SCRIPT_DIR}/janzeman-shared-albums-for-google-photos.php"; then
    echo -e "${RED}Error:${NC} JZSA_VERSION constant does not match ${REQUESTED_VERSION} in janzeman-shared-albums-for-google-photos.php"
    VERSION_ERRORS=1
fi

# 2) readme.txt Stable tag
if ! grep -q "Stable tag: ${REQUESTED_VERSION}" "${SCRIPT_DIR}/readme.txt"; then
    echo -e "${RED}Error:${NC} readme.txt Stable tag is not ${REQUESTED_VERSION}"
    VERSION_ERRORS=1
fi

# 3) README.md version badge
if ! grep -q "version-${REQUESTED_VERSION}-blue" "${SCRIPT_DIR}/README.md"; then
    echo -e "${RED}Error:${NC} README.md version badge does not reference ${REQUESTED_VERSION}"
    VERSION_ERRORS=1
fi

# 4) readme.txt changelog section for this version
if ! grep -q "= ${REQUESTED_VERSION} =" "${SCRIPT_DIR}/readme.txt"; then
    echo -e "${RED}Error:${NC} readme.txt does not contain a changelog section header '= ${REQUESTED_VERSION} ='"
    VERSION_ERRORS=1
else
    # Ensure there is at least one bullet point under this version before next header
    if ! awk -v v="${REQUESTED_VERSION}" '
        $0 ~ "^= " v " =" { in_section=1; next }
        in_section && $0 ~ "^=" { exit 1 }
        in_section && $0 ~ "^\*" { found=1 }
        END { exit found ? 0 : 1 }
    ' "${SCRIPT_DIR}/readme.txt"; then
        echo -e "${RED}Error:${NC} Changelog section for ${REQUESTED_VERSION} in readme.txt has no bullet items."
        VERSION_ERRORS=1
    fi
fi

if [ "$VERSION_ERRORS" -ne 0 ]; then
    echo -e "${RED}Version validation failed.${NC} Please update the files above before releasing."
    exit 1
fi

# ---------------------------------------------------------------------------
# Read-only git checks (optional but strongly recommended)
# - Ensure we are on main/master
# - Ensure working tree is clean
# - Ensure a git tag matching this version points at HEAD (vX.Y.Z or X.Y.Z)
# ---------------------------------------------------------------------------
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo -e "${YELLOW}Checking git state...${NC}"

    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
    if [ "$CURRENT_BRANCH" != "main" ] && [ "$CURRENT_BRANCH" != "master" ]; then
        echo -e "${RED}Error: release.sh should be run from main/master (current: ${CURRENT_BRANCH}).${NC}"
        exit 1
    fi

    # Fail if working tree is dirty
    if ! git diff-index --quiet HEAD --; then
        echo -e "${RED}Error: git working tree is not clean. Commit or stash your changes first.${NC}"
        git status -sb || true
        exit 1
    fi

    TAG_RAW="${VERSION}"

    tag_points_at_head() {
        local tag="$1"
        git rev-parse "$tag" >/dev/null 2>&1 || return 1
        git tag --points-at HEAD | grep -qx "$tag"
    }

    if tag_points_at_head "$TAG_RAW"; then
        echo -e "${GREEN}✓ Git tag for version ${VERSION} already points at HEAD.${NC}"
    else
        # Tag does not point at HEAD; check if it exists at all
        if git rev-parse "$TAG_RAW" >/dev/null 2>&1; then
            echo -e "${RED}Error:${NC} git tag '${TAG_RAW}' already exists but does not point at HEAD."
            echo "Please move HEAD to that tag or delete/adjust the tag manually, then re-run release.sh."
            exit 1
        fi

        echo -e "${YELLOW}Creating git tag ${TAG_RAW} at HEAD...${NC}"
        if ! git tag -a "${TAG_RAW}" -m "${TAG_RAW}"; then
            echo -e "${RED}Error:${NC} Failed to create git tag '${TAG_RAW}'."
            exit 1
        fi

        echo -e "${YELLOW}Pushing branch ${CURRENT_BRANCH} to origin...${NC}"
        if ! git push origin "${CURRENT_BRANCH}"; then
            echo -e "${RED}Error:${NC} Failed to push branch '${CURRENT_BRANCH}' to origin."
            echo "Please fix the git remote issue and push manually."
            exit 1
        fi

        echo -e "${YELLOW}Pushing tag ${TAG_RAW} to origin...${NC}"
        if ! git push origin "${TAG_RAW}"; then
            echo -e "${RED}Error:${NC} Failed to push git tag '${TAG_RAW}' to origin."
            echo "You may need to push the tag manually with: git push origin ${TAG_RAW}"
            exit 1
        fi

        echo -e "${GREEN}✓ Git tag ${TAG_RAW} created and pushed to origin.${NC}"
    fi
else
    echo -e "${YELLOW}Warning: not in a git repository; skipping git checks.${NC}"
fi

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}  Shared Albums for Google Photos (by JanZeman) Release Script  ${NC}"
echo -e "${BLUE}================================================================${NC}"
echo ""
echo -e "${GREEN}Plugin:${NC}   ${PLUGIN_SLUG}"
echo -e "${GREEN}Version:${NC}  ${VERSION} (requested: ${REQUESTED_VERSION})"
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
    echo "  - Git tag has been validated/created and pushed to origin."
    echo "  - SVN trunk has been synced from the release package."
    echo "  - If the automatic SVN commit/tagging step (below) fails, follow the printed error message and run the svn commands manually."
else
    echo "  - Review extracted files in: ${EXTRACT_DIR}"
    echo "  - Test by installing ${ZIP_NAME} on a WordPress site"
    echo "  - Validate with WordPress Plugin Check (if available)"
    echo "  - Manually copy files into your SVN trunk working copy and commit/tag there"
fi
echo ""

# If we synced to SVN, stage new files in SVN, show status, and optionally commit & tag
if [ "$SYNCED_TO_SVN" -eq 1 ] && command -v svn &> /dev/null; then
    echo -e "${YELLOW}Running 'svn add . --force' in trunk (no commit yet)...${NC}"
    (
        cd "$SVN_TRUNK" && \
        svn add . --force >/dev/null 2>&1 || true
    )

    echo -e "${YELLOW}SVN status for trunk before commit:${NC}"
    SVN_STATUS_OUTPUT=$(cd "$SVN_TRUNK" && svn status || true)
    echo "$SVN_STATUS_OUTPUT"

    if [ -z "${SVN_STATUS_OUTPUT}" ]; then
        echo -e "${YELLOW}No pending changes in SVN trunk; skipping automatic SVN commit and tag.${NC}"
    else
        echo -e "${YELLOW}Committing changes to SVN trunk...${NC}"
        if ! (cd "$SVN_TRUNK" && svn commit -m "Release ${VERSION}"); then
            echo -e "${RED}Error:${NC} Failed to commit changes to SVN trunk."
            echo "Please resolve the issue in ${SVN_TRUNK} and commit manually."
            exit 1
        fi

        SVN_ROOT="${SVN_TRUNK%/trunk}"
        if [ ! -d "$SVN_ROOT/tags" ]; then
            echo -e "${YELLOW}Warning:${NC} SVN tags directory not found under ${SVN_ROOT}; skipping SVN tag creation."
        else
            echo -e "${YELLOW}Creating SVN tag ${VERSION}...${NC}"
            if ! (cd "$SVN_ROOT" && svn copy trunk "tags/${VERSION}" -m "Tag version ${VERSION}"); then
                echo -e "${RED}Error:${NC} Failed to create SVN tag ${VERSION}."
                echo "You may need to create the tag manually, for example:"
                echo "  cd ${SVN_ROOT}"
                echo "  svn copy trunk tags/${VERSION} -m 'Tag version ${VERSION}'"
                exit 1
            fi
            echo -e "${GREEN}✓ SVN tag ${VERSION} created successfully.${NC}"
        fi
    fi
fi

# Clean up build directory and temporary extract
echo -e "${YELLOW}Cleaning up build directory...${NC}"
rm -rf "$BUILD_DIR" "$EXTRACT_DIR"

echo -e "${GREEN}Done!${NC}"
