#!/bin/bash

#
# Set up the WordPress.org SVN checkout used by release.sh --prod.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="janzeman-shared-albums-for-google-photos"
SVN_PARENT="${SCRIPT_DIR}/release/wp-svn"
SVN_ROOT="${SVN_PARENT}/${PLUGIN_SLUG}"

if ! command -v svn >/dev/null 2>&1; then
    echo "Error: svn CLI is not installed."
    echo "Install Subversion first, then re-run this script."
    exit 1
fi

mkdir -p "${SVN_PARENT}"

if [ ! -d "${SVN_ROOT}/.svn" ]; then
    echo "Checking out full WordPress.org SVN working copy..."
    svn checkout "https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/" "${SVN_ROOT}"
else
    echo "SVN working copy already exists: ${SVN_ROOT}"

    STATUS_OUTPUT="$(svn status "${SVN_ROOT}" 2>/dev/null || true)"
    if [ -n "${STATUS_OUTPUT}" ]; then
        echo "Error: SVN working copy is not clean:"
        echo ""
        echo "${STATUS_OUTPUT}"
        echo ""
        echo "This helper will not repair a dirty release checkout in place."
        echo "Reset it first:"
        echo "  rm -rf \"${SVN_ROOT}\""
        echo "  ./setup-wporg-svn.sh"
        exit 1
    fi

    echo "Updating WordPress.org SVN working copy..."
    svn update "${SVN_ROOT}"
fi

echo ""
echo "WordPress.org SVN checkout is ready:"
echo "  ${SVN_ROOT}"
echo ""
echo "Expected release paths:"
echo "  trunk:  ${SVN_ROOT}/trunk"
echo "  assets: ${SVN_ROOT}/assets"
echo "  tags:   ${SVN_ROOT}/tags"
