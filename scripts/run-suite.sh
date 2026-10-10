#!/usr/bin/env bash
# Run ps_categorytree PHPUnit in digest-pinned PHP containers.
# Does not modify composer.json/composer.lock in the repo.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ARTIFACT_DIR="/opt/cursor/artifacts"
ARTIFACT_FILE="${ARTIFACT_DIR}/suite-results.md"
PHPUNIT_VERSION="8.5.41"
PHPUNIT_KEY_ID="D8406D0D82947747293778314AA394086372C20A"
PHPUNIT_PHAR_SHA256="df3a284a28ef7eee387707b6cfd6e569f27698c557281c440addaee165c64c16"
COMPOSER_VERSION="2.2.25"
SMARTY_DIR="/tmp/ps-categorytree-smarty-vendor"
TOOL_CACHE="/tmp/ps-categorytree-tool-cache"
PHPUNIT_PHAR="${TOOL_CACHE}/phpunit-${PHPUNIT_VERSION}.phar"
PHPUNIT_ASC="${TOOL_CACHE}/phpunit-${PHPUNIT_VERSION}.phar.asc"
COMPOSER_PHAR="${TOOL_CACHE}/composer.phar"

PHP_72_IMAGE="php@sha256:5249d94b4b00db63a0ed53e6528114926110ab5dcb0d0f07cc30d1ea7dbe747e"
PHP_74_IMAGE="php@sha256:691f9ae2a3639de11d95f507bc29c723a7f27b79bdf91317aef8ded35f9864ce"
mkdir -p "${ARTIFACT_DIR}" "${TOOL_CACHE}" "${SMARTY_DIR}"

log() { printf '%s\n' "$*"; }

ensure_docker() {
  if docker info >/dev/null 2>&1; then
    DOCKER="${DOCKER:-docker}"
  elif sudo docker info >/dev/null 2>&1; then
    DOCKER="${DOCKER:-sudo docker}"
  else
    if command -v sudo >/dev/null 2>&1; then
      sudo dockerd --storage-driver=vfs >/tmp/dockerd.log 2>&1 &
      sleep 5
    fi
    DOCKER="${DOCKER:-sudo docker}"
  fi
}

container_phpunit() {
  local image="$1"
  local extra_args="$2"
  local log_label="$3"
  ${DOCKER} run --rm \
    -v "${REPO_ROOT}:/app" \
    -v "${TOOL_CACHE}:/tool-cache" \
    -v "${SMARTY_DIR}:/smarty-vendor" \
    -w /app \
    "${image}" \
    bash -lc "
      set -euo pipefail
      sed -i 's|deb.debian.org/debian|archive.debian.org/debian|g' /etc/apt/sources.list 2>/dev/null || true
      sed -i 's|security.debian.org/debian-security|archive.debian.org/debian-security|g' /etc/apt/sources.list 2>/dev/null || true
      echo 'Acquire::Check-Valid-Until \"false\";' > /etc/apt/apt.conf.d/99no-check-valid
      apt-get update -qq && apt-get install -y -qq gnupg unzip curl >/dev/null
      export PHPUNIT_PHAR=/tool-cache/phpunit-${PHPUNIT_VERSION}.phar
      export PHPUNIT_ASC=/tool-cache/phpunit-${PHPUNIT_VERSION}.phar.asc
      export COMPOSER_PHAR=/tool-cache/composer.phar
      if [[ ! -f \${PHPUNIT_PHAR} ]]; then
        curl -fsSL https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar -o \${PHPUNIT_PHAR}
        curl -fsSL https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar.asc -o \${PHPUNIT_ASC}
      fi
      actual_sha=\$(sha256sum \${PHPUNIT_PHAR} | awk '{print \$1}')
      test '${PHPUNIT_PHAR_SHA256}' = \"\${actual_sha}\"
      gpg --batch --keyserver keys.openpgp.org --recv-keys ${PHPUNIT_KEY_ID} 2>/dev/null || \
        gpg --batch --keyserver hkps://keyserver.ubuntu.com --recv-keys ${PHPUNIT_KEY_ID}
      gpg --batch --verify \${PHPUNIT_ASC} \${PHPUNIT_PHAR}
      if [[ ! -f /smarty-vendor/vendor/autoload.php ]]; then
        work=/tool-cache/composer-smarty-install
        mkdir -p \${work}
        php -r \"copy('https://getcomposer.org/installer', '\${work}/composer-setup.php');\"
        php -r \"copy('https://composer.github.io/installer.sig', '\${work}/composer-setup.sig');\"
        php -r \"if (hash_file('sha384', '\${work}/composer-setup.php') !== trim(file_get_contents('\${work}/composer-setup.sig'))) { fwrite(STDERR, 'bad composer installer'); exit(1); }\"
        php \${work}/composer-setup.php --version=${COMPOSER_VERSION} --install-dir=/tool-cache --filename=composer.phar
        rm -f \${work}/composer-setup.php \${work}/composer-setup.sig
        echo '{\"require\":{\"smarty/smarty\":\"3.1.47\"},\"config\":{\"preferred-install\":\"dist\"}}' > /smarty-vendor/composer.json
        php \${COMPOSER_PHAR} install --working-dir=/smarty-vendor --no-interaction --prefer-dist --no-progress
      fi
      export PS_CATEGORYTREE_SMARTY_AUTOLOAD=/smarty-vendor/vendor/autoload.php
      php \${PHPUNIT_PHAR} --configuration phpunit.xml.dist ${extra_args}
    " 2>&1 | tee "/tmp/ps-categorytree-${log_label}.log"
}

parse_summary() {
  grep -E '^OK \(|^FAILURES!|^ERRORS!' "$1" | tail -1 || echo "(no summary)"
}

parse_ok_line_counts() {
  local log="$1"
  local line
  line="$(grep -E '^OK \(' "$log" | tail -1 || true)"
  if [[ -z "${line}" ]]; then
    echo "0|0"
    return
  fi
  local tests assertions
  tests=$(echo "${line}" | grep -oP '^OK \(\K[0-9]+' || echo 0)
  assertions=$(echo "${line}" | grep -oP 'tests, \K[0-9]+' || echo 0)
  echo "${tests}|${assertions}"
}

parse_failure_counts() {
  local log="$1"
  local failures errors
  failures=$(grep -oP 'Failures: \K[0-9]+' "$log" | tail -1 || echo 0)
  errors=$(grep -oP 'Errors: \K[0-9]+' "$log" | tail -1 || echo 0)
  echo "${failures}|${errors}"
}

assert_revert_log() {
  local log="$1"
  local expected_failures="$2"
  local label="$3"
  if ! grep -q '^FAILURES!' "${log}"; then
    log "Revert proof ${label}: expected FAILURES! summary in ${log}"
    exit 1
  fi
  local counts failure_count error_count
  counts="$(parse_failure_counts "${log}")"
  IFS='|' read -r failure_count error_count <<< "${counts}"
  if [[ "${failure_count}" != "${expected_failures}" ]]; then
    log "Revert proof ${label}: expected ${expected_failures} failures, got ${failure_count}"
    exit 1
  fi
  if [[ "${error_count}" != "0" ]]; then
    log "Revert proof ${label}: expected 0 errors, got ${error_count}"
    exit 1
  fi
}

restore_module() {
  if [[ -n "${MODULE_BACKUP:-}" && -f "${MODULE_BACKUP}" ]]; then
    cp "${MODULE_BACKUP}" "${REPO_ROOT}/ps_categorytree.php"
  fi
}

prove_fix_reverts() {
  local module="${REPO_ROOT}/ps_categorytree.php"
  MODULE_BACKUP="${TOOL_CACHE}/ps_categorytree.php.bak"
  cp "${module}" "${MODULE_BACKUP}"
  trap restore_module EXIT

  cp "${MODULE_BACKUP}" "${module}"
  sed -i 's/\$parentCategory->checkAccess/\$category->checkAccess/g' "${module}"
  sed -i 's/\$parentCategory->existsInShop/\$category->existsInShop/g' "${module}"
  set +e
  container_phpunit "${PHP_74_IMAGE}" \
    "--filter 'testParentModeSkipsParentThatDeniesAccess|testParentModeSkipsParentMissingFromShop|testParentModeKeepsParentWhenOnlyChildDeniesAccess'" \
    "revert-parent"
  PROOF_PARENT_EXIT=$?
  set -e
  assert_revert_log "/tmp/ps-categorytree-revert-parent.log" 3 "parent-checkAccess-existsInShop"
  cp "${MODULE_BACKUP}" "${module}"

  sed -i 's/getSubCategories(\$this->context->language->id, true)/getSubCategories(\$rootCategory->id, true)/' "${module}"
  set +e
  container_phpunit "${PHP_74_IMAGE}" \
    "--filter testGetSubCategoriesReceivesLanguageIdAndActiveTrue" \
    "revert-subcategories"
  PROOF_SUB_EXIT=$?
  set -e
  assert_revert_log "/tmp/ps-categorytree-revert-subcategories.log" 1 "getSubCategories-language-id"
  cp "${MODULE_BACKUP}" "${module}"

  sed -i 's/ || \$category->id == Configuration::get('\''PS_HOME_CATEGORY'\'')//' "${module}"
  set +e
  container_phpunit "${PHP_74_IMAGE}" \
    "--filter testParentModeStaysWhenCategoryIsHome" \
    "revert-home-check"
  PROOF_HOME_EXIT=$?
  set -e
  assert_revert_log "/tmp/ps-categorytree-revert-home-check.log" 1 "PS_HOME_CATEGORY guard"
  cp "${MODULE_BACKUP}" "${module}"

  sed -i '/Configuration::updateValue('\''BLOCK_CATEG_SORT_WAY'\''/d' "${module}"
  set +e
  container_phpunit "${PHP_74_IMAGE}" \
    "--filter testPositiveDepthSortAndRootAreSavedBeforeRedirect" \
    "revert-config-sort-way"
  PROOF_SORT_WAY_EXIT=$?
  set -e
  assert_revert_log "/tmp/ps-categorytree-revert-config-sort-way.log" 1 "BLOCK_CATEG_SORT_WAY save"
  cp "${MODULE_BACKUP}" "${module}"

  sed -i '/Configuration::updateValue('\''BLOCK_CATEG_SORT'\''/d' "${module}"
  set +e
  container_phpunit "${PHP_74_IMAGE}" \
    "--filter testPositiveDepthSortAndRootAreSavedBeforeRedirect" \
    "revert-config-sort"
  PROOF_SORT_EXIT=$?
  set -e
  assert_revert_log "/tmp/ps-categorytree-revert-config-sort.log" 1 "BLOCK_CATEG_SORT save"
  cp "${MODULE_BACKUP}" "${module}"

  sed -i '/Configuration::updateValue('\''BLOCK_CATEG_ROOT_CATEGORY'\''/d' "${module}"
  set +e
  container_phpunit "${PHP_74_IMAGE}" \
    "--filter testPositiveDepthSortAndRootAreSavedBeforeRedirect" \
    "revert-config-root"
  PROOF_ROOT_EXIT=$?
  set -e
  assert_revert_log "/tmp/ps-categorytree-revert-config-root.log" 1 "BLOCK_CATEG_ROOT_CATEGORY save"
  cp "${MODULE_BACKUP}" "${module}"

  trap - EXIT
  restore_module
  rm -f "${MODULE_BACKUP}"
  unset MODULE_BACKUP
}

main() {
  ensure_docker
  log "Using ${DOCKER}"
  local branch sha
  branch="$(git -C "${REPO_ROOT}" rev-parse --abbrev-ref HEAD)"
  sha="$(git -C "${REPO_ROOT}" rev-parse HEAD)"

  log "Gate PHP 7.2 default x2"
  container_phpunit "${PHP_72_IMAGE}" "" "72-default-1"
  container_phpunit "${PHP_72_IMAGE}" "" "72-default-2"
  log "Gate PHP 7.2 random seed 20261008 x2"
  container_phpunit "${PHP_72_IMAGE}" "--order-by=random --random-order-seed=20261008" "72-random-1"
  container_phpunit "${PHP_72_IMAGE}" "--order-by=random --random-order-seed=20261008" "72-random-2"

  log "Gate PHP 7.4 default x2"
  container_phpunit "${PHP_74_IMAGE}" "" "74-default-1"
  container_phpunit "${PHP_74_IMAGE}" "" "74-default-2"
  log "Gate PHP 7.4 random seed 20261008 x2"
  container_phpunit "${PHP_74_IMAGE}" "--order-by=random --random-order-seed=20261008" "74-random-1"
  container_phpunit "${PHP_74_IMAGE}" "--order-by=random --random-order-seed=20261008" "74-random-2"

  prove_fix_reverts

  local summary ok_counts failure_counts
  summary="$(parse_summary /tmp/ps-categorytree-74-default-1.log)"
  ok_counts="$(parse_ok_line_counts /tmp/ps-categorytree-74-default-1.log)"
  IFS='|' read -r test_count assertion_count <<< "${ok_counts}"
  failure_counts="$(parse_failure_counts /tmp/ps-categorytree-74-default-1.log)"
  IFS='|' read -r failure_count error_count <<< "${failure_counts}"

  cat > "${ARTIFACT_FILE}" <<EOF
# ps_categorytree suite results

- **repo:** Training-Datasmith/ps_categorytree
- **date:** $(date -u +%Y-%m-%dT%H:%M:%SZ)
- **branch:** ${branch}
- **commit:** ${sha}
- **php versions:** 7.2 and 7.4 (digest-pinned images only)
- **phpunit:** ${PHPUNIT_VERSION} PHAR (SHA-256 ${PHPUNIT_PHAR_SHA256}, GPG key ${PHPUNIT_KEY_ID})
- **smarty:** 3.1.47 via Composer ${COMPOSER_VERSION} in ${SMARTY_DIR}

## Gate (8 runs, all passed)

| Run | Image | Order |
| --- | --- | --- |
| 1-2 | php:7.2@5249d94b… | default |
| 3-4 | php:7.2@5249d94b… | random seed 20261008 |
| 5-6 | php:7.4@691f9ae2… | default |
| 7-8 | php:7.4@691f9ae2… | random seed 20261008 |

**PHP 7.4 default run 1:** ${summary}

- Tests: ${test_count}
- Assertions: ${assertion_count}
- Failures: ${failure_count}
- Errors: ${error_count}

## Production-fix revert proof (FAILURES! with expected counts, 0 errors)

| Revert | Failures | Exit |
| --- | --- | --- |
| Parent checkAccess/existsInShop on \$category | 3 | ${PROOF_PARENT_EXIT} |
| getSubCategories(\$rootCategory->id) | 1 | ${PROOF_SUB_EXIT} |
| PS_HOME_CATEGORY guard (home test) | 1 | ${PROOF_HOME_EXIT} |
| BLOCK_CATEG_SORT_WAY save | 1 | ${PROOF_SORT_WAY_EXIT} |
| BLOCK_CATEG_SORT save | 1 | ${PROOF_SORT_EXIT} |
| BLOCK_CATEG_ROOT_CATEGORY save | 1 | ${PROOF_ROOT_EXIT} |

Revert logs: /tmp/ps-categorytree-revert-*.log

## Command

\`\`\`bash
./scripts/run-suite.sh
\`\`\`
EOF

  log "Wrote ${ARTIFACT_FILE}"
}

main "$@"
