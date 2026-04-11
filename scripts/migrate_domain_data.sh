#!/usr/bin/env bash

set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  OLD_DB_URL=postgresql://... NEW_DB_URL=postgresql://... ./scripts/migrate_domain_data.sh [options]

Options:
  --output-dir DIR   Reuse or persist exported CSV files in DIR.
  --skip-export      Skip export and import existing CSVs from --output-dir.
  --skip-import      Only export data, do not import into NEW_DB_URL.
  --truncate-new     Truncate target domain tables before importing.
  --help             Show this help.

Notes:
  - Run `php artisan migrate --force` against NEW_DB_URL before importing.
  - The script only migrates domain tables used by the current API.
  - Optional source columns that do not exist are replaced with safe defaults.
EOF
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing required command: $1" >&2
        exit 1
    fi
}

psql_value() {
    local db_url="$1"
    local sql="$2"

    PSQLRC=/dev/null psql "$db_url" -X -v ON_ERROR_STOP=1 -qAt -c "$sql"
}

table_exists() {
    local db_url="$1"
    local table_name="$2"

    [[ "$(psql_value "$db_url" "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '${table_name}' LIMIT 1;")" == "1" ]]
}

column_exists() {
    local db_url="$1"
    local table_name="$2"
    local column_name="$3"

    [[ "$(psql_value "$db_url" "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '${table_name}' AND column_name = '${column_name}' LIMIT 1;")" == "1" ]]
}

source_column_expr() {
    local table_name="$1"
    local column_name="$2"
    local fallback_sql="$3"
    local required="${4:-0}"

    if column_exists "$OLD_DB_URL" "$table_name" "$column_name"; then
        printf '"%s" AS "%s"' "$column_name" "$column_name"
        return 0
    fi

    if [[ "$required" == "1" ]]; then
        echo "Required source column missing: ${table_name}.${column_name}" >&2
        exit 1
    fi

    printf '%s AS "%s"' "$fallback_sql" "$column_name"
}

build_select_list() {
    local table_name="$1"

    case "$table_name" in
        schools)
            printf '%s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_name" "''::text" 1)" \
                "$(source_column_expr "$table_name" "school_code" "''::text" 1)" \
                "$(source_column_expr "$table_name" "password" "''::text" 1)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)"
            printf ', %s' "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        users)
            printf '%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "name" "''::text" 1)" \
                "$(source_column_expr "$table_name" "email" "''::text" 1)" \
                "$(source_column_expr "$table_name" "password" "''::text" 1)" \
                "$(source_column_expr "$table_name" "role" "'user'::text" 1)" \
                "$(source_column_expr "$table_name" "active" '1::smallint' 0)" \
                "$(source_column_expr "$table_name" "api_token" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "api_token_expires_at" 'NULL::timestamp without time zone' 0)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        resource_categories)
            printf '%s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "name" "''::text" 1)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        resources)
            printf '%s, %s, %s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "category_id" 'NULL::bigint' 0)" \
                "$(source_column_expr "$table_name" "name" "''::text" 1)" \
                "$(source_column_expr "$table_name" "active" '1::smallint' 0)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        class_groups|subjects)
            printf '%s, %s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "name" "''::text" 1)" \
                "$(source_column_expr "$table_name" "active" '1::smallint' 0)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        lesson_slots)
            printf '%s, %s, %s, %s, %s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "lesson_number" '0::integer' 1)" \
                "$(source_column_expr "$table_name" "label" "''::text" 1)" \
                "$(source_column_expr "$table_name" "start_time" 'NULL::time without time zone' 0)" \
                "$(source_column_expr "$table_name" "end_time" 'NULL::time without time zone' 0)" \
                "$(source_column_expr "$table_name" "active" '1::smallint' 0)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        bookings)
            printf '%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "user_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "resource_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "class_group_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "subject_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "booking_date" 'NULL::date' 1)" \
                "$(source_column_expr "$table_name" "status" "'scheduled'::text" 0)" \
                "$(source_column_expr "$table_name" "purpose" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "cancelled_at" 'NULL::timestamp without time zone' 0)" \
                "$(source_column_expr "$table_name" "completed_at" 'NULL::timestamp without time zone' 0)" \
                "$(source_column_expr "$table_name" "completed_by_user_id" 'NULL::bigint' 0)" \
                "$(source_column_expr "$table_name" "cancelled_by_user_id" 'NULL::bigint' 0)" \
                "$(source_column_expr "$table_name" "completion_feedback" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "idempotency_key" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        booking_lessons)
            printf '%s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "booking_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "lesson_slot_id" 'NULL::bigint' 1)"
            ;;
        notifications)
            printf '%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s' \
                "$(source_column_expr "$table_name" "id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "school_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "user_id" 'NULL::bigint' 1)" \
                "$(source_column_expr "$table_name" "type" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "title" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "message" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "booking_id" 'NULL::bigint' 0)" \
                "$(source_column_expr "$table_name" "metadata_json" 'NULL::text' 0)" \
                "$(source_column_expr "$table_name" "read_at" 'NULL::timestamp without time zone' 0)" \
                "$(source_column_expr "$table_name" "created_at" 'CURRENT_TIMESTAMP' 0)" \
                "$(source_column_expr "$table_name" "updated_at" 'CURRENT_TIMESTAMP' 0)"
            ;;
        *)
            echo "Unsupported table mapping: ${table_name}" >&2
            exit 1
            ;;
    esac
}

target_columns() {
    local table_name="$1"

    case "$table_name" in
        schools)
            echo 'id,school_name,school_code,password,created_at,updated_at'
            ;;
        users)
            echo 'id,school_id,name,email,password,role,active,api_token,api_token_expires_at,created_at,updated_at'
            ;;
        resource_categories)
            echo 'id,name,created_at,updated_at'
            ;;
        resources)
            echo 'id,school_id,category_id,name,active,created_at,updated_at'
            ;;
        class_groups|subjects)
            echo 'id,school_id,name,active,created_at,updated_at'
            ;;
        lesson_slots)
            echo 'id,school_id,lesson_number,label,start_time,end_time,active,created_at,updated_at'
            ;;
        bookings)
            echo 'id,school_id,user_id,resource_id,class_group_id,subject_id,booking_date,status,purpose,cancelled_at,completed_at,completed_by_user_id,cancelled_by_user_id,completion_feedback,idempotency_key,created_at,updated_at'
            ;;
        booking_lessons)
            echo 'id,booking_id,lesson_slot_id'
            ;;
        notifications)
            echo 'id,school_id,user_id,type,title,message,booking_id,metadata_json,read_at,created_at,updated_at'
            ;;
        *)
            echo "Unsupported target mapping: ${table_name}" >&2
            exit 1
            ;;
    esac
}

export_table() {
    local table_name="$1"
    local csv_path="${OUTPUT_DIR}/${table_name}.csv"
    local select_list

    if ! table_exists "$OLD_DB_URL" "$table_name"; then
        echo "Source table not found: ${table_name}" >&2
        exit 1
    fi

    select_list="$(build_select_list "$table_name")"

    echo "Exporting ${table_name} -> ${csv_path}"
    PSQLRC=/dev/null psql "$OLD_DB_URL" -X -v ON_ERROR_STOP=1 <<EOF
\copy (
    SELECT ${select_list}
    FROM ${table_name}
    ORDER BY id
) TO '${csv_path}' WITH CSV HEADER
EOF
}

import_table() {
    local table_name="$1"
    local csv_path="${OUTPUT_DIR}/${table_name}.csv"
    local columns

    if ! table_exists "$NEW_DB_URL" "$table_name"; then
        echo "Target table not found: ${table_name}. Run migrations first." >&2
        exit 1
    fi

    if [[ ! -f "$csv_path" ]]; then
        echo "Missing CSV for import: ${csv_path}" >&2
        exit 1
    fi

    columns="$(target_columns "$table_name")"

    echo "Importing ${table_name} <- ${csv_path}"
    PSQLRC=/dev/null psql "$NEW_DB_URL" -X -v ON_ERROR_STOP=1 <<EOF
\copy ${table_name} (${columns}) FROM '${csv_path}' WITH CSV HEADER
EOF
}

truncate_target_tables() {
    echo "Truncating target domain tables"
    PSQLRC=/dev/null psql "$NEW_DB_URL" -X -v ON_ERROR_STOP=1 <<'EOF'
TRUNCATE TABLE
    notifications,
    booking_lessons,
    bookings,
    lesson_slots,
    subjects,
    class_groups,
    resources,
    users,
    schools,
    resource_categories
RESTART IDENTITY CASCADE;
EOF
}

reset_sequences() {
    echo "Resetting target sequences"
    PSQLRC=/dev/null psql "$NEW_DB_URL" -X -v ON_ERROR_STOP=1 <<'EOF'
SELECT setval(pg_get_serial_sequence('schools', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM schools;
SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM users;
SELECT setval(pg_get_serial_sequence('resource_categories', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM resource_categories;
SELECT setval(pg_get_serial_sequence('resources', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM resources;
SELECT setval(pg_get_serial_sequence('class_groups', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM class_groups;
SELECT setval(pg_get_serial_sequence('subjects', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM subjects;
SELECT setval(pg_get_serial_sequence('lesson_slots', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM lesson_slots;
SELECT setval(pg_get_serial_sequence('bookings', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM bookings;
SELECT setval(pg_get_serial_sequence('booking_lessons', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM booking_lessons;
SELECT setval(pg_get_serial_sequence('notifications', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM notifications;
EOF
}

print_counts() {
    local db_url="$1"
    local label="$2"

    echo
    echo "Row counts in ${label}:"
    PSQLRC=/dev/null psql "$db_url" -X -v ON_ERROR_STOP=1 -F $'\t' -At <<'EOF'
SELECT 'schools', COUNT(*) FROM schools
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'resource_categories', COUNT(*) FROM resource_categories
UNION ALL SELECT 'resources', COUNT(*) FROM resources
UNION ALL SELECT 'class_groups', COUNT(*) FROM class_groups
UNION ALL SELECT 'subjects', COUNT(*) FROM subjects
UNION ALL SELECT 'lesson_slots', COUNT(*) FROM lesson_slots
UNION ALL SELECT 'bookings', COUNT(*) FROM bookings
UNION ALL SELECT 'booking_lessons', COUNT(*) FROM booking_lessons
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
ORDER BY 1;
EOF
}

OLD_DB_URL="${OLD_DB_URL:-}"
NEW_DB_URL="${NEW_DB_URL:-}"
OUTPUT_DIR=""
SKIP_EXPORT=0
SKIP_IMPORT=0
TRUNCATE_NEW=0
TEMP_DIR_CREATED=0

while (($# > 0)); do
    case "$1" in
        --output-dir)
            OUTPUT_DIR="${2:-}"
            shift 2
            ;;
        --skip-export)
            SKIP_EXPORT=1
            shift
            ;;
        --skip-import)
            SKIP_IMPORT=1
            shift
            ;;
        --truncate-new)
            TRUNCATE_NEW=1
            shift
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

require_command psql

if [[ "$SKIP_EXPORT" == "0" ]] && [[ -z "$OLD_DB_URL" ]]; then
    echo "OLD_DB_URL is required unless --skip-export is used." >&2
    exit 1
fi

if [[ "$SKIP_IMPORT" == "0" ]] && [[ -z "$NEW_DB_URL" ]]; then
    echo "NEW_DB_URL is required unless --skip-import is used." >&2
    exit 1
fi

if [[ -z "$OUTPUT_DIR" ]]; then
    OUTPUT_DIR="$(mktemp -d)"
    TEMP_DIR_CREATED=1
fi

mkdir -p "$OUTPUT_DIR"

if [[ "$TEMP_DIR_CREATED" == "1" ]]; then
    trap 'rm -rf "$OUTPUT_DIR"' EXIT
fi

TABLES=(
    schools
    users
    resource_categories
    resources
    class_groups
    subjects
    lesson_slots
    bookings
    booking_lessons
    notifications
)

if [[ "$SKIP_EXPORT" == "0" ]]; then
    for table_name in "${TABLES[@]}"; do
        export_table "$table_name"
    done
fi

if [[ "$SKIP_IMPORT" == "0" ]]; then
    if [[ "$TRUNCATE_NEW" == "1" ]]; then
        truncate_target_tables
    fi

    for table_name in "${TABLES[@]}"; do
        import_table "$table_name"
    done

    reset_sequences
fi

if [[ -n "$OLD_DB_URL" ]]; then
    print_counts "$OLD_DB_URL" "source"
fi

if [[ "$SKIP_IMPORT" == "0" ]]; then
    print_counts "$NEW_DB_URL" "target"
fi

echo
echo "Migration data files are in: ${OUTPUT_DIR}"
