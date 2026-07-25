# Deployment

> **Vor dem ersten Deployment lesen.** Zwei Punkte sind nicht verhandelbar.

## Zuerst: Zugangsdaten neu setzen

Zugangsdaten, die jemals in einem Chat, einer E-Mail oder einem Ticket standen, sind verbrannt. Sie sind in Verläufen, Protokollen und Sicherungen gespeichert, auf die niemand mehr Zugriff hat.

**Im KAS-Adminbereich von All-Inkl neu setzen:**

1. Datenbank-Passwort
2. FTP-/SFTP-Passwort
3. Danach die neuen Werte ausschließlich in `.env` und `deployment/.deploy.env` eintragen — beide Dateien sind in `.gitignore` erfasst

Besser als ein Passwort: einen SSH-Schlüssel hinterlegen. Dann muss gar kein Passwort gespeichert werden.

```
ssh-keygen -t ed25519 -C "deployment meinslip"
ssh-copy-id -p 22 BENUTZER@HOST
```

## Zweitens: kein FTP auf Port 21

All-Inkl bietet beides an. Auf Port 21 gehen Passwort und sämtliche Dateien **im Klartext** über die Leitung. Das Deployment-Skript weigert sich, Port 21 zu verwenden.

SFTP läuft über Port 22.

---

## Einrichtung auf dem Server

### 1. Dokumentenstammverzeichnis auf `public/` zeigen lassen

Im KAS-Adminbereich unter *Domains* das Verzeichnis der Domain auf das Unterverzeichnis `public/` setzen.

**Das ist der wichtigste Schritt.** Ohne ihn lägen `.env`, `app/` und `storage/` offen im Netz. Die `.htaccess` im Projektstamm fängt das ab, aber sie ist die Absicherung, nicht die Lösung.

### 2. Passwortschutz einrichten

Solange Altersverifikation, Impressum und Zahlungsabwicklung nicht stehen, darf die Seite nicht öffentlich erreichbar sein. Ein öffentlich zugänglicher Erotik-Marktplatz ohne KJM-konforme Altersprüfung ist genau das, was laut Recherche Plattformen tötet — Aufsichtsverfahren und Netzsperre.

Im KAS-Adminbereich unter *Tools → Verzeichnisschutz* für das Verzeichnis einrichten. Alternativ von Hand:

```
htpasswd -c /pfad/ausserhalb/webroot/.htpasswd deinname
```

Und in `public/.htaccess` ergänzen:

```apache
AuthType Basic
AuthName "Nicht oeffentlich"
AuthUserFile /pfad/ausserhalb/webroot/.htpasswd
Require valid-user
```

Die Datei `.htpasswd` gehört **außerhalb** des Webverzeichnisses.

### 3. PHP-Version prüfen

Mindestens PHP 8.2. Im KAS-Adminbereich einstellbar.

### 4. Datenbank anlegen

Im KAS-Adminbereich eine MySQL-Datenbank anlegen und die Werte in `.env` eintragen:

```
DB_TREIBER=mysql
DB_HOST=localhost
DB_NAME=...
DB_BENUTZER=...
DB_PASSWORT=...
```

### 5. `.env` auf dem Server anlegen

`.env` wird **nicht** übertragen — sie lebt auf dem Server. Einmalig von Hand anlegen, Vorlage ist `.env.example`.

Schlüssel erzeugen:

```
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### 6. Schreibrechte

```
chmod -R 775 storage
```

---

## Deployment ausführen

```
cp deployment/deploy.env.example deployment/.deploy.env
# Werte eintragen, dann:
./deployment/deploy.sh
```

Das Skript bricht ab, wenn Tests rot sind, die Kontrastprüfung fehlschlägt, eine `.env` versioniert ist oder Port 21 konfiguriert wurde.

---

## Cronjobs

Im KAS-Adminbereich unter *Tools → Cronjobs* einrichten:

| Takt | Befehl | Zweck |
| :--- | :--- | :--- |
| alle 5 Minuten | `php /pfad/bin/fristen` | Fällige Treuhandbeträge freigeben, abgelaufene Annahmefristen erstatten |
| stündlich | `php /pfad/bin/hauptbuch-pruefen` | Hauptbuch auf Ausgeglichenheit prüfen |

Die stündliche Prüfung ist wichtiger, als sie klingt: Eine Abweichung im Hauptbuch bedeutet einen Fehler in der Geldlogik und muss laut auffallen, nicht still bleiben.

---

## Nach dem Deployment prüfen

```
curl https://meinslip.de/zustand
```

Erwartet wird:

```json
{"anwendung":"ok","datenbank":"ok","hauptbuch":"ausgeglichen","hauptbuch_abweichung_cent":0}
```

Bei `hauptbuch: ABWEICHUNG` sofort nachsehen — dann stimmt die Geldlogik nicht.

---

## Was noch nicht live gehen darf

Unabhängig vom Passwortschutz: Bevor die Seite öffentlich wird, müssen die Punkte in [`../docs/11-offene-fragen.md`](../docs/11-offene-fragen.md) geklärt sein. Die wichtigsten sind Altersverifikation, Zahlungsdienstleister und die aufsichtsrechtliche Einordnung des Guthabens.

Die Guthabenfunktion ist im Code gesperrt und lässt sich nur mit `ZAHLUNG_GUTHABEN_AKTIV=true` freischalten — bewusst, damit das eine dokumentierte Entscheidung bleibt und kein Versehen.
