#!/bin/bash

#
# Set up the sparse WordPress.org SVN checkout used by release.sh --prod.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="janzeman-shared-albums-for-google-photos"
SVN_ROOT="${SCRIPT_DIR}/release/wp-svn/${PLUGIN_SLUG}"

if ! command -v svn >/dev/null 2>&1; then
    echo "Error: svn CLI is not installed."
    echo "Install Subversion first, then re-run this script."
    exit 1
fi

mkdir -p "${SCRIPT_DIR}/release/wp-svn"

if [ ! -d "${SVN_ROOT}/.svn" ]; then
    echo "Checking out WordPress.org SVN working copy..."
    svn checkout "https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/" \
        "${SVN_ROOT}" --depth=immediates
else
    echo "SVN working copy already exists: ${SVN_ROOT}"
fi

echo "Updating trunk..."
svn update "${SVN_ROOT}/trunk" --depth=infinity

echo "Updating tags..."
svn update "${SVN_ROOT}/tags" --depth=immediates

echo "Updating assets..."
svn update "${SVN_ROOT}/assets" --depth=files

echo ""
echo "WordPress.org SVN checkout is ready:"
echo "  ${SVN_ROOT}"
