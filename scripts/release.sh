#!/usr/bin/env bash

set -euo pipefail

# Package the EspoCRM Xero module into a versioned ZIP archive.
# Usage: ./scripts/release.sh [--version X.Y.Z]
#
# IMPORTANT: After running this script, make sure to execute 'chmod +x scripts/release.sh'
#            to ensure the script is executable.
#
# Requires: zip, php, node

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
# DEPENDENCY CHECKS
##############################################################################

check_command() {
  if ! command -v "$1" &> /dev/null; then
    _red "ERROR: Required command not found: $1"
    exit 1
  fi
}

_blue "Checking dependencies..."
check_command "zip"
check_command "php"
check_command "node"
_green "All dependencies available"

##############################################################################
# VERSION RESOLUTION
##############################################################################

VERSION=""

# Parse --version flag if provided
while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      VERSION="$2"
      shift 2
      ;;
    *)
      _red "ERROR: Unknown option: $1"
      exit 1
      ;;
  esac
done

# Get version from git tag, or use dev version
if [[ -z "$VERSION" ]]; then
  if git describe --tags --abbrev=0 &>/dev/null; then
    VERSION=$(git describe --tags --abbrev=0 | sed 's/^v//')
  else
    VERSION="0.0.0-dev"
  fi
fi

_green "Release version: $VERSION"

##############################################################################
# SETUP & CLEANUP
##############################################################################

PROJECT_ROOT="$(pwd)"
STAGING_DIR="$PROJECT_ROOT/.release-staging-$$"
RELEASES_DIR="$PROJECT_ROOT/releases"

# Cleanup function (runs on exit)
cleanup() {
  _blue "Cleaning up..."
  rm -rf "$STAGING_DIR"
}
trap cleanup EXIT

# Create releases directory
mkdir -p "$RELEASES_DIR"

##############################################################################
# STAGE FILES
##############################################################################

_blue "Staging files..."

mkdir -p "$STAGING_DIR"

# Copy server module
_green "  Staging server module..."
mkdir -p "$STAGING_DIR/custom/Espo/Modules"
cp -r "$PROJECT_ROOT/custom/Espo/Modules/Xero" \
  "$STAGING_DIR/custom/Espo/Modules/Xero"

# Copy client module
_green "  Staging client module..."
mkdir -p "$STAGING_DIR/client/custom/modules"
cp -r "$PROJECT_ROOT/client/custom/modules/xero" \
  "$STAGING_DIR/client/custom/modules/xero"

# Copy install script
_green "  Staging installation script..."
mkdir -p "$STAGING_DIR/scripts"
cp "$PROJECT_ROOT/scripts/install-modules.sh" "$STAGING_DIR/scripts/install-modules.sh"

# Copy docs (if they exist)
if [[ -d "$PROJECT_ROOT/docs" ]]; then
  _green "  Staging documentation..."
  cp -r "$PROJECT_ROOT/docs" "$STAGING_DIR/docs"
fi

# Exclude patterns: test files, node_modules, git, maps
find "$STAGING_DIR" -type f \( \
  -name "*.test.js" \
  -o -name "*.test.ts" \
  -o -name "*.test.php" \
  -o -name "*.map" \
  -o -name ".gitignore" \
  \) -delete

find "$STAGING_DIR" -type d \( \
  -name "tests" \
  -o -name "node_modules" \
  -o -name ".git" \
  \) -exec rm -rf {} + 2>/dev/null || true

_green "Files staged"

##############################################################################
# RUN TESTS
##############################################################################

_blue "Running tests..."

if php vendor/bin/phpunit \
  tests/unit/Espo/Modules/Xero/ \
  --no-coverage 2>&1 | tee /tmp/phpunit-output.log; then
  _green "PHP tests passed"
else
  _red "ERROR: PHP tests failed"
  tail -20 /tmp/phpunit-output.log
  exit 1
fi

##############################################################################
# TRANSPILE CLIENT CODE
##############################################################################

_blue "Transpiling client code..."

if node js/transpile.js; then
  _green "Transpilation successful"
else
  _red "ERROR: Transpilation failed"
  exit 1
fi

##############################################################################
# CREATE ZIP ARCHIVE
##############################################################################

_blue "Creating release archive..."

ZIP_FILE="$RELEASES_DIR/espocrm-xero-v${VERSION}.zip"

# Remove old zip if exists
rm -f "$ZIP_FILE"

# Create zip from staging directory
cd "$STAGING_DIR"
zip -r "$ZIP_FILE" . > /dev/null 2>&1
cd "$PROJECT_ROOT"

if [[ ! -f "$ZIP_FILE" ]]; then
  _red "ERROR: Failed to create ZIP archive"
  exit 1
fi

_green "Archive created: $ZIP_FILE"

##############################################################################
# CHECKSUM & FINAL OUTPUT
##############################################################################

CHECKSUM=$(sha256sum "$ZIP_FILE" | awk '{print $1}')

_green "Release package ready!"
echo ""
_blue "Package details:"
echo "  File:      $ZIP_FILE"
echo "  Version:   $VERSION"
echo "  Checksum:  $CHECKSUM"
echo ""
_blue "Deployment:"
_yellow "scp $ZIP_FILE user@server:/tmp/"
_yellow "cd /path/to/espocrm && unzip -o /tmp/$(basename "$ZIP_FILE")"
_yellow "./scripts/install-modules.sh"
echo ""
