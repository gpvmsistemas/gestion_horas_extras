#!/usr/bin/env bash
# Concatena migraciones RRHH para hosting (estado primitivo → esquema completo).
# Ver HOSTING_MIGRATION.md

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/migration_hosting_full.sql"

STEPS=(
  "1|migration_v2.sql"
  "2|migration_companies_grupo.sql"
  "3|migration_marcaciones_cache.sql"
  "3b|migration_clock_events_device.sql"
  "4|migration_users_profile_extended.sql"
  "5|migration_attendance_summary.sql"
  "6|migration_attendance_justifications.sql"
  "8|migration_requests_admin_review.sql"
  "9|migration_shift_swaps_fix.sql"
  "10|migration_shift_swaps_accepter_null.sql"
  "12|migration_learning.sql"
  "14|migration_learning_enrich.sql"
  "15|migration_learning_quiz_bonus.sql"
  "16|migration_user_login_logs.sql"
  "17|migration_learning_reviews.sql"
  "18|migration_areas_global.sql"
  "20|migration_notifications_paystubs.sql"
  "21|migration_employee_incidents.sql"
  "22|migration_collective_agreements.sql"
  "23|migration_schedule_vacation_types.sql"
  "24|migration_users_probation_date_phpmyadmin.sql"
  "25|migration_role_supervisor.sql"
  "26|migration_areas_agreement.sql"
  "27|migration_users_plex_operator.sql"
  "28|migration_peer_stars.sql"
  "29|migration_surveys.sql"
  "30|migration_casapav_tasks.sql"
  "31|migration_casapav_phase_b.sql"
  "32|migration_system_settings.sql"
  "32b|migration_system_settings_employee_portal.sql"
  "33|migration_overtime_visibility.sql"
  "34|migration_cp_extras_visibility.sql"
  "35|migration_prode_wc2026.sql"
  "36|migration_survey_responses_unique.sql"
  "37|migration_cp_closure_lot_unique.sql"
  "38|migration_vacation_management_v2.sql"
  "39|migration_employee_record_complete.sql"
)

{
  echo "-- migration_hosting_full.sql"
  echo "-- Generado: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "-- Base objetivo: paviotti_lanaturaleza (estado primitivo RRHH + tablas legacy)"
  echo "-- BACKUP OBLIGATORIO antes de importar en producción."
  echo ""
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';"
  echo ""

  for entry in "${STEPS[@]}"; do
    step="${entry%%|*}"
    file="${entry#*|}"
    path="$ROOT/$file"
    if [[ ! -f "$path" ]]; then
      echo "ERROR: falta $file" >&2
      exit 1
    fi
    echo "-- =========================================================================="
    echo "-- STEP $step — $file"
    echo "-- =========================================================================="
    cat "$path"
    echo ""
    echo ""
  done

  echo "SET FOREIGN_KEY_CHECKS = 1;"
  echo "SELECT 'migration_hosting_full completada.' AS resultado;"
} > "$OUT"

echo "Escrito: $OUT ($(wc -l < "$OUT") líneas)"
