# Release Process

## Prerequisites

- Clean git working tree on `main` branch
- `svn` CLI installed (for WordPress.org publishing)
- SVN working copy checked out at `release/wp-svn/janzeman-shared-albums-for-google-photos/` (one-time setup, see below)

## Steps

### 1. Bump the version

Update the version string in **all three** files:

| File | What to update |
|------|----------------|
| `janzeman-shared-albums-for-google-photos.php` | `Version:` in the plugin header AND `JZSA_VERSION` constant |
| `readme.txt` | `Stable tag:` line |
| `README.md` | Version badge (`version-X.Y.Z-blue`) |

### 2. Write the changelog

Add a changelog section in `readme.txt` under `== Changelog ==`:

```
= X.Y.Z =
* First change
* Second change
```

The release script validates that at least one bullet point exists.

### 3. Commit and run the release script

```bash
git add -A && git commit -m "X.Y.Z"
./release.sh X.Y.Z
```

To **only build the ZIP** (no git tag, no SVN sync - useful for local testing):

```bash
./release.sh --zip-only X.Y.Z
```

The full release script will:

1. **Validate** that the requested version matches all versioned files
2. **Check git state** - must be on `main` with a clean working tree
3. **Create and push a git tag** (e.g., `1.0.7`) to origin
4. **Build a ZIP** at `release/janzeman-shared-albums-for-google-photos-X.Y.Z.zip`
5. **Sync to SVN trunk** (if the SVN working copy exists)
6. **Commit to SVN** and create an SVN tag under `tags/X.Y.Z`
7. **Clean up** temporary build files

## One-time SVN setup

```bash
mkdir -p release/wp-svn
cd release/wp-svn
svn checkout https://plugins.svn.wordpress.org/janzeman-shared-albums-for-google-photos/ \
    janzeman-shared-albums-for-google-photos --depth=immediates
cd janzeman-shared-albums-for-google-photos
svn update trunk
svn update tags --depth=immediates
```

This creates a sparse checkout with only `trunk` and `tags` (skipping `assets` and `branches` to save space).

## Troubleshooting

- **"Version in main plugin file is X but you requested Y"** - you forgot to bump one of the three files in step 1.
- **"git working tree is not clean"** - commit or stash changes first.
- **"should be run from main/master"** - switch to the main branch.
- **SVN commit fails** - check your SVN credentials. WordPress.org SVN uses your wp.org username and password.
- **SVN trunk not found** - run the one-time SVN setup above.
