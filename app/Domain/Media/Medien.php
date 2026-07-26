<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Media;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Account\Konten;

/**
 * Die Fachschicht ueber der Bildpipeline: Zuordnung, Eigentum, Sichtbarkeit.
 *
 * Bilder kennt weder Angebot noch Benutzer — es nimmt eine Datei entgegen und
 * gibt zwei Pfade zurueck. Diese Klasse ist die Gegenstueck dazu: Sie weiss,
 * WEM ein Bild gehoert, AN WAS es haengt und WER es sehen darf. Sie dekodiert
 * dafuer keine einzige Bilddatei.
 *
 * Die Tabelle angebot_medien liegt seit database/migrations/004_katalog.php
 * vollstaendig angelegt und vollstaendig ungenutzt im Schema. Diese Klasse ist
 * ihr erster Nutzer; eine Migration ist deshalb nicht noetig.
 *
 * DREI ENTSCHEIDUNGEN, DIE HIER FESTGEHALTEN WERDEN MUESSEN:
 *
 *  1. DIE DATEI WIRD VOR DER ZEILE GESCHRIEBEN. Scheitert danach das INSERT,
 *     wird die Datei wieder entfernt. Andersherum waere es schlimmer: Eine
 *     Zeile ohne Datei liefert bei jedem Abruf eine 404 und ist von aussen
 *     nicht von einem Rechtefehler zu unterscheiden — eine Datei ohne Zeile
 *     kostet nur Speicher und ist ueber die Datenbank auffindbar (jede Datei,
 *     zu der keine Zeile existiert, ist verwaist).
 *
 *  2. DER PFAD IN DER DATENBANK IST RELATIV. Bilder::annehmen() gibt volle
 *     Pfade zurueck; gespeichert wird nur der Teil unterhalb des
 *     Medienwurzelverzeichnisses. Ein Umzug des Servers — anderes
 *     Heimatverzeichnis, anderes Hosting — entwertet die Zeilen sonst
 *     samt und sonders.
 *
 *  3. EXPLIZITE MEDIEN SIND FUER FREMDE NICHT VORHANDEN, NICHT BLOSS
 *     UNSCHARF. Die Begruendung steht bei explizitSichtbar().
 */
final class Medien
{
    /**
     * Bilder je Angebot.
     *
     * Acht, weil das die Zahl ist, die eine Angebotsseite auf einem Telefon
     * noch traegt, ohne dass die Beschreibung aus dem Bild rutscht. Die Grenze
     * ist zugleich die Speicherbremse: Ohne sie kostete ein einziges Angebot
     * beliebig viel Platz, und der Platz ist auf geteiltem Webhosting die
     * knappste Ressource ueberhaupt.
     */
    public const JE_ANGEBOT = 8;

    /**
     * Das Unterverzeichnis je Angebot, relativ zur Medienwurzel.
     *
     * Ein Verzeichnis je Angebot statt eines Topfes fuer alles: Zehntausend
     * Dateien in einem Verzeichnis machen jedes ls und jedes Backup langsam,
     * und beim Entfernen eines Angebots laesst sich so ein ganzer Ordner
     * loeschen statt einer Liste von Namen.
     */
    private const ORDNER = 'angebot';

    /**
     * Die Bildpipeline.
     *
     * Optional im Konstruktor aus genau dem Grund, aus dem Bilder seine
     * Uploadpruefung austauschbar macht: is_uploaded_file() ist ausserhalb
     * einer echten HTTP-Anfrage immer false, ein Test kaeme sonst nie bis zum
     * ersten INSERT. In app/ wird diese Naht nie benutzt — dort steht schlicht
     * new Medien($db, Medien::verzeichnis($wurzel)).
     */
    private readonly Bilder $bilder;

    public function __construct(
        private readonly Database $db,
        /**
         * Das Wurzelverzeichnis der Medien, absolut und OHNE Schlussschraegstrich.
         * Es liegt ausserhalb von public/ — nichts hier ist je direkt vom
         * Webserver ausgeliefert, alles laeuft ueber MedienRouten.
         */
        private readonly string $wurzel,
        ?Bilder $bilder = null,
    ) {
        $this->bilder = $bilder ?? new Bilder();
    }

    /**
     * Das Medienverzeichnis zu einem Projektstamm.
     *
     * An einer Stelle, damit die Zeichenkette 'storage/medien' nicht in jeder
     * Routenklasse einzeln steht — sie steht sonst irgendwann zweimal
     * verschieden da, und die Auslieferung findet die Datei nicht mehr, die
     * der Upload geschrieben hat.
     */
    public static function verzeichnis(string $projektwurzel): string
    {
        return rtrim($projektwurzel, '/') . '/storage/medien';
    }

    // --- Schreiben ---------------------------------------------------------

    /**
     * Nimmt ein Bild an und haengt es an ein eigenes Angebot.
     *
     * $datei ist ein Eintrag aus $_FILES. Geprueft wird hier NUR das, was
     * Bilder nicht wissen kann: Eigentum und Obergrenze. Alles Uebrige — echter
     * Upload, Typ, Bombe, Neukodierung, Vorschau — macht Bilder::annehmen().
     *
     * ABSICHTLICH KEINE STATUSPRUEFUNG ueber 'entfernt' hinaus: Ein Bild ist
     * keine Beschaffenheitsangabe im Sinne von § 312g Abs. 2 Nr. 1 BGB, es
     * illustriert. Wer merkt, dass sein Foto unscharf ist, soll es ersetzen
     * duerfen, ohne das Angebot dafuer erst zu pausieren.
     *
     * @param array<string,mixed> $datei ein Eintrag aus $_FILES
     * @return int die Kennung der neuen Zeile in angebot_medien
     * @throws MedienFehler 'angebot_unbekannt', 'zu_viele_bilder', dazu alle
     *                      Schluessel aus Bilder::annehmen()
     */
    public function hinzufuegen(int $angebotId, int $verkaeuferId, array $datei, bool $explizit): int
    {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);

        if ((int) $this->db->wert(
            'SELECT COUNT(*) FROM angebot_medien WHERE angebot_id = :a',
            ['a' => $angebotId]
        ) >= self::JE_ANGEBOT) {
            throw new MedienFehler('zu_viele_bilder', 'Angebot ' . $angebotId . ' hat bereits ' . self::JE_ANGEBOT . ' Bilder.');
        }

        $ergebnis = $this->bilder->annehmen($datei, $this->angebotsverzeichnis($angebotId));

        // Ab hier liegen zwei Dateien auf der Platte. Jeder weitere Fehler
        // muss sie mitnehmen, sonst bleiben sie fuer immer liegen.
        try {
            return $this->db->einfuegen('angebot_medien', [
                'angebot_id' => (int) $angebot['id'],
                'pfad' => $this->relativ($ergebnis['pfad']),
                'vorschau_pfad' => $this->relativ($ergebnis['vorschau_pfad']),
                'art' => $ergebnis['art'],
                'explizit' => $explizit ? 1 : 0,
                'reihenfolge' => $this->naechsteReihenfolge($angebotId),
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $fehler) {
            @unlink($ergebnis['pfad']);
            @unlink($ergebnis['vorschau_pfad']);

            throw $fehler;
        }
    }

    /**
     * Entfernt ein eigenes Bild.
     *
     * Erst die Zeile, dann die Dateien — dieselbe Rangfolge wie beim Anlegen,
     * nur andersherum gelesen: Nach dem DELETE ist das Bild nicht mehr
     * abrufbar, auch wenn das unlink() scheitert. Waere es umgekehrt, bliebe
     * bei einem Fehler eine Zeile ohne Datei stehen und die Angebotsseite
     * zeigte eine kaputte Kachel.
     *
     * Unbekannte und fremde Kennung sind DERSELBE Fall und tragen denselben
     * Schluessel. Sonst waere die fortlaufende Kennung durchzaehlbar: Zwei
     * verschiedene Antworten verraten, welche Kennungen es gibt.
     *
     * @throws MedienFehler 'medium_unbekannt'
     */
    public function entfernen(int $medienId, int $verkaeuferId): void
    {
        $zeile = $this->mitAngebot($medienId);

        if ($zeile === null || $zeile['verkaeufer_id'] !== $verkaeuferId) {
            throw new MedienFehler('medium_unbekannt', 'Medium ' . $medienId . ' gibt es nicht oder es gehoert jemand anderem.');
        }

        $this->db->ausfuehren('DELETE FROM angebot_medien WHERE id = :id', ['id' => $medienId]);

        foreach ([$zeile['pfad'], $zeile['vorschau_pfad']] as $relativ) {
            $voll = $this->datei(is_string($relativ) ? $relativ : null);

            if ($voll !== null) {
                @unlink($voll);
            }
        }
    }

    // --- Lesen -------------------------------------------------------------

    /**
     * Alle Bilder eines Angebots, in der Reihenfolge des Angebots.
     *
     * Ohne jede Sichtbarkeitspruefung: Die Zuordnung ist eine Tatsache, die
     * Berechtigung eine Frage der Anfrage. Wer aufruft, filtert selbst — die
     * Oberflaeche ueber explizitSichtbar(), die Auslieferung ueber ihre eigene
     * Pruefung am gebundenen Angebot.
     *
     * @return list<array<string,mixed>>
     */
    public function zuAngebot(int $angebotId): array
    {
        $zeilen = $this->db->alle(
            'SELECT id, angebot_id, pfad, vorschau_pfad, art, explizit, reihenfolge, angelegt_am
               FROM angebot_medien
              WHERE angebot_id = :a
              ORDER BY reihenfolge, id',
            ['a' => $angebotId]
        );

        return array_values(array_map(fn (array $zeile): array => $this->zeileFormen($zeile), $zeilen));
    }

    /**
     * Das eine Bild fuer die Katalogkachel — oder null.
     *
     * Sortiert wird explizit AUFSTEIGEND und erst danach nach Reihenfolge:
     * Traegt ein Angebot ein harmloses und ein explizites Bild, gewinnt das
     * harmlose, gleich in welcher Reihenfolge sie hochgeladen wurden. Sonst
     * zeigte die Kachel ein Schloss, obwohl daneben ein zeigbares Bild liegt.
     *
     * Gibt es NUR explizite Bilder, kommt eines davon zurueck — die Kachel
     * zeigt dann das Schloss, und das ist die richtige Auskunft: Das Angebot
     * hat Bilder, nur nicht fuer dich.
     *
     * @return array<string,mixed>|null
     */
    public function ersteVorschau(int $angebotId): ?array
    {
        $zeile = $this->db->eine(
            'SELECT id, angebot_id, pfad, vorschau_pfad, art, explizit, reihenfolge, angelegt_am
               FROM angebot_medien
              WHERE angebot_id = :a
              ORDER BY explizit, reihenfolge, id
              LIMIT 1',
            ['a' => $angebotId]
        );

        return $zeile === null ? null : $this->zeileFormen($zeile);
    }

    /**
     * Dieselbe Auswahl wie ersteVorschau(), aber fuer eine ganze Katalogseite.
     *
     * EINE Abfrage fuer alle Angebote der Seite statt einer je Kachel. Die
     * Katalogseite zeigt PRO_SEITE Angebote; ersteVorschau() in einer Schleife
     * waeren ebenso viele Rundgaenge zur Datenbank, und zwar auf der Seite, die
     * am haeufigsten aufgerufen wird.
     *
     * Die Auswahlregel steht trotzdem nur einmal: Sortiert wird hier woertlich
     * wie dort (explizit, reihenfolge, id), und welche Zeile je Angebot gewinnt,
     * entscheidet danach das erste Vorkommen. Kein GROUP BY und keine
     * Fensterfunktion — beides verhaelt sich zwischen MySQL und SQLite
     * unterschiedlich genug, dass die Kachel je nach Treiber ein anderes Bild
     * zeigen koennte.
     *
     * @param  list<int>                     $angebotIds
     * @return array<int,array<string,mixed>> Angebotskennung => Medienzeile
     */
    public function ersteVorschauZuAngeboten(array $angebotIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $angebotIds), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        // Platzhalter statt Einsetzen: Die Werte sind zwar schon durch intval
        // gegangen, aber eine Abfrage, die Werte in den Text schreibt, ist eine
        // Vorlage fuer die naechste, bei der das nicht mehr stimmt.
        $platzhalter = [];
        $werte = [];

        foreach ($ids as $nummer => $id) {
            $platzhalter[] = ':a' . $nummer;
            $werte['a' . $nummer] = $id;
        }

        $zeilen = $this->db->alle(
            'SELECT id, angebot_id, pfad, vorschau_pfad, art, explizit, reihenfolge, angelegt_am
               FROM angebot_medien
              WHERE angebot_id IN (' . implode(', ', $platzhalter) . ')
              ORDER BY explizit, reihenfolge, id',
            $werte
        );

        $vorschauen = [];

        foreach ($zeilen as $zeile) {
            $angebotId = (int) $zeile['angebot_id'];

            if (!isset($vorschauen[$angebotId])) {
                $vorschauen[$angebotId] = $this->zeileFormen($zeile);
            }
        }

        return $vorschauen;
    }

    /**
     * Eine Medienzeile samt der beiden Angaben des gebundenen Angebots.
     *
     * Eine Abfrage statt zweier, weil die Auslieferung beides immer zusammen
     * braucht: die Zeile fuer den Pfad, das Angebot fuer die Berechtigung.
     *
     * @return array<string,mixed>|null
     */
    public function mitAngebot(int $medienId): ?array
    {
        $zeile = $this->db->eine(
            'SELECT m.id, m.angebot_id, m.pfad, m.vorschau_pfad, m.art, m.explizit,
                    m.reihenfolge, m.angelegt_am,
                    a.verkaeufer_id AS verkaeufer_id, a.status AS angebot_status
               FROM angebot_medien m
               JOIN angebote a ON a.id = m.angebot_id
              WHERE m.id = :id',
            ['id' => $medienId]
        );

        if ($zeile === null) {
            return null;
        }

        $geformt = $this->zeileFormen($zeile);
        $geformt['verkaeufer_id'] = (int) $zeile['verkaeufer_id'];
        $geformt['angebot_status'] = (string) $zeile['angebot_status'];

        return $geformt;
    }

    // --- Sichtbarkeit ------------------------------------------------------

    /**
     * Darf diese Person die expliziten Bilder dieses Angebots sehen?
     *
     * WARUM EXPLIZITE MEDIEN FUER FREMDE UEBERHAUPT NICHT AUSGELIEFERT WERDEN,
     * auch nicht als unscharfe Vorschau:
     *
     * Eine Selbsterklaerung — "ich bin ueber 18" — ist keine geschlossene
     * Benutzergruppe nach § 4 Abs. 2 JMStV. Sie ist ueberhaupt keine Schranke,
     * sondern eine Schaltflaeche. Solange kein lizenziertes
     * Altersverifikationsverfahren gebunden ist, gibt es also nur zwei ehrliche
     * Moeglichkeiten: ausliefern und so tun, als sei das Kennzeichen 'explizit'
     * eine Massnahme, oder nicht ausliefern. Nichtausliefern ist die einzige
     * verteidigbare Antwort.
     *
     * Der Nebeneffekt ist der eigentliche Gewinn: Das Kennzeichen tut ab Tag
     * eins echte Arbeit. Wer es setzt, merkt sofort, dass sein Bild niemand
     * sieht — es ist keine Selbstauskunft ins Leere, sondern eine Entscheidung
     * mit sichtbarer Folge. Und die unscharfe Vorschau bleibt fuer den Tag
     * aufgehoben, an dem es eine echte Schranke gibt, statt heute den Eindruck
     * zu erwecken, es gaebe schon eine.
     *
     * Sehen duerfen deshalb genau zwei: die Hochladende (sie muss pruefen
     * koennen, was sie eingestellt hat) und die Verwaltung (sie muss eine
     * Meldung bearbeiten koennen, ohne dass ihr der Gegenstand vorenthalten
     * wird).
     */
    public function explizitSichtbar(int $angebotId, int $betrachterId): bool
    {
        if ($betrachterId <= 0) {
            return false;
        }

        $angebot = $this->db->eine(
            'SELECT verkaeufer_id FROM angebote WHERE id = :id',
            ['id' => $angebotId]
        );

        if ($angebot === null) {
            return false;
        }

        if ((int) $angebot['verkaeufer_id'] === $betrachterId) {
            return true;
        }

        return (new Konten($this->db))->hatFaehigkeit($betrachterId, Konten::FAEHIGKEIT_VERWALTEN);
    }

    /**
     * Ist ein lizenziertes Altersverifikationsverfahren angebunden?
     *
     * Heute: nein, und zwar hart. Die .env kennt zwar den Schluessel
     * AV_ANBIETER, aber ein eingetragener Name ist eine Absichtserklaerung und
     * keine Anbindung — es gibt keine Zeile Code, die irgendetwas bei einem
     * Anbieter erfragte. Wuerde diese Methode den Env-Wert lesen, oeffnete ein
     * Tippfehler in einer Konfigurationsdatei die expliziten Medien fuer alle.
     *
     * Diese Methode ist die eine Stelle, an der sich das aendert: Wer ein
     * Verfahren anbindet, ersetzt hier das false durch die echte Pruefung des
     * Verifikationsnachweises der Sitzung — und muss dann auch explizitSichtbar()
     * um diesen Zweig erweitern. Bis dahin ist sie Dokumentation mit
     * Rueckgabewert, absichtlich greppbar.
     */
    public static function altersschrankeGebunden(): bool
    {
        return false;
    }

    // --- Dateien -----------------------------------------------------------

    /**
     * Loest einen gespeicherten relativen Pfad in einen absoluten auf.
     *
     * GUERTEL UND HOSENTRAEGER. Der relative Pfad stammt aus der Datenbank und
     * ist dort ausschliesslich von relativ() hineingeschrieben worden — er kann
     * also strukturell kein '..' enthalten. Trotzdem wird das Ergebnis gegen
     * das Medienverzeichnis geprueft: Gegen eine von Hand veraenderte oder aus
     * einer Sicherung falsch eingespielte Zeile hilft nur diese Pruefung, und
     * sie kostet einen realpath()-Aufruf.
     *
     * realpath() loest dabei auch Verweise auf: Ein symbolischer Link im
     * Medienverzeichnis, der nach /etc zeigt, faellt hier durch — ein
     * Zeichenkettenvergleich auf dem ungeloesten Pfad taete das nicht.
     */
    public function datei(?string $relativ): ?string
    {
        if ($relativ === null || $relativ === '') {
            return null;
        }

        $wurzel = realpath($this->wurzel);
        $voll = realpath($this->wurzel . '/' . $relativ);

        if ($wurzel === false || $voll === false || !is_file($voll)) {
            return null;
        }

        return str_starts_with($voll, $wurzel . DIRECTORY_SEPARATOR) ? $voll : null;
    }

    // --- Innereien ---------------------------------------------------------

    /**
     * Das Angebot, wenn es der Person gehoert.
     *
     * @return array<string,mixed>
     * @throws MedienFehler 'angebot_unbekannt'
     */
    private function eigenesAngebot(int $angebotId, int $verkaeuferId): array
    {
        $angebot = $this->db->eine(
            'SELECT id, verkaeufer_id, status FROM angebote WHERE id = :id',
            ['id' => $angebotId]
        );

        // Fremd, unbekannt und entfernt sind derselbe Fall — dieselbe
        // Zusicherung wie in MarktRouten::bearbeitenFormular(): Wer das Angebot
        // nicht bearbeiten darf, soll nicht erfahren, ob es die Kennung gibt.
        if ($angebot === null
            || (int) $angebot['verkaeufer_id'] !== $verkaeuferId
            || (string) $angebot['status'] === 'entfernt') {
            throw new MedienFehler('angebot_unbekannt', 'Angebot ' . $angebotId . ' gibt es nicht oder es gehoert jemand anderem.');
        }

        return $angebot;
    }

    /** Das Ablageverzeichnis eines Angebots, absolut. */
    private function angebotsverzeichnis(int $angebotId): string
    {
        return $this->wurzel . '/' . self::ORDNER . '/' . $angebotId;
    }

    /**
     * Schneidet das Medienverzeichnis vom vollen Pfad ab.
     *
     * Bleibt der Pfad unveraendert, lag er nicht unterhalb der Wurzel — dann
     * wird der volle Pfad gespeichert, und datei() weist ihn spaeter ab. Das
     * ist der laute Ausgang: lieber ein Bild, das nicht erscheint, als eine
     * Zeile, die auf etwas ausserhalb des Medienverzeichnisses zeigt.
     */
    private function relativ(string $voll): string
    {
        $praefix = $this->wurzel . '/';

        return str_starts_with($voll, $praefix) ? substr($voll, strlen($praefix)) : $voll;
    }

    /** Die naechste freie Reihenfolge; MAX() ueber null Zeilen ist NULL. */
    private function naechsteReihenfolge(int $angebotId): int
    {
        $hoechste = $this->db->wert(
            'SELECT MAX(reihenfolge) FROM angebot_medien WHERE angebot_id = :a',
            ['a' => $angebotId]
        );

        return $hoechste === null ? 0 : (int) $hoechste + 1;
    }

    /**
     * Vereinheitlicht eine Zeile: Zahlen als int, 'explizit' als bool.
     *
     * SQLite liefert Ganzzahlen als int, MySQL ueber PDO als Zeichenkette. Ohne
     * diese Stelle stuende in der Vorlage einmal 1 und einmal '1', und ein
     * Vergleich mit === waere je nach Treiber wahr oder falsch.
     *
     * @param array<string,mixed> $zeile
     * @return array<string,mixed>
     */
    private function zeileFormen(array $zeile): array
    {
        return [
            'id' => (int) $zeile['id'],
            'angebot_id' => (int) $zeile['angebot_id'],
            'pfad' => (string) $zeile['pfad'],
            'vorschau_pfad' => $zeile['vorschau_pfad'] === null ? null : (string) $zeile['vorschau_pfad'],
            'art' => (string) $zeile['art'],
            'explizit' => (int) $zeile['explizit'] === 1,
            'reihenfolge' => (int) $zeile['reihenfolge'],
            'angelegt_am' => $zeile['angelegt_am'],
        ];
    }
}
