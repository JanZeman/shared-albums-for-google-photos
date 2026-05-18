#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

run_unit() {
    echo ""
    echo "=== Unit tests (PHPUnit) ==="
    vendor/bin/phpunit
}

run_e2e() {
    echo ""
    echo "=== E2E tests (Playwright) ==="
    npx playwright test
}

case "${1:-}" in
    "")
        run_unit
        run_e2e
        ;;
    --unit)
        run_unit
        ;;
    --e2e)
        run_e2e
        ;;
    *)
        echo "Usage: $0 [--unit | --e2e]" >&2
        exit 1
        ;;
esac
