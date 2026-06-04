#!/bin/bash

#
# Set up the sparse WordPress.org SVN checkout used by release.sh --prod.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="janzeman-shared-albums-for-google-photos"
SVN_ROOT="${SCRIPT_DIR}/release/wp-svn/${PLUGIN_SLUG}"

svn_depth() {
    svn info "$1" | awk -F': ' '/^Depth:/ { print $2; found=1 } END { if (!found) print "infinity" }'
}

assert_depth() {
    local path="$1"
    local expected="$2"
    local actual

    actual="$(svn_depth "$path")"
    if [ "$actual" != "$expected" ]; then
        echo "Error: unexpected SVN depth for ${path}"
        echo "  expected: ${expected}"
        echo "  actual:   ${actual:-<missing>}"
        exit 1
    fi
}

assert_clean() {
    local status_output

    status_output="$(svn status "$SVN_ROOT" 2>/dev/null || true)"
    if [ -n "$status_output" ]; then
        echo "Error: SVN working copy is not clean:"
        echo ""
        echo "$status_output"
        echo ""
        echo "This helper will not repair a dirty release checkout in place."
        echo "Reset it first:"
        echo "  rm -rf \"${SVN_ROOT}\""
        echo "  ./setup-wporg-svn.sh"
        exit 1
    fi
}

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

echo "Checking SVN working copy is clean before changing depths..."
assert_clean

echo "Setting trunk checkout depth to infinity..."
svn update "${SVN_ROOT}/trunk" --set-depth infinity

echo "Setting assets checkout depth to files..."
svn update "${SVN_ROOT}/assets" --set-depth files

echo "Setting tags checkout depth to immediates..."
svn update "${SVN_ROOT}/tags" --set-depth immediates

echo "Verifying SVN checkout depths..."
assert_depth "${SVN_ROOT}/trunk" "infinity"
assert_depth "${SVN_ROOT}/assets" "files"
assert_depth "${SVN_ROOT}/tags" "immediates"

echo "Checking SVN working copy is clean after setup..."
assert_clean

echo ""
echo "WordPress.org SVN checkout is ready:"
echo "  ${SVN_ROOT}"
echo ""
echo "Verified depths:"
echo "  trunk:  $(svn_depth "${SVN_ROOT}/trunk")"
echo "  assets: $(svn_depth "${SVN_ROOT}/assets")"
echo "  tags:   $(svn_depth "${SVN_ROOT}/tags")"
