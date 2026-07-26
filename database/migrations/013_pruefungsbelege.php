<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Der Pruefbeleg: ein Selfie mit handgeschriebenem Zettel, und ein Loeschdatum,
 * das keine Zeile jemals auslassen kann.
 *
 * WARUM EINE EIGENE TABELLE UND NICHT ZWEI SPALTEN AUF 'pruefungen'.
 *
 * 'pruefungen' (001_konten.php) traegt ausschliesslich das ERGEBNIS einer
 * Pruefung — bestanden ja/nein, volljaehrig ja/nein, ein Referenzschluessel.
 * docs/04-features/verifizierung-altersstufen.md sagt an Zeile 42 bis 44
 * ausdruecklich: niemals Ausweisdokumente, die Tabelle hat kein Feld dafuer.
 * Ein Bildpfad auf 'pruefungen' waere genau dieses Feld, nur anders benannt.
 * Das Ergebnis ist dauerhaft, der Beleg ist fluechtig; zwei verschiedene
 * Lebensdauern gehoeren nicht in dieselbe Zeile.
 *
 * DIE EINE SPALTE, AUF DIE ES ANKOMMT: loeschen_ab NOT NULL.
 *
 * Ddl::zeitpunkt($name, false) erzeugt DATETIME NOT NULL beziehungsweise
 * TEXT NOT NULL — ohne Vorgabewert. Ein INSERT ohne Loeschdatum scheitert
 * damit auf beiden Datenbanken, und zwar in der Datenbank und nicht in einer
 * Pruefung, die jemand vergessen kann. Die Datenminimierung nach Art. 5 Abs. 1
 * lit. c DSGVO ist damit eine Schemazusage und keine Gewohnheit: Es gibt keinen
 * Weg, einen Beleg anzulegen, der nicht von selbst wieder verschwindet.
 *
 * Die Frist selbst steht im Code (Pruefbelege::FRIST_UNBEARBEITET_TAGE und
 * FRIST_ENTSCHIEDEN_TAGE) und nicht als Vorgabewert im Schema. Ein DEFAULT
 * koennte nur den einen Fall abbilden; die Regel lautet aber "hoechstens 30
 * Tage unbearbeitet, 7 Tage nach der Entscheidung, das jeweils Fruehere", und
 * das ist eine Rechnung, keine Konstante. Das Schema erzwingt, DASS ein Datum
 * dasteht — welches, entscheidet die Fachschicht.
 *
 * ZWEITE ENTSCHEIDUNG: DER CODE STEHT IM KLARTEXT.
 *
 * Ueberall sonst in diesem Projekt wird gehasht, was zum Nachweis dient
 * (sitzungen.kennung, konto_signale.wert_hash). Hier nicht, und der Grund ist
 * kein Versehen: Den Code vergleicht ein MENSCH mit dem, was auf dem Zettel im
 * Foto steht. Ein Hash liesse sich damit nicht vergleichen, und ein Vergleich
 * in der Anwendung gibt es nicht — die Ziffern kommen nicht getippt an,
 * sondern als Bildpunkte. Der Code ist ausserdem 48 Stunden gueltig und danach
 * wertlos; er ist ein Einmalwort, kein Geheimnis auf Dauer.
 *
 * Die Migration legt nur Schema an. Sie ist gegen einen Abbruch mitten in
 * hoch() abgesichert: Migrationen laufen ohne Transaktionsklammer
 * (app/Core/Migrator.php), ein Abbruch hinterlaesst keinen Eintrag in
 * 'migrationen' und der naechste Lauf startet diese Datei von vorn.
 * Ddl::tabelle() bringt IF NOT EXISTS mit; CREATE INDEX kennt es auf MySQL
 * nicht, deshalb steht jeder Index hinter Ddl::indexVorhanden().
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Pruefungsbelege mit erzwungenem Loeschdatum';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('pruefungsbelege', [
            $d->id(),

            // Wem der Beleg gehoert. CASCADE, weil ein Beleg ohne Konto
            // niemandem gehoert und niemand ihn je wieder zuordnen koennte.
            // Die Dateien haengen NICHT an diesem Fremdschluessel — sie liegen
            // im Dateisystem. Deshalb raeumt bin/pflege zusaetzlich
            // verwaiste Verzeichnisse weg (Pruefbelege::verwaisteEntfernen()):
            // Ein CASCADE, das die Zeile mitnimmt und die Datei liegen laesst,
            // waere das Gegenteil einer Loeschzusage.
            $d->fremdschluessel('benutzer_id'),

            // Der plattformseitig vergebene Code, in der Form 'ABCD-EFGH'.
            // Gespeichert wird genau das, was die Person auf den Zettel
            // schreiben soll — Bindestrich eingeschlossen. Ein anders
            // formatierter Vergleichswert waere eine Fehlerquelle an der einen
            // Stelle, an der ein Mensch zwei Zeichenketten nebeneinanderlegt.
            $d->text('code', 20),
            $d->zeitpunkt('code_ausgegeben_am', false),
            $d->zeitpunkt('code_gueltig_bis', false),

            // offen | eingereicht | freigegeben | abgelehnt
            $d->schluesselwort('status'),

            // Bild und Vorschau, beide relativ zum Belegverzeichnis. NULL,
            // solange nur der Code vergeben ist: Ein Vorgang ohne Foto ist der
            // Normalfall der ersten 48 Stunden.
            $d->text('pfad', 255, true),
            $d->text('vorschau_pfad', 255, true),
            $d->zeitpunkt('eingereicht_am'),

            // Die Entscheidung. verwalter_id ist SET NULL statt CASCADE: Dass
            // entschieden wurde, bleibt wahr, auch wenn das entscheidende
            // Konto spaeter verschwindet. Der belastbare Nachweis liegt
            // ohnehin in verwaltungs_ereignisse.
            $d->zeitpunkt('entschieden_am'),
            $d->fremdschluessel('verwalter_id', true),
            $d->langtext('entscheidung'),

            // Verweis auf die Ergebniszeile in 'pruefungen'. Sie ueberlebt den
            // Beleg um Jahre — deshalb SET NULL: Verschwindet der Beleg nach
            // Frist, bleibt das Ergebnis stehen, und andersherum darf ein
            // geloeschtes Ergebnis den Beleg nicht mitreissen.
            $d->fremdschluessel('pruefung_id', true),

            // DIE ZUSAGE. Ohne Vorgabewert und ohne NULL: Es gibt keinen
            // INSERT, der ein Loeschdatum auslassen koennte.
            $d->zeitpunkt('loeschen_ab', false),

            $d->angelegtAm(),

            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
            $d->fremdschluesselBedingung('verwalter_id', 'benutzer', 'id', 'SET NULL'),
            $d->fremdschluesselBedingung('pruefung_id', 'pruefungen', 'id', 'SET NULL'),
        ]));

        // Eindeutig, damit zwei gleichzeitig laufende Vorgaenge nie denselben
        // Zettel beschreiben lassen. Pruefbelege::codeVergeben() erzeugt aus
        // random_bytes und wiederholt bei Kollision — ohne diesen Index waere
        // die Wiederholung eine Hoeflichkeit statt einer Zusicherung.
        $this->index($db, $d, 'pruefungsbelege', ['code'], true);

        // Der Blick der betroffenen Person: 'habe ich einen laufenden Vorgang?'
        $this->index($db, $d, 'pruefungsbelege', ['benutzer_id', 'status']);

        // Die Arbeitsliste der Verwaltung: status = 'eingereicht', aelteste
        // zuerst. Spaltenreihenfolge wie bei idx_angebote_status_angelegt_am
        // aus 009_nachbesserung.php — Gleichheitsfilter zuerst, Sortierung
        // danach.
        $this->index($db, $d, 'pruefungsbelege', ['status', 'angelegt_am']);

        // Der Index, an dem die Loeschzusage haengt. bin/pflege und die
        // opportunistische Raeumung fragen ausschliesslich
        // 'WHERE loeschen_ab <= :jetzt' — ohne ihn kostet jede Raeumung einen
        // vollstaendigen Durchlauf, und zwar bei JEDEM Aufruf der Pruefseite.
        $this->index($db, $d, 'pruefungsbelege', ['loeschen_ab']);
    }

    /**
     * Legt einen Index an, wenn er noch fehlt.
     *
     * MySQL kennt bei CREATE INDEX kein IF NOT EXISTS (Ddl::index setzt es nur
     * bei SQLite). Weil eine abgebrochene Migration beim naechsten Lauf von
     * vorn beginnt, wuerde ein blindes CREATE INDEX dort mit "1061 Duplicate
     * key name" scheitern — und zwar bei jedem weiteren Versuch erneut.
     *
     * @param list<string> $spalten
     */
    private function index(Database $db, Ddl $d, string $tabelle, array $spalten, bool $eindeutig = false): void
    {
        if ($d->indexVorhanden($tabelle, $d->indexName($tabelle, $spalten))) {
            return;
        }

        $db->ddl($d->index($tabelle, $spalten, $eindeutig));
    }
};
