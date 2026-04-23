#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="${SOURCE_DIR:-/root/pdl-mu-source}"
SEARCH_ROOTS="${SEARCH_ROOTS:-/var/www}"
BACKUP_ROOT="${BACKUP_ROOT:-/root/pdl-mu-backups}"
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
  local tool

  for tool in find stat cp rm mkdir chown chmod sed; do
    command -v "$tool" >/dev/null 2>&1 || missing+=("$tool")
  done

  if (( ${#missing[@]} > 0 )); then
    fail "Missing required tools: ${missing[*]}"
  fi
}

validate_source() {
  [[ -d "$SOURCE_DIR" ]] || fail "Source directory not found: $SOURCE_DIR"
  [[ -f "$SOURCE_DIR/pdl-loader.php" ]] || fail "Missing $SOURCE_DIR/pdl-loader.php"
  [[ -d "$SOURCE_DIR/pdl-modules" ]] || fail "Missing $SOURCE_DIR/pdl-modules/"
}

discover_sites() {
  local root
  local found=0

  IFS=' ' read -r -a roots <<< "$SEARCH_ROOTS"
  for root in "${roots[@]}"; do
    [[ -d "$root" ]] || continue
    while IFS= read -r path; do
      found=1
      printf '%s\n' "$path"
    done < <(find "$root" -type f -name wp-config.php 2>/dev/null | sort -u)
  done

  if [[ "$found" == "0" ]]; then
    return 1
  fi
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
  local timestamp="$2"

  local site_root
  local wp_content
  local mu_dir
  local owner
  local site_slug
  local backup_dir

  site_root="$(dirname "$wp_config")"
  wp_content="$site_root/wp-content"
  mu_dir="$wp_content/mu-plugins"
  owner="$(stat -c '%U:%G' "$wp_content" 2>/dev/null || stat -c '%U:%G' "$site_root")"
  site_slug="$(echo "$site_root" | sed 's#^/##; s#[/ ]#_#g')"
  backup_dir="$BACKUP_ROOT/$timestamp/$site_slug"

  log "Deploying to $site_root"

  [[ -d "$wp_content" ]] || {
    log "Skipping $site_root because wp-content is missing"
    return 0
  }

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

  run_cmd cp -f "$SOURCE_DIR/pdl-loader.php" "$mu_dir/pdl-loader.php"
  run_cmd cp -a "$SOURCE_DIR/pdl-modules" "$mu_dir/pdl-modules"

  set_permissions "$owner" "$mu_dir"
}

main() {
  local timestamp
  local sites=()
  local wp_config

  require_tools
  validate_source

  timestamp="$(date '+%Y%m%d-%H%M%S')"

  while IFS= read -r wp_config; do
    [[ -n "$wp_config" ]] || continue
    sites+=("$wp_config")
  done < <(discover_sites || true)

  (( ${#sites[@]} > 0 )) || fail "No WordPress installs found under: $SEARCH_ROOTS"

  log "Source directory: $SOURCE_DIR"
  log "WordPress installs found: ${#sites[@]}"
  log "Backup root: $BACKUP_ROOT/$timestamp"
  [[ "$DRY_RUN" == "1" ]] && log "Dry-run mode enabled"

  for wp_config in "${sites[@]}"; do
    deploy_site "$wp_config" "$timestamp"
  done

  log "Done"
}

main "$@"
