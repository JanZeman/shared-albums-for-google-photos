#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

run_unit() {
    echo ""
    echo "=== Unit tests (PHPUnit) ==="
    vendor/bin/phpunit
}

run_js() {
    echo ""
    echo "=== JS unit tests (Vitest) ==="
    npx vitest run
}

require_docker_stack() {
    if [ "${JZSA_E2E_SKIP_SETUP:-}" = "1" ] || [ "${JZSA_E2E_SKIP_SETUP:-}" = "true" ]; then
        return 0
    fi
    if ! docker info >/dev/null 2>&1; then
        echo "ERROR: Docker is not running. E2E tests need the WordPress stack." >&2
        echo "Start Docker Desktop, then run docker-start.sh at the backend repo root." >&2
        exit 1
    fi
    if ! docker compose ps --status running --services 2>/dev/null | grep -qx wordpress; then
        echo "ERROR: The 'wordpress' service is not running. E2E tests need the WordPress stack." >&2
        echo "Run docker-start.sh at the backend repo root, wait until it is up, then retry." >&2
        exit 1
    fi
}

run_e2e() {
    require_docker_stack
    echo ""
    echo "=== E2E tests (Playwright) ==="
    npx playwright test
}

case "${1:-}" in
    "")
        run_unit
        run_js
        run_e2e
        ;;
    --unit)
        run_unit
        ;;
    --js)
        run_js
        ;;
    --e2e)
        run_e2e
        ;;
    *)
        echo "Usage: $0 [--unit | --js | --e2e]" >&2
        exit 1
        ;;
esac
