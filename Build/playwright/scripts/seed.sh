#!/usr/bin/env bash
#
# Applies the E2E seed data of EXT:pforum to the DDEV project and flushes the
# TYPO3 caches. Run it from anywhere; the DDEV project root is detected from
# PFORUM_E2E_PROJECT_ROOT or defaults to the project that contains Source/Extension/pforum.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SEED_FILE="${SCRIPT_DIR}/../fixtures/seed.sql"
PROJECT_ROOT="${PFORUM_E2E_PROJECT_ROOT:-$(cd "${SCRIPT_DIR}/../../../../../.." && pwd)}"

if [[ ! -f "${PROJECT_ROOT}/.ddev/config.yaml" ]]; then
    echo "No DDEV project found at ${PROJECT_ROOT}. Set PFORUM_E2E_PROJECT_ROOT." >&2
    exit 1
fi

cd "${PROJECT_ROOT}"
echo "Applying ${SEED_FILE} to DDEV project in ${PROJECT_ROOT}"
ddev mysql < "${SEED_FILE}"
ddev typo3 cache:flush
echo "Seed applied."
