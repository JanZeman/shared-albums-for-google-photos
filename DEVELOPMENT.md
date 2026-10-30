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

Use `COMPOSE_FILE` and `COMPOSE_PROJECT_NAME` when the stack is outside this repository or
has a different project name. Set `JZSA_E2E_WP_SERVICE` if its WordPress service is not named
`wordpress`, and set `PLAYWRIGHT_BASE_URL` to the corresponding local URL. Set `WP_ROOT` to
the in-container WordPress root (for example `/var/www/html`) when a symlink places the
plugin outside it. Fixture setup resets test passwords and data, preserves existing profile
details, enables the legacy migration UI, and suppresses WordPress notification emails.
Use a disposable test installation to preserve working-site accounts.

## Building the plugin ZIP

The public ZIP builder packages the plugin source without release orchestration:

```bash
./build-release.sh VERSION OUTPUT_DIRECTORY
```

The version must already match the plugin header, `JZSA_VERSION`, the readme stable tag,
and a changelog section. Release preparation, tests, WordPress.org SVN operations, and
publishing are managed by the private venture repository.
