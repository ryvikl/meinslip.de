#!/usr/bin/env bash
#
# Deployment nach All-Inkl per SFTP.
#
# WICHTIG — vor dem ersten Lauf lesen:
#
#  1. NIEMALS FTP auf Port 21 verwenden. Dort gehen Passwort und alle Dateien
#     im Klartext über die Leitung. All-Inkl bietet SFTP auf Port 22.
#  2. Zugangsdaten stehen in deployment/.deploy.env — diese Datei ist in
#     .gitignore erfasst und darf nie committet werden.
#  3. Wurden Zugangsdaten jemals in einem Chat, einer E-Mail oder einem
#     Ticket geteilt, sind sie verbrannt. Im KAS-Adminbereich neu setzen.
#  4. Das Ziel ist passwortgeschützt (siehe .htpasswd weiter unten) und trägt
#     noindex. Ein öffentlich erreichbarer Erotik-Marktplatz ohne
#     KJM-konforme Altersverifikation ist genau das, was laut Recherche
#     Plattformen killt: Aufsichtsverfahren und Netzsperre.
#
set -euo pipefail

WURZEL="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$WURZEL"

# --- Zugangsdaten laden ----------------------------------------------------
if [[ ! -f deployment/.deploy.env ]]; then
    echo "FEHLER: deployment/.deploy.env fehlt."
    echo "Vorlage kopieren:  cp deployment/deploy.env.example deployment/.deploy.env"
    exit 1
fi

# shellcheck disable=SC1091
source deployment/.deploy.env

: "${SFTP_HOST:?SFTP_HOST fehlt}"
: "${SFTP_BENUTZER:?SFTP_BENUTZER fehlt}"
: "${SFTP_ZIEL:?SFTP_ZIEL fehlt}"
SFTP_PORT="${SFTP_PORT:-22}"

if [[ "$SFTP_PORT" == "21" ]]; then
    echo "FEHLER: Port 21 ist unverschlüsseltes FTP. Nutze Port 22 (SFTP)."
    exit 1
fi

# --- Vorbedingungen prüfen -------------------------------------------------
echo "==> Prüfe, dass alles grün ist"
vendor/bin/phpunit --no-coverage --no-progress > /dev/null || {
    echo "FEHLER: Tests sind rot. Kein Deployment."
    exit 1
}
python3 docs/kontrast-pruefung.py > /dev/null || {
    echo "FEHLER: Kontrastprüfung schlägt fehl. Kein Deployment."
    exit 1
}

echo "==> Prüfe, dass keine Zugangsdaten mitgehen"
if git ls-files | grep -qE '(^|/)\.env$|\.deploy\.env$'; then
    echo "FEHLER: Eine .env-Datei ist versioniert. Sofort entfernen."
    exit 1
fi

echo "==> Abhängigkeiten für die Produktion"
composer install --no-dev --optimize-autoloader --quiet

# --- Übertragen ------------------------------------------------------------
# --delete räumt entfernte Dateien auf. storage/ und .env bleiben ausgenommen,
# weil sie auf dem Server leben und nicht aus dem Repo kommen.
echo "==> Übertrage nach ${SFTP_HOST}:${SFTP_ZIEL}"

rsync -avz --delete \
    -e "ssh -p ${SFTP_PORT}" \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='tests/' \
    --exclude='node_modules/' \
    --exclude='.phpunit.cache/' \
    --exclude='deployment/.deploy.env' \
    --exclude='*.sqlite' \
    ./ "${SFTP_BENUTZER}@${SFTP_HOST}:${SFTP_ZIEL}/"

echo "==> Migrationen auf dem Server"
ssh -p "${SFTP_PORT}" "${SFTP_BENUTZER}@${SFTP_HOST}" \
    "cd ${SFTP_ZIEL} && php bin/migrate"

echo "==> Betriebsprüfung"
if [[ -n "${PRUEF_URL:-}" ]]; then
    curl -fsS --max-time 15 "${PRUEF_URL}" | head -c 300 || {
        echo
        echo "WARNUNG: Betriebsprüfung fehlgeschlagen. Bitte manuell nachsehen."
    }
    echo
fi

# Entwicklungsabhängigkeiten lokal wiederherstellen.
composer install --quiet

echo "==> Fertig."
