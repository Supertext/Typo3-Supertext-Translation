#!/bin/sh
# First boot: installs TYPO3 with the Camino demo content into /data.
# Every boot: links persistent folders, runs extension setup, flushes caches.
set -e

APP=/var/www/typo3
DATA=/data
cd "$APP"

# mod_php needs the prefork MPM. On Railway a second MPM ends up enabled and
# Apache refuses to start ("More than one MPM loaded"), so keep only prefork.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod -q mpm_prefork

# Apache listens on Railway's $PORT (default 80).
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Persistent state: database + caches (var), uploads (fileadmin), settings, sites.
mkdir -p "$DATA/var" "$DATA/fileadmin" "$DATA/sites" "$DATA/system" "$APP/config/system"
link() { # link <path in app> <path in data>
  if [ ! -L "$1" ]; then
    if [ -e "$1" ] && [ -z "$(ls -A "$2" 2>/dev/null)" ]; then cp -a "$1/." "$2/" 2>/dev/null || true; fi
    rm -rf "$1"
  fi
  ln -sfn "$2" "$1"
}
link "$APP/var" "$DATA/var"
link "$APP/public/fileadmin" "$DATA/fileadmin"
link "$APP/config/sites" "$DATA/sites"
ln -sfn "$DATA/system/settings.php" "$APP/config/system/settings.php"

# Demo accounts (see "Demo accounts rule" in CLAUDE.md). DEMO_ADMIN_* win; the older
# TYPO3_ADMIN_* names still work. The e-mail address doubles as the TYPO3 username.
ADMIN_USER="${DEMO_ADMIN_EMAIL:-${TYPO3_ADMIN_USER:-admin}}"
ADMIN_EMAIL="${DEMO_ADMIN_EMAIL:-${TYPO3_ADMIN_EMAIL:-}}"
ADMIN_PASSWORD="${DEMO_ADMIN_PASSWORD:-${TYPO3_ADMIN_PASSWORD:-}}"

if [ ! -s "$DATA/system/settings.php" ]; then
  if [ -z "$ADMIN_PASSWORD" ]; then
    echo "DEMO_ADMIN_PASSWORD (or TYPO3_ADMIN_PASSWORD) is not set - refusing to install without an admin password." >&2
    exit 1
  fi
  echo "First boot: installing TYPO3 with Camino demo content..."
  rm -f "$APP/config/system/settings.php"
  # Passed via the environment, not the command line, so the password never shows in process lists.
  TYPO3_SETUP_ADMIN_PASSWORD="$ADMIN_PASSWORD" php vendor/bin/typo3 setup -n --force \
    --driver=sqlite \
    --admin-username="$ADMIN_USER" \
    --admin-email="$ADMIN_EMAIL" \
    --project-name="${TYPO3_PROJECT_NAME:-Supertext TYPO3 Demo}" \
    --distribution=theme_camino \
    --server-type=apache
  # setup writes a real file; move it onto the volume and link it back.
  if [ -f "$APP/config/system/settings.php" ] && [ ! -L "$APP/config/system/settings.php" ]; then
    mv "$APP/config/system/settings.php" "$DATA/system/settings.php"
    ln -sfn "$DATA/system/settings.php" "$APP/config/system/settings.php"
  fi
  # Demo languages: English (default), German (Switzerland), French (Switzerland).
  cp /opt/demo/site-config.yaml "$DATA/sites/camino/config.yaml"
  echo "TYPO3 installed."
fi

# Every boot: create missing demo accounts; existing ones are never changed.
# TYPO3 ships no ready-made editor group, so the editor account is an admin
# without maintainer rights (no access to the Install Tool / system maintenance).
ensure_user() { # ensure_user <label> <username> <password> <admin 0|1>
  [ -n "$2" ] && [ -n "$3" ] || return 0
  if out=$(TYPO3_BE_USER_NAME="$2" TYPO3_BE_USER_EMAIL="$2" TYPO3_BE_USER_PASSWORD="$3" \
      TYPO3_BE_USER_ADMIN="$4" TYPO3_BE_USER_MAINTAINER=0 \
      php vendor/bin/typo3 backend:user:create -n 2>&1); then
    echo "[demo] created $1 account"
  elif echo "$out" | grep -q "already taken"; then
    echo "[demo] $1 account already exists, leaving it unchanged"
  else
    # TYPO3's message names the rule that failed (e.g. password policy); it never contains the password.
    echo "[demo] WARNING: $1 account not created: $(echo "$out" | grep -v '^ *$' | grep -v 'backend:user:create \[' | sed -n '2,4p' | tr -s ' ' | tr '\n' ' ')" >&2
  fi
}
ensure_user DEMO_ADMIN "$ADMIN_USER" "$ADMIN_PASSWORD" 1
ensure_user DEMO_EDITOR "${DEMO_EDITOR_EMAIL:-}" "${DEMO_EDITOR_PASSWORD:-}" 1

php vendor/bin/typo3 extension:setup
php vendor/bin/typo3 cache:flush

chown -R www-data:www-data "$DATA"
exec "$@"
