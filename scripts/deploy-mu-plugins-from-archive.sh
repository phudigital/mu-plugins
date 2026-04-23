cat > /root/pdl-deploy/deploy-mu-plugins-from-archive.sh <<'SH'
#!/usr/bin/env bash
set -euo pipefail

ARCHIVE_PATH="${ARCHIVE_PATH:-/root/Archive.tar.gz}"
SEARCH_ROOTS="${SEARCH_ROOTS:-/var/www}"
BACKUP_ROOT="${BACKUP_ROOT:-/root/pdl-mu-backups}"
TMP_ROOT="${TMP_ROOT:-/tmp/pdl-mu-deploy}"
DRY_RUN="${DRY_RUN:-0}"

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*"
}

fail() {
  log "ERROR: $*"
  exit 1
}

run_cmd() {
  if [[ "$DRY_RUN" == "1" ]]; then
    printf '[DRY-RUN] '
    printf '%q ' "$@"
    printf '\n'
    return 0
  fi
  "$@"
}

require_tools() {
  local missing=()
  for tool in tar find stat cp rm mkdir chown chmod sed; do
    command -v "$tool" >/dev/null 2>&1 || missing+=("$tool")
  done
  if (( ${#missing[@]} > 0 )); then
    fail "Missing required tools: ${missing[*]}"
  fi
}

discover_sites() {
  find /var/www -type f -name wp-config.php 2>/dev/null | sort -u
}

prepare_payload() {
  local payload_dir="$1"

  [[ -f "$ARCHIVE_PATH" ]] || fail "Archive not found: $ARCHIVE_PATH"
  rm -rf "$payload_dir"
  mkdir -p "$payload_dir"

  tar -xzf "$ARCHIVE_PATH" -C "$payload_dir"

  [[ -f "$payload_dir/pdl-loader.php" ]] || fail "Archive missing pdl-loader.php"
  [[ -d "$payload_dir/pdl-modules" ]] || fail "Archive missing pdl-modules/"
}

set_permissions() {
  local owner="$1"
  local mu_dir="$2"

  run_cmd chown "$owner" "$mu_dir"
  run_cmd chmod 755 "$mu_dir"

  if [[ -f "$mu_dir/pdl-loader.php" ]]; then
    run_cmd chown "$owner" "$mu_dir/pdl-loader.php"
    run_cmd chmod 644 "$mu_dir/pdl-loader.php"
  fi

  if [[ -d "$mu_dir/pdl-modules" ]]; then
    run_cmd chown -R "$owner" "$mu_dir/pdl-modules"
    run_cmd find "$mu_dir/pdl-modules" -type d -exec chmod 755 {} +
    run_cmd find "$mu_dir/pdl-modules" -type f -exec chmod 644 {} +
  fi
}

deploy_site() {
  local wp_config="$1"
  local payload_dir="$2"
  local timestamp="$3"

  local site_root wp_content mu_dir owner site_slug backup_dir
  site_root="$(dirname "$wp_config")"
  wp_content="$site_root/wp-content"
  mu_dir="$wp_content/mu-plugins"
  owner="$(stat -c '%U:%G' "$wp_content" 2>/dev/null || stat -c '%U:%G' "$site_root")"
  site_slug="$(echo "$site_root" | sed 's#^/##; s#[/ ]#_#g')"
  backup_dir="$BACKUP_ROOT/$timestamp/$site_slug"

  log "Deploying to $site_root"

  [[ -d "$wp_content" ]] || return 0

  run_cmd mkdir -p "$mu_dir"

  if [[ -f "$mu_dir/pdl-loader.php" || -d "$mu_dir/pdl-modules" ]]; then
    run_cmd mkdir -p "$backup_dir"
  fi

  if [[ -f "$mu_dir/pdl-loader.php" ]]; then
    run_cmd cp -a "$mu_dir/pdl-loader.php" "$backup_dir/pdl-loader.php"
  fi

  if [[ -d "$mu_dir/pdl-modules" ]]; then
    run_cmd cp -a "$mu_dir/pdl-modules" "$backup_dir/pdl-modules"
    run_cmd rm -rf "$mu_dir/pdl-modules"
  fi

  run_cmd cp -f "$payload_dir/pdl-loader.php" "$mu_dir/pdl-loader.php"
  run_cmd cp -a "$payload_dir/pdl-modules" "$mu_dir/pdl-modules"

  set_permissions "$owner" "$mu_dir"
}

main() {
  local timestamp payload_dir
  local sites=()

  require_tools

  timestamp="$(date '+%Y%m%d-%H%M%S')"
  payload_dir="$TMP_ROOT/payload-$timestamp"

  prepare_payload "$payload_dir"

  while IFS= read -r wp_config; do
    [[ -n "$wp_config" ]] && sites+=("$wp_config")
  done < <(discover_sites)

  (( ${#sites[@]} > 0 )) || fail "No WordPress installs found"

  log "Archive: $ARCHIVE_PATH"
  log "WordPress installs found: ${#sites[@]}"
  log "Backup root: $BACKUP_ROOT/$timestamp"
  [[ "$DRY_RUN" == "1" ]] && log "Dry-run mode enabled"

  for wp_config in "${sites[@]}"; do
    deploy_site "$wp_config" "$payload_dir" "$timestamp"
  done

  [[ "$DRY_RUN" != "1" ]] && rm -rf "$payload_dir"
  log "Done"
}

main "$@"
SH
chmod +x /root/pdl-deploy/deploy-mu-plugins-from-archive.sh
