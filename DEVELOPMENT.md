# Development Environment

This repository is the WordPress plugin source.

## Local Plugin Work

Run tests from the plugin root:

```bash
./test.sh
```

For focused E2E runs against the local Docker WordPress instance:

```bash
JZSA_E2E_SKIP_SETUP=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 npx playwright test
```

## WordPress.org SVN Setup

Production plugin releases publish to WordPress.org SVN. The release script
expects a sparse SVN checkout at:

```text
release/wp-svn/janzeman-shared-albums-for-google-photos/
```

Set it up once per clone:

```bash
./setup-wporg-svn.sh
```

This creates or updates the sparse checkout with `trunk` and `tags`. If you keep
the SVN checkout elsewhere, pass the trunk path when releasing:

```bash
SVN_TRUNK_PATH=/path/to/janzeman-shared-albums-for-google-photos/trunk ./release.sh X.Y.Z --prod
```

If a production release fails midway and leaves many `A`, `M`, or `!` entries in
`release/wp-svn/...`, reset that release-only checkout and recreate it:

```bash
rm -rf release/wp-svn/janzeman-shared-albums-for-google-photos
./setup-wporg-svn.sh
```

## Release Commands

Test release, ZIP only:

```bash
./release.sh X.Y.Z
./release.sh X.Y.Z --test
```

Production release:

```bash
./release.sh X.Y.Z --prod
```
