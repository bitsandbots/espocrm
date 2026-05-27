#!/usr/bin/env bash
# =============================================================================
# reset-db.sh — Reset EspoCRM database, preserving settings and configuration
# =============================================================================
#
# WHAT IS KEPT:
#   • System config & secrets (app_secret, system_data, integration, extension)
#   • Roles & permissions (role, role_team, portal_role)
#   • Email setup (email_template, email_filter, email_folder, inbound_email)
#   • Layouts & dashboards (layout_record, layout_set, dashboard_template)
#   • Pipelines, currencies, working time calendars, webhooks
#   • Admin/system users (type = 'admin' or 'system') — see --keep-users
#   • Reference data (address_country, next_number, unique_id)
#   • Lead capture forms, document folder structure, KB category tree
#
# WHAT IS CLEARED:
#   • All CRM records (accounts, contacts, leads, opportunities, cases, etc.)
#   • Activities (meetings, calls, tasks, emails)
#   • Campaign data, target lists, documents, notes
#   • All log & audit tables
#   • Job queues, notifications, auth tokens
#   • Inventory records, invoices
#   • Non-admin users (by default; see --keep-users)
#
# USAGE:
#   bin/reset-db.sh [OPTIONS]
#
# OPTIONS:
#   --dry-run        Print SQL that would run, don't execute it
#   --keep-users     Keep ALL users, not just admins
#   --no-users       Remove all users including admins (requires --yes)
#   --keep-logs      Don't truncate log and audit tables
#   --yes            Skip confirmation prompt
#   --help           Show this help
#
# EXAMPLES:
#   bin/reset-db.sh                    # Reset, keep admin users (prompted)
#   bin/reset-db.sh --dry-run          # Preview SQL
#   bin/reset-db.sh --keep-users --yes # Keep all users, no prompt
#   bin/reset-db.sh --no-users --yes   # Full wipe including admins

set -euo pipefail

# ── Defaults ─────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CONFIG_FILE="$PROJECT_ROOT/data/config-internal.php"
CACHE_DIR="$PROJECT_ROOT/data/cache"

DRY_RUN=false
KEEP_USERS=false     # keep all users
NO_USERS=false       # remove even admin users
KEEP_LOGS=false
AUTO_YES=false

# ── Argument parsing ──────────────────────────────────────────────────────────
for arg in "$@"; do
  case "$arg" in
    --dry-run)    DRY_RUN=true ;;
    --keep-users) KEEP_USERS=true ;;
    --no-users)   NO_USERS=true ;;
    --keep-logs)  KEEP_LOGS=true ;;
    --yes|-y)     AUTO_YES=true ;;
    --help|-h)
      sed -n '/^# USAGE:/,/^[^#]/{ /^[^#]/d; s/^# \{0,2\}//; p }' "$0"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg  (use --help)" >&2
      exit 1
      ;;
  esac
done

if $NO_USERS && ! $AUTO_YES && ! $DRY_RUN; then
  echo "ERROR: --no-users requires --yes (destructive — removes all user accounts)." >&2
  exit 1
fi

# ── Read DB credentials from PHP config ──────────────────────────────────────
if [[ ! -f "$CONFIG_FILE" ]]; then
  echo "ERROR: config-internal.php not found at $CONFIG_FILE" >&2
  exit 1
fi

read_php_value() {
  php -r "
    \$c = require('$CONFIG_FILE');
    echo \$c['database']['$1'] ?? '';
  " 2>/dev/null
}

DB_HOST=$(read_php_value host)
DB_PORT=$(read_php_value port)
DB_NAME=$(read_php_value dbname)
DB_USER=$(read_php_value user)
DB_PASS=$(read_php_value password)

DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"

if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
  echo "ERROR: Could not read database credentials from config-internal.php" >&2
  exit 1
fi

# ── MySQL helper ──────────────────────────────────────────────────────────────
mysql_cmd() {
  mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    "$DB_NAME" \
    2>/dev/null
}

sql_exec() {
  local sql="$1"
  if $DRY_RUN; then
    echo "$sql"
  else
    echo "$sql" | mysql_cmd
  fi
}

# Verify connection
if ! $DRY_RUN; then
  if ! echo "SELECT 1;" | mysql_cmd > /dev/null; then
    echo "ERROR: Cannot connect to MySQL ($DB_USER@$DB_HOST/$DB_NAME)" >&2
    exit 1
  fi
fi

# ── Banner ────────────────────────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║           EspoCRM Database Reset                             ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "  Database : $DB_NAME @ $DB_HOST"
$DRY_RUN    && echo "  Mode     : DRY RUN (no changes will be made)"
$KEEP_USERS && echo "  Users    : All users preserved"
$NO_USERS   && echo "  Users    : ALL users will be removed (including admins!)"
! $KEEP_USERS && ! $NO_USERS && echo "  Users    : Admin/system users preserved, regular users removed"
$KEEP_LOGS  && echo "  Logs     : Log tables preserved"
echo ""

# ── Confirmation ─────────────────────────────────────────────────────────────
if ! $DRY_RUN && ! $AUTO_YES; then
  read -rp "  This will DELETE all CRM data. Are you sure? [y/N] " confirm
  echo ""
  if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "  Aborted."
    exit 0
  fi
fi

# ── Tables to TRUNCATE ────────────────────────────────────────────────────────
# (configuration and system tables are NOT in this list)
DATA_TABLES=(
  # CRM core entities
  account
  account_contact
  account_document
  account_portal_user
  account_target_list

  contact
  contact_document
  contact_meeting
  contact_opportunity
  contact_target_list

  lead
  lead_meeting
  lead_target_list

  opportunity

  "case"
  case_contact
  case_knowledge_base_article

  # Activities
  meeting
  meeting_user

  call
  call_contact
  call_lead
  call_user

  task
  reminder

  # Email / messaging
  email
  email_account
  email_email_account
  email_email_address
  email_inbound_email
  email_queue_item
  email_user
  email_address
  phone_number
  entity_email_address
  entity_phone_number

  # Documents & attachments
  document
  document_lead
  document_opportunity
  attachment
  array_value

  # Knowledge base articles (not categories — those are config)
  knowledge_base_article
  knowledge_base_article_knowledge_base_category
  knowledge_base_article_portal

  # Campaigns & targeting
  campaign
  campaign_tracking_url
  campaign_target_list
  campaign_target_list_excluding
  target
  target_list
  target_list_user

  # Notes & stream
  note
  note_portal
  note_team
  note_user
  notification
  star_subscription
  stream_subscription

  # Import / Export
  import
  import_entity
  import_error
  export
  mass_action

  # Mass email
  mass_email
  mass_email_target_list
  mass_email_target_list_excluding

  # Auth / session tokens
  auth_token
  two_factor_code
  password_change_request
  o_auth_account

  # Background jobs
  job
  webhook_queue_item
  webhook_event_queue_item

  # Inventory module records
  inventory_order
  inventory_order_item
  inventory_purchase_order
  inventory_purchase_order_item
  inventory_stock_adjustment
  inventory_product
  inventory_category

  # Finance
  invoice

  # Misc transactional
  kanban_order
  sms
  sms_phone_number
  external_account
  entity_collaborator
  entity_team
  entity_user
  user_reaction
  user_working_time_range

  # Portal users
  portal_user
  portal_role_user
)

# Log tables (separate so --keep-logs can skip them)
LOG_TABLES=(
  action_history_record
  app_log_record
  auth_log_record
  campaign_log_record
  lead_capture_log_record
  scheduled_job_log_record
)

# ── Build SQL ─────────────────────────────────────────────────────────────────
SQL=""
SQL+="SET FOREIGN_KEY_CHECKS = 0;\n"
SQL+="\n-- ── CRM data tables ───────────────────────────────────────────\n"

# Only truncate tables that actually exist in the DB
EXISTING_TABLES=$(echo "SHOW TABLES;" | mysql_cmd 2>/dev/null || true)

for tbl in "${DATA_TABLES[@]}"; do
  if echo "$EXISTING_TABLES" | grep -qx "$tbl"; then
    SQL+="TRUNCATE TABLE \`${tbl}\`;\n"
  fi
done

if ! $KEEP_LOGS; then
  SQL+="\n-- ── Log & audit tables ────────────────────────────────────────\n"
  for tbl in "${LOG_TABLES[@]}"; do
    if echo "$EXISTING_TABLES" | grep -qx "$tbl"; then
      SQL+="TRUNCATE TABLE \`${tbl}\`;\n"
    fi
  done
fi

# ── User handling ─────────────────────────────────────────────────────────────
if $NO_USERS; then
  SQL+="\n-- ── Users (full removal) ──────────────────────────────────────\n"
  SQL+="TRUNCATE TABLE \`user\`;\n"
  SQL+="TRUNCATE TABLE \`user_data\`;\n"
  SQL+="TRUNCATE TABLE \`preferences\`;\n"
  SQL+="TRUNCATE TABLE \`role_user\`;\n"
  SQL+="TRUNCATE TABLE \`team_user\`;\n"
elif ! $KEEP_USERS; then
  SQL+="\n-- ── Users (keep admin/system only) ────────────────────────────\n"
  SQL+="DELETE FROM \`user\` WHERE \`type\` NOT IN ('admin', 'system');\n"
  SQL+="DELETE FROM \`user_data\` WHERE \`user_id\` NOT IN (SELECT \`id\` FROM \`user\`);\n"
  SQL+="DELETE FROM \`preferences\` WHERE \`id\` NOT IN (SELECT \`id\` FROM \`user\`);\n"
  SQL+="DELETE FROM \`role_user\` WHERE \`user_id\` NOT IN (SELECT \`id\` FROM \`user\`);\n"
  SQL+="DELETE FROM \`team_user\` WHERE \`user_id\` NOT IN (SELECT \`id\` FROM \`user\`);\n"
fi

SQL+="\nSET FOREIGN_KEY_CHECKS = 1;\n"

# ── Execute ───────────────────────────────────────────────────────────────────
if $DRY_RUN; then
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━ SQL Preview ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo -e "$SQL"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
else
  echo "  Clearing data tables..."
  echo -e "$SQL" | mysql_cmd
fi

# ── Clear application cache ───────────────────────────────────────────────────
if ! $DRY_RUN && [[ -d "$CACHE_DIR" ]]; then
  echo "  Clearing application cache..."
  find "$CACHE_DIR" -mindepth 1 -delete 2>/dev/null || true
fi

# ── Summary ───────────────────────────────────────────────────────────────────
if ! $DRY_RUN; then
  # Count remaining users
  USER_COUNT=$(echo "SELECT COUNT(*) FROM \`user\` WHERE deleted = 0;" | mysql_cmd 2>/dev/null || echo "?")
  echo ""
  echo "  ✓ Database reset complete"
  echo "  ✓ Cache cleared"
  echo "  ✓ Remaining users: $USER_COUNT"
  echo ""
  echo "  Configuration preserved:"
  echo "    roles, email templates, layouts, pipelines, webhooks,"
  echo "    inbound email, integration settings, portal config,"
  echo "    working time calendars, currencies, scheduled jobs"
  echo ""
fi
