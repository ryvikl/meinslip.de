# Deployment

## Der bequemste Weg: aus Git heraus

Ein Push auf `main` deployt automatisch — aber erst, wenn alle Tests grün sind. Zusätzlich lässt sich das Deployment jederzeit von Hand auslösen unter *Actions → Deployen → Run workflow*.

Zwei Workflows:

| Datei | Läuft wann | Was |
| :--- | :--- | :--- |
| `.github/workflows/pruefen.yml` | jeder Push, jeder Pull Request | Tests auf PHP 8.2 **und** 8.4, Kontrastprüfung, Syntaxprüfung, Verweisprüfung, Suche nach versehentlich versionierten Zugangsdaten |
| `.github/workflows/deployen.yml` | Push auf `main`, oder auf Knopfdruck | Paket bauen → FTPS-Upload → Migrationen → Betriebsprüfung |

**Rote Tests blockieren das Deployment.** Der Deploy-Job wartet über `needs` auf die Prüfungen. Ohne diese Kopplung wäre die Automatik gefährlicher als Handarbeit.

### Schritt 1: SSH-Schlüssel erzeugen

Auf dem eigenen Rechner. Unter Windows in PowerShell:

```powershell
mkdir -Force "$env:USERPROFILE\.ssh" | Out-Null
ssh-keygen -t ed25519 -C "github-actions meinslip" -f "$env:USERPROFILE\.ssh\meinslip_deploy"
```

Unter macOS und Linux:

```bash
ssh-keygen -t ed25519 -C "github-actions meinslip" -f ~/.ssh/meinslip_deploy -N ""
```

> **Bei der Passwortabfrage zweimal Enter drücken — das Passwort muss leer bleiben.** Ein passwortgeschützter Schlüssel lässt sich in einer Action nicht ohne Weiteres verwenden.
>
> Unter Windows funktioniert `-N ""` nicht, weil PowerShell die leere Zeichenkette verwirft. Deshalb steht der Parameter dort nicht in der Zeile.

Es entstehen zwei Dateien:

| Datei | Wohin |
| :--- | :--- |
| `meinslip_deploy.pub` (**öffentlich**) | KAS-Adminbereich → *SSH-Zugriff* → Feld *SSH-Schlüssel* |
| `meinslip_deploy` (**privat**) | GitHub-Geheimnis `SSH_SCHLUESSEL`, vollständig inklusive `BEGIN`- und `END`-Zeile |

Der private Schlüssel gehört ausschließlich in das GitHub-Geheimnis — nicht in eine E-Mail, nicht in einen Chat, nicht ins Repository.

### Schritt 2: Geheimnisse anlegen

Unter *Settings → Secrets and variables → Actions → New repository secret*:

| Name | Inhalt | Wo zu finden |
| :--- | :--- | :--- |
| `SSH_HOST` | z. B. `w0xxxxxx.kasserver.com` | KAS → SSH-Zugriff → *Host Name* |
| `SSH_BENUTZER` | z. B. `ssh-w0xxxxxx` | KAS → SSH-Zugriff → *SSH-Login*. **Nicht** derselbe Name wie der FTP-Benutzer |
| `SSH_SCHLUESSEL` | privater Schlüssel aus Schritt 1 | |
| `SSH_FINGERPRINT` | `SHA256:…` | KAS → SSH-Zugriff → *Fingerprints* → **ED25519**, die SHA256-Zeile, mit `SHA256:` davor |
| `SSH_ZIEL` | Zielverzeichnis, z. B. `/www/htdocs/wXXXXXXX/meinslip` | |
| `SEITE_URL` | `https://meinslip.de` (ohne Schrägstrich am Ende) | |
| `SCHUTZ_BENUTZER` | Benutzername des Verzeichnisschutzes | leer lassen, wenn keiner eingerichtet ist |
| `SCHUTZ_PASSWORT` | Passwort des Verzeichnisschutzes | dito |

> **Die beiden letzten sind der häufigste Stolperstein.** Solange die Seite passwortgeschützt ist, antwortet sie **jeder** Anfrage mit HTTP 401 — auch der Action. Ohne diese Geheimnisse schlägt die Betriebsprüfung fehl, und im Protokoll steht nur ein nichtssagender Fehler.

`SSH_FINGERPRINT` ist keine Formalität: Ohne ihn nimmt die Action den Serverschlüssel blind an und wäre gegen einen zwischengeschalteten Angreifer ungeschützt. Fehlt das Geheimnis, warnt der Workflow und läuft weiter — er bricht nur ab, wenn der Fingerprint gesetzt ist und **nicht passt**.

### Was das Deployment niemals anfasst

`.env` und `storage/` stehen in der Ausschlussliste von rsync. Die `.env` lebt auf dem Server und enthält die Datenbankzugänge — würde sie überschrieben oder durch `--delete` entfernt, wäre die Seite sofort tot.

### `DEPLOY_TOKEN` bleibt leer

Weil die Migrationen über SSH laufen, wird die Route `/deploy/migrieren` nicht gebraucht. Bleibt `DEPLOY_TOKEN` in der `.env` leer, ist sie vollständig gesperrt — ein Endpunkt weniger im Netz. Sie existiert weiterhin als Rückfallebene für den Fall, dass SSH einmal nicht verfügbar ist.

### Schritt 3: Erstes Deployment

**Zuerst von Hand auslösen, nicht durch einen Push.** Unter *Actions → Deployen → Run workflow*. So siehst du im Protokoll, ob alle Geheimnisse stimmen, bevor die Automatik greift.

### Schritt 4: Prüfen, dass die Absicherung wirklich hält

Einen Test absichtlich brechen, pushen, und nachsehen: Das Deployment darf **nicht** starten. Danach zurücknehmen. Ohne diese Gegenprobe weiß niemand, ob die Kopplung zwischen Tests und Deployment tatsächlich greift.

---

## Der manuelle Weg — ohne SSH, ohne Composer

Die Anwendung hat **produktiv keine externen Abhängigkeiten**. Der eigene Klassenlader in `app/Core/Autoloader.php` ersetzt Composer, deshalb muss kein `vendor/`-Verzeichnis auf den Server. Damit reduziert sich das Deployment auf fünf Schritte:

```
./deployment/paket-bauen.sh
```

Das erzeugt `deployment/upload/` — 42 Dateien, rund 340 KB. Dann:

1. **Inhalt von `deployment/upload/` per FTP-Programm hochladen** (FileZilla, Cyberduck)
2. **Im KAS-Adminbereich unter *Domains*** das Verzeichnis der Domain auf das Unterverzeichnis **`public/`** setzen — der wichtigste Schritt, siehe unten
3. **MySQL-Datenbank anlegen** (KAS-Adminbereich)
4. **`.env.BEISPIEL` in `.env` umbenennen** und ausfüllen: Datenbankdaten, `APP_SCHLUESSEL`, `EINRICHTUNG_TOKEN`
5. **Im Browser aufrufen:** `https://deine-domain/einrichten?token=DEIN_TOKEN`

Die Einrichtungsseite prüft PHP-Version, Datenbankverbindung, Schreibrechte und ob die `.env` versehentlich im öffentlichen Verzeichnis liegt — und legt die Tabellen an. Danach `EINRICHTUNG_TOKEN` wieder leeren.

Prüfen, ob alles läuft: `https://deine-domain/zustand`

Für Deployments per SSH und rsync gibt es zusätzlich `deploy.sh` — der Weg oben ist aber der einfachere.

---

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
