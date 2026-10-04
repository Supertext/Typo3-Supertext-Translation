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

if [ ! -s "$DATA/system/settings.php" ]; then
  if [ -z "$TYPO3_ADMIN_PASSWORD" ]; then
    echo "TYPO3_ADMIN_PASSWORD is not set - refusing to install without an admin password." >&2
    exit 1
  fi
  echo "First boot: installing TYPO3 with Camino demo content..."
  rm -f "$APP/config/system/settings.php"
  php vendor/bin/typo3 setup -n --force \
    --driver=sqlite \
    --admin-username="${TYPO3_ADMIN_USER:-admin}" \
    --admin-user-password="$TYPO3_ADMIN_PASSWORD" \
    --admin-email="${TYPO3_ADMIN_EMAIL:-}" \
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

php vendor/bin/typo3 extension:setup
php vendor/bin/typo3 cache:flush

chown -R www-data:www-data "$DATA"
exec "$@"
