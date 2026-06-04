# Release Process

## Prerequisites

- Clean git working tree on `main` branch
- `svn` CLI installed (for WordPress.org publishing)
- SVN working copy checked out at `release/wp-svn/janzeman-shared-albums-for-google-photos/` (one-time setup, see below). Production releases fail if this checkout is missing.

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

The default mode is a **test release**: it bumps/validates versions, builds the ZIP, and copies it to `~/Downloads`. It does not create a git tag or publish to WordPress.org SVN.

```bash
./release.sh X.Y.Z
./release.sh X.Y.Z --test
```

For a **production release**:

```bash
./release.sh X.Y.Z --prod
```

Every run copies the generated ZIP to `~/Downloads`, overwriting an existing file with the same name.

The production release script will:

1. **Validate** that the requested version matches all versioned files
2. **Check git state** - must be on `main` with a clean working tree, and the requested git tag must not already exist locally or on origin
3. **Build a ZIP** at `release/janzeman-shared-albums-for-google-photos-X.Y.Z.zip`
4. **Copy the ZIP** to `~/Downloads/janzeman-shared-albums-for-google-photos-X.Y.Z.zip`
5. **Sync to SVN trunk** (fails if the SVN working copy is missing)
6. **Commit to SVN** and create an SVN tag under `tags/X.Y.Z`
7. **Push the git branch, create the git tag, and push the git tag** only after successful SVN delivery
8. **Clean up** temporary build files

## One-time SVN setup

```bash
./setup-wporg-svn.sh
```

This creates a sparse checkout with only `trunk` and `tags` (skipping `assets` and `branches` to save space).

If you keep the SVN checkout elsewhere, set `SVN_TRUNK_PATH` when running the production release:

```bash
SVN_TRUNK_PATH=/path/to/janzeman-shared-albums-for-google-photos/trunk ./release.sh X.Y.Z --prod
```

## Troubleshooting

- **"Version in main plugin file is X but you requested Y"** - you forgot to bump one of the three files in step 1.
- **"git working tree is not clean"** - commit or stash changes first.
- **"should be run from main/master"** - switch to the main branch.
- **"git tag already exists"** - delete the failed local/remote tag before retrying if the previous production release did not complete.
- **SVN commit fails** - check your SVN credentials. WordPress.org SVN uses your wp.org username and password.
- **SVN trunk not found** - run the one-time SVN setup above in the current clone, or pass `SVN_TRUNK_PATH=/path/to/trunk`.
- **SVN working copy is not clean** - reset the release-only checkout with `rm -rf release/wp-svn/janzeman-shared-albums-for-google-photos && ./setup-wporg-svn.sh`.
