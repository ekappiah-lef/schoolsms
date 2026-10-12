#!/bin/bash
# Runs inside the "backup" container: dumps the database to /backups/<date>/<name>_<time>.sql.gz
# at start and every 6 hours, and removes dumps older than 30 days.
while true; do
  out=/backups/$(date +%F); mkdir -p "$out"
  f="$out/${MARIADB_DATABASE}_$(date +%H%M).sql.gz"
  if mariadb-dump -hdb -uroot -p"${MARIADB_ROOT_PASSWORD}" --single-transaction --routines --triggers --events "${MARIADB_DATABASE}" | gzip > "$f.tmp"; then
    mv "$f.tmp" "$f"; echo "$(date '+%F %T') ok $f" >> /backups/backup.log
  else
    rm -f "$f.tmp"; echo "$(date '+%F %T') FAILED" >> /backups/backup.log
  fi
  find /backups -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime +30 -exec rm -rf {} +
  sleep 21600
done
