#!/usr/bin/env bash

set -euo pipefail

# Install the EspoCRM Xero module onto an existing EspoCRM instance.
# Usage: ./scripts/install-modules.sh [--espo-path PATH]
#
# IMPORTANT: After running this script, make sure to execute 'chmod +x scripts/install-modules.sh'
#            to ensure the script is executable.

##############################################################################
# COLOR & OUTPUT UTILITIES
##############################################################################

_green() {
  echo -e "\033[32m$*\033[0m"
}

_yellow() {
  echo -e "\033[33m$*\033[0m"
}

_red() {
  echo -e "\033[31m$*\033[0m"
}

_blue() {
  echo -e "\033[34m$*\033[0m"
}

##############################################################################
# DEFAULT VALUES & ARGUMENT PARSING
##############################################################################

ESPO_PATH="."

while [[ $# -gt 0 ]]; do
  case "$1" in
    --espo-path)
      ESPO_PATH="$2"
      shift 2
      ;;
    *)
      _red "ERROR: Unknown option: $1"
      exit 1
      ;;
  esac
done

##############################################################################
# VALIDATION
##############################################################################

# Resolve to absolute path
ESPO_PATH="$(cd "$ESPO_PATH" 2>/dev/null && pwd)" || {
  _red "ERROR: EspoCRM path does not exist: $ESPO_PATH"
  exit 1
}

# Check for data/config.php
if [[ ! -f "$ESPO_PATH/data/config.php" ]]; then
  _red "ERROR: EspoCRM config not found at $ESPO_PATH/data/config.php"
  exit 1
fi

_green "Found EspoCRM at: $ESPO_PATH"

# Check user ownership
CONFIG_OWNER=$(stat -c "%U" "$ESPO_PATH/data/config.php")
CURRENT_USER=$(whoami)

if [[ "$CURRENT_USER" != "$CONFIG_OWNER" ]]; then
  _yellow "WARNING: Running as '$CURRENT_USER' but config.php is owned by '$CONFIG_OWNER'"
  _yellow "This may cause permission issues during module installation."
fi

##############################################################################
# COPY MODULE
##############################################################################

_blue "Installing Xero module..."

copy_module() {
  local src="$1"
  local dst="$2"
  local name="$3"

  if [[ ! -d "$src" ]]; then
    _red "ERROR: Source not found: $src"
    return 1
  fi

  # If source and destination are the same, skip (already in place)
  if [[ "$(cd "$src" && pwd)" == "$(cd "$dst" 2>/dev/null && pwd)" ]]; then
    _green "  $name already in place"
    return 0
  fi

  _green "  Copying $name..."
  cp -r "$src" "$dst"
}

copy_module \
  "$ESPO_PATH/custom/Espo/Modules/Xero" \
  "$ESPO_PATH/custom/Espo/Modules/Xero" \
  "Xero module" || exit 1

copy_module \
  "$ESPO_PATH/client/custom/modules/xero" \
  "$ESPO_PATH/client/custom/modules/xero" \
  "Xero client module" || exit 1

##############################################################################
# REBUILD & CACHE CLEAR
##############################################################################

_blue "Running system rebuilds..."

cd "$ESPO_PATH"

if ! php command.php rebuild 2>&1 | grep -q "Rebuild succeeded"; then
  _red "ERROR: Rebuild failed"
  exit 1
fi
_green "  Rebuild succeeded"

php command.php clear-cache
_green "  Cache cleared"

##############################################################################
# POST-INSTALL INSTRUCTIONS
##############################################################################

_green "Installation complete!"
echo ""
_blue "Post-installation steps:"
echo ""
echo "1. Configure cron job (as root or with sudo):"
_yellow "   * * * * * $CONFIG_OWNER php $ESPO_PATH/cron.php > /dev/null 2>&1"
echo ""
echo "2. Configure credentials:"
_yellow "   Go to Admin > Integrations > Xero"
echo ""
echo "3. Enable scheduled jobs:"
_yellow "   Go to Admin > Scheduled Jobs and enable:"
echo "     - SyncFromXero"
echo "     - ReconcileXero"
echo ""
echo "4. Xero OAuth (important):"
_yellow "   Xero requires HTTPS for OAuth redirects."
_yellow "   Ensure Admin > System > Site URL uses https://"
echo ""
