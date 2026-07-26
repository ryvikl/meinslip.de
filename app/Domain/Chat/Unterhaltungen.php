<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Chat;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Admin\VerwaltungsFehler;

/**
 * Der Chat: Unterhaltungen zwischen genau zwei Konten und die Nachrichten
 * darin.
 *
 * "Nachricht schreiben" ist nach dem Modellwechsel die Hauptaktion der
 * Angebotsseite. Damit ist diese Klasse der meistbenutzte Schreibweg der
 * Plattform — und der einzige, ueber den zwei Menschen einander unaufgefordert
 * erreichen. Fuenf Zusicherungen entstehen hier und nirgends sonst:
 *
 *  1. DIE SPERRE GILT IN BEIDEN RICHTUNGEN, UND ZWAR BEI JEDEM SENDEN. Hat A
 *     die Person B gesperrt, darf auch B nicht an A schreiben. Eine Sperre,
 *     die nur eine Richtung schliesst, ist keine: Wer belaestigt wird, sperrt,
 *     und die belaestigende Person schriebe weiter. Und die Pruefung sitzt
 *     nicht nur im Eroeffnen, sondern in jedem senden() — sonst bliebe eine
 *     vor der Sperre eroeffnete Unterhaltung als offener Kanal bestehen, und
 *     die Sperre waere folgenlos fuer genau die Person, die sie ausgeloest
 *     hat.
 *
 *  2. DER VERLAUF BLEIBT STEHEN, AUCH BEI SPERRE. Er ist das Beweismittel fuer
 *     Meldungen, fuer Beschwerden nach Art. 20 DSA und fuer Auskuenfte an
 *     Behoerden. Gesperrt wird das Schreiben, nicht das Lesen. Und verbergen()
 *     setzt einen Zeitpunkt, es loescht nie.
 *
 *  3. JEDE NACHRICHT TRAEGT IHRE DEKLARATION ALS MOMENTAUFNAHME. Ohne gesetzte
 *     Deklaration wird nicht gesendet ('deklaration_fehlt'). Die Angabe wird
 *     beim Senden in die Zeile geschrieben und aendert sich nicht mehr, wenn
 *     das Konto spaeter umstellt — erst dadurch ist eine Falschangabe
 *     ueberhaupt belegbar.
 *
 *  4. KEIN SELBSTGESPRAECH. Eine Unterhaltung mit sich selbst waere kein
 *     Fehler des Nutzens, sondern der Datenlage: teilnehmer_a_id und
 *     teilnehmer_b_id waeren gleich, "die andere Person" waere nicht mehr
 *     bestimmbar, und ungeleseneAnzahl() (absender_id <> ich) zaehlte
 *     dauerhaft falsch.
 *
 *  5. DROSSELUNG, UND ZWAR ALS PFLICHT. Ohne Vorabpruefung, ohne
 *     E-Mail-Bestaetigung und mit freiem Anschreiben ist diese Plattform ab
 *     Tag eins ein Spamziel: Ein Skript registriert ein Konto und schreibt
 *     jede Verkaeuferin im Katalog an. Zwei Grenzen fangen die beiden Formen
 *     ab — die Nachrichtenflut in einer Unterhaltung und das Anschreiben in
 *     die Breite.
 *
 * Zur Rollenverteilung, weil sie leicht zu verwechseln ist: Die
 * Normalisierung "kleinere Kennung zuerst" betrifft AUSSCHLIESSLICH
 * kontext_schluessel. In den Spalten steht teilnehmer_a_id fuer die Person,
 * die eroeffnet hat, und teilnehmer_b_id fuer die angeschriebene. Ohne diese
 * Unterscheidung liesse sich die Tagesgrenze nicht messen, ohne den
 * Angeschriebenen mitzubestrafen.
 */
final class Unterhaltungen
{
    /** Unterhaltungen je Seite in meine(). */
    public const PRO_SEITE = 25;

    /**
     * Nachrichten je Abruf.
     *
     * Eine Grenze ist noetig, weil ein Verlauf beliebig lang wird und die
     * Seite ihn sonst vollstaendig laedt. 200 ist grosszuegig genug, dass sie
     * im Alltag nie greift.
     */
    public const PRO_ABRUF = 200;

    /**
     * Obergrenze einer Nachricht in Zeichen.
     *
     * Die Spalte ist TEXT und koennte mehr. Die Grenze haelt die Nachricht bei
     * dem, was ein Mensch liest, und nimmt zugleich dem Missbrauch das
     * bequemste Werkzeug: Der Chat ist nach dem Torabbau der einzige Weg, auf
     * dem ungeprueft beliebiger Text zu einer fremden Person gelangt.
     */
    public const TEXT_MAXLAENGE = 4000;

    /**
     * Nachrichten je Minute und Absender.
     *
     * Zwanzig ist bewusst hoch: Ein hitziges Gespraech soll nicht in die
     * Drosselung laufen. Es reicht trotzdem, denn es macht das automatisierte
     * Zuschuetten eines Postfachs um Groessenordnungen teurer.
     */
    public const NACHRICHTEN_JE_MINUTE = 20;

    /**
     * Neu eroeffnete Unterhaltungen je Tag und Konto.
     *
     * Das ist die Grenze gegen das Anschreiben in die Breite — die Form, die
     * wirklich weh tut, weil sie viele Menschen je einmal trifft und deshalb
     * von jedem Einzelnen kaum meldbar ist. Gezaehlt wird nur, was dieses
     * Konto EROEFFNET hat (teilnehmer_a_id): Wer angeschrieben wird, darf
     * dadurch nicht sein eigenes Kontingent verlieren, sonst legt ein
     * Angreifer ein beliebtes Konto still, indem er es dreissigmal anschreibt.
     */
    public const UNTERHALTUNGEN_JE_TAG = 30;

    /**
     * Die Art der Zustellung, wenn eine Nachricht verborgen wurde.
     *
     * ACHTUNG: Jede Art, die in 'benachrichtigungen' landen kann, braucht
     * einen Text unter 'profil.art.<art>' — die Profilseite baut ihre
     * Ueberschrift als te('profil.art.' . $art). Fehlt er, liest die betroffene
     * Person '[[profil.art.nachricht_verborgen]]' als Begruendung ihrer
     * Beschraenkung.
     */
    public const ART_VERBORGEN = 'nachricht_verborgen';

    /**
     * Die Gegenstandsart fuer Protokoll, Zustellung und Meldeweg.
     *
     * Sie steht hier und nicht als Konstante in Verwaltung, weil die Tabelle
     * 'nachrichten' zu diesem Paket gehoert. Wer den Meldeweg auf Nachrichten
     * erweitert, traegt denselben Wert in Meldungen::GEGENSTAENDE ein — der
     * dortige Kommentar hat die Zeile bereits vorgesehen.
     */
    public const GEGENSTAND_NACHRICHT = 'nachricht';

    private readonly Profile $profile;

    private readonly Verwaltung $verwaltung;

    public function __construct(private readonly Database $db)
    {
        $this->profile = new Profile($db);

        // Das Verbergen laeuft ueber Verwaltung und nicht ueber ein eigenes
        // INSERT: Dort liegt die Zusicherung, dass jede Beschraenkung
        // protokolliert UND zugestellt wird. Ein zweiter Schreibweg in
        // 'verwaltungs_ereignisse' wuerde sie aufheben, ohne etwas zu gewinnen.
        $this->verwaltung = new Verwaltung($db);
    }

    /**
     * Eroeffnet eine Unterhaltung oder findet die vorhandene.
     *
     * Zweimal Anschreiben ergibt einen Faden, nicht zwei — auch dann, wenn
     * beim zweiten Mal die Rollen vertauscht sind. Das leistet der
     * kontext_schluessel; die Begruendung steht in
     * database/migrations/011_profile_und_chat.php.
     *
     * @param int|null $angebotId Bezug auf ein Angebot, oder null fuer ein
     *                            Gespraech ohne Kontext
     *
     * @return int Kennung der Unterhaltung
     *
     * @throws ChatFehler 'selbstgespraech', 'empfaenger_unbekannt', 'gesperrt',
     *                    'zu_schnell'
     */
    public function eroeffnen(int $starterId, int $empfaengerId, ?int $angebotId = null): int
    {
        if ($starterId === $empfaengerId) {
            throw new ChatFehler(
                'selbstgespraech',
                'Konto ' . $starterId . ' kann keine Unterhaltung mit sich selbst fuehren.'
            );
        }

        if (!$this->kontoVorhanden($empfaengerId)) {
            throw new ChatFehler(
                'empfaenger_unbekannt',
                'Konto ' . $empfaengerId . ' existiert nicht.'
            );
        }

        $this->pruefeKeineSperre($starterId, $empfaengerId);

        $angebotId = $this->gepruefterAngebotsbezug($angebotId);
        $schluessel = $this->kontextSchluessel($starterId, $empfaengerId, $angebotId);

        // Zuerst suchen, dann drosseln: Eine bestehende Unterhaltung wieder zu
        // oeffnen ist kein neues Anschreiben. Wer die Reihenfolge umdreht,
        // sperrt jemanden aus einem laufenden Gespraech aus, weil er tagsueber
        // viele Gespraeche begonnen hat.
        $vorhanden = $this->db->wert(
            'SELECT id FROM unterhaltungen WHERE kontext_schluessel = :k',
            ['k' => $schluessel]
        );

        if ($vorhanden !== null) {
            return (int) $vorhanden;
        }

        $this->pruefeDrosselungUnterhaltungen($starterId);

        try {
            return $this->db->einfuegen('unterhaltungen', [
                'kontext_schluessel' => $schluessel,
                // a ist die eroeffnende Person, b die angeschriebene. Die
                // Sortierung nach Groesse steckt nur im Schluessel.
                'teilnehmer_a_id' => $starterId,
                'teilnehmer_b_id' => $empfaengerId,
                'angebot_id' => $angebotId,
                'letzte_nachricht_am' => null,
                'geschlossen_am' => null,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $fehler) {
            // Wettlauf: Zwei gleichzeitige Anfragen desselben Paares kommen
            // beide durch die Suche oben. Der eindeutige Index auf
            // kontext_schluessel entscheidet — und das Ergebnis ist genau das
            // gewuenschte, es gibt die eine Unterhaltung.
            $wieder = $this->db->wert(
                'SELECT id FROM unterhaltungen WHERE kontext_schluessel = :k',
                ['k' => $schluessel]
            );

            if ($wieder !== null) {
                return (int) $wieder;
            }

            throw $fehler;
        }
    }

    /**
     * Die eigenen Unterhaltungen, zuletzt beschriebene zuerst.
     *
     * Die Zahl der Ungelesenen und der letzte Text kommen als abhaengige
     * Teilabfragen mit, nicht ueber GROUP BY: Eine Gruppierung muesste unter
     * ONLY_FULL_GROUP_BY (MySQL 8 hat es standardmaessig an) jede ausgewaehlte
     * Spalte aufzaehlen, und den letzten Text bekaeme sie ohne GROUP_CONCAT
     * oder Fensterfunktion gar nicht. Die Teilabfragen laufen auf SQLite und
     * MySQL 8 wortgleich.
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function meine(int $benutzerId, int $seite = 1): array
    {
        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM unterhaltungen WHERE teilnehmer_a_id = :ich_a OR teilnehmer_b_id = :ich_b',
            ['ich_a' => $benutzerId, 'ich_b' => $benutzerId]
        );

        [$seite, $versatz, $seiten] = $this->blaettern($seite, $anzahl);

        $zeilen = $this->db->alle(
            'SELECT u.id, u.kontext_schluessel, u.teilnehmer_a_id, u.teilnehmer_b_id, u.angebot_id,
                    u.letzte_nachricht_am, u.geschlossen_am, u.angelegt_am,
                    (SELECT COUNT(*) FROM nachrichten n
                      WHERE n.unterhaltung_id = u.id
                        AND n.absender_id <> :ich_z
                        AND n.gelesen_am IS NULL
                        AND n.verborgen_am IS NULL) AS ungelesen,
                    (SELECT n2.text FROM nachrichten n2
                      WHERE n2.unterhaltung_id = u.id
                        AND n2.verborgen_am IS NULL
                      ORDER BY n2.id DESC
                      LIMIT 1) AS letzter_text
               FROM unterhaltungen u
              WHERE u.teilnehmer_a_id = :ich_a OR u.teilnehmer_b_id = :ich_b
              ORDER BY u.letzte_nachricht_am DESC, u.id DESC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz,
            ['ich_z' => $benutzerId, 'ich_a' => $benutzerId, 'ich_b' => $benutzerId]
        );

        $partnerIds = [];

        foreach ($zeilen as $zeile) {
            $partnerIds[] = $this->partnerVon($zeile, $benutzerId);
        }

        $namen = $this->namen($partnerIds);
        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $partnerId = $this->partnerVon($zeile, $benutzerId);

            $ergebnis[] = [
                'id' => (int) $zeile['id'],
                'angebot_id' => $zeile['angebot_id'] === null ? null : (int) $zeile['angebot_id'],
                'letzte_nachricht_am' => $zeile['letzte_nachricht_am'],
                'geschlossen_am' => $zeile['geschlossen_am'],
                'angelegt_am' => $zeile['angelegt_am'],
                'ungelesen' => (int) $zeile['ungelesen'],
                'letzter_text' => $zeile['letzter_text'],
                'partner_id' => $partnerId,
                'partner_name' => $namen[$partnerId]['name'] ?? '',
                'partner_pseudonym' => $namen[$partnerId]['pseudonym'] ?? '',
                'partner_deklaration' => $namen[$partnerId]['chat_deklaration'] ?? null,
            ];
        }

        return [
            'zeilen' => $ergebnis,
            'anzahl' => $anzahl,
            'seite' => $seite,
            'seiten' => $seiten,
            'pro_seite' => self::PRO_SEITE,
        ];
    }

    /**
     * Eine Unterhaltung — oder null, wenn sie nicht existiert oder das Konto
     * nicht daran beteiligt ist.
     *
     * Beide Faelle liefern dasselbe null, und das ist Absicht: Ein eigener
     * Fehler fuer "gibt es, aber nicht deine" wuerde die Existenz fremder
     * Unterhaltungen preisgeben und mit ihr, wer wem schreibt. Genau diese
     * Auskunft ist die empfindlichste, die diese Plattform hat.
     *
     * Die Deklaration beider Seiten kommt mit, weil das Label dauerhaft im
     * Fensterkopf steht — fuer beide sichtbar, nicht als Fussnote im Profil.
     *
     * @return array<string,mixed>|null
     */
    public function laden(int $unterhaltungId, int $benutzerId): ?array
    {
        $zeile = $this->unterhaltungZeile($unterhaltungId);

        if ($zeile === null || !$this->istTeilnehmer($zeile, $benutzerId)) {
            return null;
        }

        $partnerId = $this->partnerVon($zeile, $benutzerId);
        $namen = $this->namen([$partnerId, $benutzerId]);

        return [
            'id' => (int) $zeile['id'],
            'kontext_schluessel' => (string) $zeile['kontext_schluessel'],
            'angebot_id' => $zeile['angebot_id'] === null ? null : (int) $zeile['angebot_id'],
            'eroeffner_id' => (int) $zeile['teilnehmer_a_id'],
            'letzte_nachricht_am' => $zeile['letzte_nachricht_am'],
            'geschlossen_am' => $zeile['geschlossen_am'],
            'angelegt_am' => $zeile['angelegt_am'],
            'partner_id' => $partnerId,
            'partner_name' => $namen[$partnerId]['name'] ?? '',
            'partner_pseudonym' => $namen[$partnerId]['pseudonym'] ?? '',
            'partner_deklaration' => $namen[$partnerId]['chat_deklaration'] ?? null,
            'eigene_deklaration' => $namen[$benutzerId]['chat_deklaration'] ?? null,
        ];
    }

    /**
     * Die Nachrichten einer Unterhaltung, aelteste zuerst.
     *
     * Eine verborgene Nachricht bleibt in der Liste, aber OHNE ihren Text. Der
     * Faden behaelt damit seine Gestalt — man sieht, dass dort etwas stand —
     * und der Inhalt ist trotzdem serverseitig entfernt, statt in der Antwort
     * mitzureisen und in der Vorlage ausgeblendet zu werden.
     *
     * $seit ist die Kennung der letzten bereits bekannten Nachricht. Bei 0
     * kommen die NEUESTEN self::PRO_ABRUF (aufsteigend sortiert), beim
     * Nachladen dagegen die AELTESTEN darueber: So kann eine laenger offene
     * Seite aufholen, statt eine Luecke zu ueberspringen.
     *
     * @return list<array<string,mixed>>
     *
     * @throws ChatFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer'
     */
    public function nachrichten(int $unterhaltungId, int $benutzerId, int $seit = 0): array
    {
        $this->gepruefteTeilnahme($unterhaltungId, $benutzerId);

        $auswahl = 'SELECT id, unterhaltung_id, absender_id, text, deklaration,
                           gelesen_am, verborgen_am, angelegt_am
                      FROM nachrichten
                     WHERE unterhaltung_id = :u';

        if ($seit > 0) {
            $zeilen = $this->db->alle(
                $auswahl . ' AND id > :seit ORDER BY id ASC LIMIT ' . self::PRO_ABRUF,
                ['u' => $unterhaltungId, 'seit' => $seit]
            );
        } else {
            $zeilen = array_reverse($this->db->alle(
                $auswahl . ' ORDER BY id DESC LIMIT ' . self::PRO_ABRUF,
                ['u' => $unterhaltungId]
            ));
        }

        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $verborgen = $zeile['verborgen_am'] !== null;

            $ergebnis[] = [
                'id' => (int) $zeile['id'],
                'unterhaltung_id' => (int) $zeile['unterhaltung_id'],
                'absender_id' => (int) $zeile['absender_id'],
                'eigene' => (int) $zeile['absender_id'] === $benutzerId,
                'text' => $verborgen ? '' : (string) $zeile['text'],
                'deklaration' => (string) $zeile['deklaration'],
                'verborgen' => $verborgen,
                'gelesen_am' => $zeile['gelesen_am'],
                'angelegt_am' => $zeile['angelegt_am'],
            ];
        }

        return $ergebnis;
    }

    /**
     * Schreibt eine Nachricht.
     *
     * Die Reihenfolge der Pruefungen ist tragend: Erst wird alles geprueft,
     * dann wird geschrieben — Nachricht und der neue Stand von
     * letzte_nachricht_am entstehen gemeinsam oder gar nicht. Ohne die Klammer
     * stuende eine Unterhaltung mit einer Nachricht am Ende der Liste, weil
     * ihr Zeitstempel fehlt, und niemand faende sie wieder.
     *
     * Die Deklaration wird hier gelesen und in die Zeile geschrieben. Das ist
     * der Kern der ganzen Zusicherung: Sie wird nicht verwiesen, sondern
     * kopiert.
     *
     * @return int Kennung der Nachricht
     *
     * @throws ChatFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer',
     *                    'gesperrt', 'text_leer', 'text_zu_lang',
     *                    'deklaration_fehlt', 'zu_schnell'
     */
    public function senden(int $unterhaltungId, int $absenderId, string $text): int
    {
        $zeile = $this->gepruefteTeilnahme($unterhaltungId, $absenderId);

        // Bei JEDEM Senden, nicht nur beim Eroeffnen: Eine vor der Sperre
        // eroeffnete Unterhaltung waere sonst ein Kanal, den die Sperre nicht
        // erreicht — und das ist genau der Kanal, um den es geht.
        $this->pruefeKeineSperre($absenderId, $this->partnerVon($zeile, $absenderId));

        $text = $this->gepruefterText($text);

        $deklaration = $this->profile->deklaration($absenderId);

        if ($deklaration === null) {
            throw new ChatFehler(
                'deklaration_fehlt',
                'Konto ' . $absenderId . ' hat keine Chat-Deklaration gesetzt.'
            );
        }

        $this->pruefeDrosselungNachrichten($absenderId);

        $jetzt = gmdate('Y-m-d H:i:s');

        return (int) $this->db->transaktion(function () use ($unterhaltungId, $absenderId, $text, $deklaration, $jetzt): int {
            $nachrichtId = $this->db->einfuegen('nachrichten', [
                'unterhaltung_id' => $unterhaltungId,
                'absender_id' => $absenderId,
                'text' => $text,
                // Momentaufnahme, kein Verweis: Stellt das Konto morgen um,
                // bleibt diese Nachricht der heutigen Behauptung zugeordnet.
                'deklaration' => $deklaration,
                'gelesen_am' => null,
                'verborgen_am' => null,
                'angelegt_am' => $jetzt,
            ]);

            $this->db->ausfuehren(
                'UPDATE unterhaltungen SET letzte_nachricht_am = :jetzt WHERE id = :id',
                ['jetzt' => $jetzt, 'id' => $unterhaltungId]
            );

            return $nachrichtId;
        });
    }

    /**
     * Markiert alles, was die andere Person geschrieben hat, als gelesen.
     *
     * Die eigenen Nachrichten bleiben unberuehrt: gelesen_am ist der Nachweis,
     * dass die EMPFANGENDE Person sie gesehen hat, und den kann die
     * absendende nicht fuer sich selbst erzeugen.
     *
     * @throws ChatFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer'
     */
    public function gelesen(int $unterhaltungId, int $benutzerId): void
    {
        $this->gepruefteTeilnahme($unterhaltungId, $benutzerId);

        $this->db->ausfuehren(
            'UPDATE nachrichten SET gelesen_am = :jetzt
              WHERE unterhaltung_id = :u
                AND absender_id <> :ich
                AND gelesen_am IS NULL',
            ['jetzt' => gmdate('Y-m-d H:i:s'), 'u' => $unterhaltungId, 'ich' => $benutzerId]
        );
    }

    /**
     * Die Zahl der ungelesenen Nachrichten ueber alle Unterhaltungen.
     *
     * Das ist der Zaehler in der Navigationsleiste. Verborgene Nachrichten
     * zaehlen nicht mit: Ein Zaehler, der auf eine Nachricht zeigt, die
     * niemand mehr lesen kann, laesst sich nicht abbauen.
     */
    public function ungeleseneAnzahl(int $benutzerId): int
    {
        return (int) $this->db->wert(
            'SELECT COUNT(*)
               FROM nachrichten n
               JOIN unterhaltungen u ON u.id = n.unterhaltung_id
              WHERE (u.teilnehmer_a_id = :ich_a OR u.teilnehmer_b_id = :ich_b)
                AND n.absender_id <> :ich_s
                AND n.gelesen_am IS NULL
                AND n.verborgen_am IS NULL',
            ['ich_a' => $benutzerId, 'ich_b' => $benutzerId, 'ich_s' => $benutzerId]
        );
    }

    /**
     * Verbirgt eine Nachricht — ein Verwaltungsvorgang, kein Chatvorgang.
     *
     * LOESCHT NIE. verborgen_am wird gesetzt, die Zeile bleibt. Der Verlauf
     * ist das Beweismittel: Wer die beanstandete Nachricht loescht, nimmt der
     * Meldung, der Beschwerde nach Art. 20 DSA und jeder spaeteren Auskunft
     * ihre Grundlage — und der betroffenen Person die Moeglichkeit, die
     * Entscheidung anzufechten.
     *
     * Der Weg laeuft ueber Verwaltung::beschraenkungProtokollierenUndZustellen(),
     * und damit gilt hier alles, was dort gilt: Die Faehigkeit 'verwalten'
     * wird geprueft, die Begruendung ist Pflicht, niemand entscheidet ueber das
     * eigene Konto, und die absendende Person bekommt die Beschraenkung nach
     * Art. 17 DSA zugestellt. Faellt die Zustellung aus, faellt auch das
     * Verbergen aus — beides liegt in einer Transaktion.
     *
     * Mehrfaches Aufrufen verbirgt nicht mehrfach: Eine bereits verborgene
     * Nachricht kehrt ohne zweiten Protokolleintrag zurueck. Sonst entstuenden
     * bei einem Doppelklick zwei Zustellungen fuer eine Entscheidung.
     *
     * @throws VerwaltungsFehler 'nachricht_unbekannt', 'begruendung_fehlt',
     *                           'kein_verwaltungsrecht', 'selbstsperre_unzulaessig'
     */
    public function verbergen(int $nachrichtId, int $verwalterId, string $grund): void
    {
        $nachricht = $this->db->eine(
            'SELECT id, absender_id, verborgen_am FROM nachrichten WHERE id = :id',
            ['id' => $nachrichtId]
        );

        if ($nachricht === null) {
            throw new VerwaltungsFehler(
                'nachricht_unbekannt',
                'Nachricht ' . $nachrichtId . ' existiert nicht.'
            );
        }

        if ($nachricht['verborgen_am'] !== null) {
            return;
        }

        $absenderId = (int) $nachricht['absender_id'];

        $this->db->transaktion(function () use ($nachrichtId, $absenderId, $verwalterId, $grund): void {
            $this->db->ausfuehren(
                'UPDATE nachrichten SET verborgen_am = :jetzt WHERE id = :id AND verborgen_am IS NULL',
                ['jetzt' => gmdate('Y-m-d H:i:s'), 'id' => $nachrichtId]
            );

            $this->verwaltung->beschraenkungProtokollierenUndZustellen(
                $verwalterId,
                $absenderId,
                self::ART_VERBORGEN,
                self::GEGENSTAND_NACHRICHT,
                $nachrichtId,
                $grund
            );
        });
    }

    // --- intern ------------------------------------------------------------

    /**
     * "<kleinereId>:<groessereId>:<angebotId|0>".
     *
     * Die Sortierung macht A -> B und B -> A zur selben Unterhaltung, die 0
     * ersetzt das NULL, das ein eindeutiger Index sonst als "immer
     * verschieden" behandeln wuerde. Ausfuehrlich in
     * database/migrations/011_profile_und_chat.php.
     */
    private function kontextSchluessel(int $einer, int $anderer, ?int $angebotId): string
    {
        $kleiner = min($einer, $anderer);
        $groesser = max($einer, $anderer);

        return $kleiner . ':' . $groesser . ':' . ($angebotId ?? 0);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function unterhaltungZeile(int $unterhaltungId): ?array
    {
        return $this->db->eine(
            'SELECT id, kontext_schluessel, teilnehmer_a_id, teilnehmer_b_id, angebot_id,
                    letzte_nachricht_am, geschlossen_am, angelegt_am
               FROM unterhaltungen
              WHERE id = :id',
            ['id' => $unterhaltungId]
        );
    }

    /**
     * Laedt die Unterhaltung und stellt sicher, dass das Konto daran beteiligt
     * ist.
     *
     * Hier trennen sich die beiden Faelle, die laden() bewusst zusammenwirft:
     * Die schreibenden Wege brauchen den Unterschied, weil ihre Aufrufer eine
     * Kennung mitbringen, die sie von einer Seite haben — und eine Seite, die
     * auf eine geloeschte Unterhaltung zeigt, ist etwas anderes als ein
     * Zugriffsversuch auf eine fremde.
     *
     * @return array<string,mixed>
     *
     * @throws ChatFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer'
     */
    private function gepruefteTeilnahme(int $unterhaltungId, int $benutzerId): array
    {
        $zeile = $this->unterhaltungZeile($unterhaltungId);

        if ($zeile === null) {
            throw new ChatFehler(
                'unterhaltung_unbekannt',
                'Unterhaltung ' . $unterhaltungId . ' existiert nicht.'
            );
        }

        if (!$this->istTeilnehmer($zeile, $benutzerId)) {
            throw new ChatFehler(
                'nicht_teilnehmer',
                'Konto ' . $benutzerId . ' ist nicht an Unterhaltung ' . $unterhaltungId . ' beteiligt.'
            );
        }

        return $zeile;
    }

    /** @param array<string,mixed> $zeile */
    private function istTeilnehmer(array $zeile, int $benutzerId): bool
    {
        return (int) $zeile['teilnehmer_a_id'] === $benutzerId
            || (int) $zeile['teilnehmer_b_id'] === $benutzerId;
    }

    /**
     * Die jeweils andere Person.
     *
     * @param array<string,mixed> $zeile
     */
    private function partnerVon(array $zeile, int $benutzerId): int
    {
        $a = (int) $zeile['teilnehmer_a_id'];
        $b = (int) $zeile['teilnehmer_b_id'];

        return $a === $benutzerId ? $b : $a;
    }

    /**
     * Namen und Deklarationen zu einer Menge von Kennungen.
     *
     * Eine Abfrage fuer die ganze Seite statt einer je Zeile. Der LEFT JOIN
     * ist noetig, weil ein Konto ohne Profilzeile moeglich ist — die entsteht
     * erst beim ersten Aufruf von Profile::laden(). Der Rueckfall auf das
     * Pseudonym steht deshalb auch hier.
     *
     * @param list<int> $ids
     *
     * @return array<int,array{pseudonym:string, name:string, chat_deklaration:string|null}>
     */
    private function namen(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $platzhalter = [];
        $werte = [];

        foreach ($ids as $i => $id) {
            $platzhalter[] = ':id' . $i;
            $werte['id' . $i] = $id;
        }

        $zeilen = $this->db->alle(
            'SELECT b.id, b.pseudonym, p.anzeigename, p.chat_deklaration
               FROM benutzer b
               LEFT JOIN profile p ON p.benutzer_id = b.id
              WHERE b.id IN (' . implode(', ', $platzhalter) . ')',
            $werte
        );

        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $pseudonym = (string) $zeile['pseudonym'];
            $anzeigename = is_string($zeile['anzeigename']) ? trim($zeile['anzeigename']) : '';

            $ergebnis[(int) $zeile['id']] = [
                'pseudonym' => $pseudonym,
                'name' => $anzeigename === '' ? $pseudonym : $anzeigename,
                'chat_deklaration' => is_string($zeile['chat_deklaration']) && $zeile['chat_deklaration'] !== ''
                    ? $zeile['chat_deklaration']
                    : null,
            ];
        }

        return $ergebnis;
    }

    private function kontoVorhanden(int $benutzerId): bool
    {
        return (int) $this->db->wert(
            'SELECT COUNT(*) FROM benutzer WHERE id = :id',
            ['id' => $benutzerId]
        ) > 0;
    }

    /**
     * Ein unbekanntes Angebot verliert seinen Bezug, statt die Unterhaltung
     * zu verhindern.
     *
     * Das ist keine Nachsicht, sondern genau die Semantik, die die Spalte
     * ohnehin zusichert: angebot_id ist ON DELETE SET NULL — verschwindet das
     * Angebot, bleibt die Unterhaltung und verliert den Kontext. Ein
     * angeblicher Bezug auf ein Angebot, das es nie gab, ist derselbe Zustand,
     * nur frueher. Ihn als Fehler zu behandeln haette den Preis, dass ein
     * Gespraech an einem geloeschten Angebot gar nicht erst zustande kaeme.
     *
     * Der Fremdschluessel wuerde die Zeile ebenfalls abweisen — aber als
     * PDOException mitten im Schreiben, und die faengt oben niemand.
     */
    private function gepruefterAngebotsbezug(?int $angebotId): ?int
    {
        if ($angebotId === null || $angebotId <= 0) {
            return null;
        }

        $vorhanden = (int) $this->db->wert(
            'SELECT COUNT(*) FROM angebote WHERE id = :id',
            ['id' => $angebotId]
        );

        return $vorhanden > 0 ? $angebotId : null;
    }

    /**
     * Die Sperrliste, in BEIDEN Richtungen.
     *
     * Eine Sperre ist keine Einbahnstrasse. Wer belaestigt wird, sperrt — und
     * genau die belaestigende Person duerfte danach weiterschreiben, wenn nur
     * die eigene Richtung geprueft wuerde. Die Tabelle 'sperren' traegt
     * (benutzer_id, gesperrter_id); gefragt wird nach beiden Paaren.
     *
     * @throws ChatFehler 'gesperrt'
     */
    private function pruefeKeineSperre(int $einer, int $anderer): void
    {
        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM sperren
              WHERE (benutzer_id = :a1 AND gesperrter_id = :b1)
                 OR (benutzer_id = :b2 AND gesperrter_id = :a2)',
            ['a1' => $einer, 'b1' => $anderer, 'b2' => $anderer, 'a2' => $einer]
        );

        if ($anzahl > 0) {
            throw new ChatFehler(
                'gesperrt',
                'Zwischen Konto ' . $einer . ' und Konto ' . $anderer . ' besteht eine Sperre.'
            );
        }
    }

    /**
     * @throws ChatFehler 'text_leer', 'text_zu_lang'
     */
    private function gepruefterText(string $text): string
    {
        // Auch Leerraum ist leer. Ohne trim genuegte ein Leerzeichen, um die
        // Drosselung mit Nachrichten zu fuellen, die niemand liest.
        $text = trim($text);

        if ($text === '') {
            throw new ChatFehler('text_leer');
        }

        if (mb_strlen($text) > self::TEXT_MAXLAENGE) {
            throw new ChatFehler(
                'text_zu_lang',
                'Nachricht ist laenger als ' . self::TEXT_MAXLAENGE . ' Zeichen.'
            );
        }

        return $text;
    }

    /**
     * Nachrichten je Minute und Absender.
     *
     * Der Zeitpunkt wird in PHP gerechnet und als Zeichenkette verglichen —
     * kein DATE_SUB und kein datetime('now', '-1 minute'), weil beide je nur
     * eine der beiden Datenbanken kennen. Alle Zeitstempel im Schema stehen in
     * UTC im Format 'Y-m-d H:i:s'; in diesem Format ist der lexikografische
     * Vergleich, den SQLite auf TEXT anwendet, mit dem zeitlichen identisch.
     *
     * @throws ChatFehler 'zu_schnell'
     */
    private function pruefeDrosselungNachrichten(int $absenderId): void
    {
        $grenze = gmdate('Y-m-d H:i:s', time() - 60);

        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM nachrichten WHERE absender_id = :a AND angelegt_am > :grenze',
            ['a' => $absenderId, 'grenze' => $grenze]
        );

        if ($anzahl >= self::NACHRICHTEN_JE_MINUTE) {
            throw new ChatFehler(
                'zu_schnell',
                'Konto ' . $absenderId . ' hat in der letzten Minute bereits '
                . $anzahl . ' Nachrichten gesendet.'
            );
        }
    }

    /**
     * Neu eroeffnete Unterhaltungen je Tag und Konto.
     *
     * Gezaehlt wird ueber teilnehmer_a_id, also nur, was dieses Konto selbst
     * eroeffnet hat. Wer angeschrieben WIRD, verliert dadurch nichts.
     *
     * @throws ChatFehler 'zu_schnell'
     */
    private function pruefeDrosselungUnterhaltungen(int $starterId): void
    {
        $grenze = gmdate('Y-m-d H:i:s', time() - 86400);

        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM unterhaltungen WHERE teilnehmer_a_id = :s AND angelegt_am > :grenze',
            ['s' => $starterId, 'grenze' => $grenze]
        );

        if ($anzahl >= self::UNTERHALTUNGEN_JE_TAG) {
            throw new ChatFehler(
                'zu_schnell',
                'Konto ' . $starterId . ' hat in den letzten 24 Stunden bereits '
                . $anzahl . ' Unterhaltungen eroeffnet.'
            );
        }
    }

    /**
     * @return array{0:int, 1:int, 2:int} Seite, Versatz, Seitenzahl
     */
    private function blaettern(int $seite, int $anzahl): array
    {
        $seiten = max(1, (int) ceil($anzahl / self::PRO_SEITE));
        $seite = max(1, min($seite, $seiten));

        return [$seite, ($seite - 1) * self::PRO_SEITE, $seiten];
    }
}
