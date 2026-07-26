#!/usr/bin/env bash
#
# Baut ein hochladefertiges Verzeichnis unter deployment/upload/.
#
# Zweck: Deployment ohne SSH, ohne Composer, ohne rsync — einfach den Inhalt
# von deployment/upload/ per FTP-Programm auf den Server ziehen.
#
# Produktiv hat die Anwendung keine externen Abhängigkeiten; der eigene
# Klassenlader in app/Core/Autoloader.php ersetzt Composer. Deshalb muss
# vendor/ nicht mit hochgeladen werden.
#
set -euo pipefail

WURZEL="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$WURZEL"

ZIEL="deployment/upload"

echo "==> Prüfe, dass alles grün ist"
if [[ -x vendor/bin/phpunit ]]; then
    vendor/bin/phpunit --no-coverage --no-progress > /dev/null || {
        echo "FEHLER: Tests sind rot. Kein Paket."
        exit 1
    }
    echo "    Tests grün"
else
    echo "    (PHPUnit nicht installiert — Tests übersprungen)"
fi

python3 docs/kontrast-pruefung.py > /dev/null && echo "    Kontraste bestanden"

echo "==> Räume altes Paket weg"
rm -rf "$ZIEL"
mkdir -p "$ZIEL"

echo "==> Kopiere Anwendungsdateien"
for teil in app database public resources bin; do
    cp -r "$teil" "$ZIEL/"
done
cp .htaccess "$ZIEL/" 2>/dev/null || true

# Leere Verzeichnisse, die zur Laufzeit gebraucht werden.
#
# storage/medien muss dabei sein, obwohl Medien::verzeichnis() es zur Not
# selbst anlegt: Auf geteiltem Webhosting darf der PHP-Benutzer nicht überall
# Verzeichnisse erzeugen, und der erste Upload scheiterte dann mit
# 'ziel_unbrauchbar' — sichtbar erst, wenn die erste Verkäuferin ihr erstes
# Foto hochlädt. Ein leeres Verzeichnis im Paket kostet nichts.
mkdir -p "$ZIEL/storage/logs" "$ZIEL/storage/uploads" "$ZIEL/storage/medien"
touch "$ZIEL/storage/.htaccess"
cat > "$ZIEL/storage/.htaccess" <<'HTACCESS'
# storage/ enthält Protokolle und hochgeladene Dateien und darf niemals
# über das Web erreichbar sein.
Require all denied
HTACCESS

echo "==> Lege .env-Vorlage bei"
cp .env.example "$ZIEL/.env.BEISPIEL"

cat > "$ZIEL/LIESMICH.txt" <<'ANLEITUNG'
MeinSlip — Hochladen und einrichten
===================================

1. Den GESAMTEN Inhalt dieses Verzeichnisses auf den Server laden.

2. Im KAS-Adminbereich unter "Domains" das Verzeichnis der Domain auf das
   Unterverzeichnis public/ setzen.

   Das ist der wichtigste Schritt. Ohne ihn lägen .env und app/ offen im Netz.

3. Im KAS-Adminbereich eine MySQL-Datenbank anlegen.

4. Die Datei .env.BEISPIEL in .env umbenennen und ausfüllen:
   - DB_NAME, DB_BENUTZER, DB_PASSWORT aus Schritt 3
   - APP_SCHLUESSEL: irgendeine lange zufällige Zeichenfolge
   - EINRICHTUNG_TOKEN: ebenfalls eine lange zufällige Zeichenfolge

5. Im Browser aufrufen:
   https://DEINE-DOMAIN/einrichten?token=DEIN_EINRICHTUNG_TOKEN

   Die Seite prüft alles durch und legt die Datenbanktabellen an.

6. Wenn alles grün ist: EINRICHTUNG_TOKEN in der .env wieder leeren.

7. Verzeichnisschutz einrichten (KAS-Adminbereich, Tools -> Verzeichnisschutz).
   Solange Altersverifikation und Impressum fehlen, darf die Seite nicht
   öffentlich erreichbar sein.

Prüfen, ob alles läuft:  https://DEINE-DOMAIN/zustand
ANLEITUNG

GROESSE=$(du -sh "$ZIEL" | cut -f1)
ANZAHL=$(find "$ZIEL" -type f | wc -l)

echo
echo "==> Fertig: $ZIEL"
echo "    $ANZAHL Dateien, $GROESSE"
echo
echo "    Nächster Schritt: Inhalt von $ZIEL per FTP-Programm hochladen,"
echo "    dann LIESMICH.txt darin befolgen."
