<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Verification;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\MedienFehler;

/**
 * Die manuelle Identitaetspruefung: ein Selfie mit einem handgeschriebenen
 * Zettel, ein plattformseitig vergebener Code, und ein Loeschdatum, das die
 * Datenbank erzwingt.
 *
 * WAS DIESE PRUEFUNG IST UND WAS SIE AUSDRUECKLICH NICHT IST.
 *
 * Sie zeigt, dass die Person hinter dem Konto in dem Moment existiert hat, in
 * dem der Code galt — nicht mehr. Sie ist KEINE Altersverifikation und wird
 * nirgends als eine bezeichnet: Ein handgeschriebener Zettel erfuellt weder
 * § 4 Abs. 2 JMStV noch das AVS-Raster der KJM, und ein Abzeichen, das etwas
 * anderes behauptet, waere schlimmer als gar keines
 * (docs/04-features/verifizierung-altersstufen.md, Abschnitt "Was das
 * Abzeichen bedeutet"). Deshalb heisst es "Identitaet geprueft (manuell)",
 * deshalb bleibt pruefungen.volljaehrig hier IMMER 0, und deshalb vergibt
 * diese Klasse keine einzige Faehigkeit — insbesondere nicht 'kaufen', die
 * genau an der Altersverifikation haengt (Konten, Kopfkommentar).
 *
 * DREI ENTSCHEIDUNGEN, DIE HIER FESTGEHALTEN WERDEN MUESSEN:
 *
 *  1. DER CODE KOMMT VON DER PLATTFORM, NICHT VON DER PERSON, UND ER LAEUFT
 *     AB. Ein Zettel mit einem selbst gewaehlten Text beweist nichts: Er
 *     laesst sich einmal fotografieren und beliebig oft einreichen, kaufen
 *     oder weitergeben. Erst ein Code, den die Plattform gerade eben vergeben
 *     hat, bindet das Foto an diesen Vorgang — und erst eine Frist bindet es
 *     an diesen Zeitpunkt. 48 Stunden sind lang genug fuer "morgen bei
 *     Tageslicht" und kurz genug, dass sich ein Foto nicht bestellen laesst.
 *     Das Datum steht zusaetzlich auf dem Zettel, weil es die einzige Angabe
 *     ist, die auch dann noch traegt, wenn der Code spaeter aus der Datenbank
 *     verschwunden ist.
 *
 *  2. GENAU EIN FOTO JE VORGANG. Kein Nachreichen, kein Ersetzen, kein
 *     zweiter Versuch am selben Code. Wer nachbessern darf, bis es passt,
 *     probiert aus, welches Bild durchkommt — und die Verwaltung entscheidet
 *     dann ueber eine Auswahl statt ueber eine Aufnahme. Ein zweites Foto wird
 *     mit 'beleg_schon_eingereicht' abgewiesen; wer neu anfangen will,
 *     bekommt nach der Entscheidung einen neuen Vorgang mit neuem Code.
 *
 *  3. DIE BILDER LAUFEN UEBER DIESELBE PIPELINE WIE ALLE ANDEREN
 *     (MeinSlip\Domain\Media\Bilder). Es gibt in diesem Projekt genau eine
 *     Stelle, an der fremde Bytes zu einer Datei auf der Platte werden. Hier
 *     zaehlt daran vor allem der EXIF-Strip: Ein Selfie traegt fast immer die
 *     Koordinaten der eigenen Wohnung, und es entsteht ausgerechnet bei einem
 *     Vorgang, bei dem die Person der Plattform gerade ihr Gesicht gibt. Die
 *     Neukodierung nimmt den APP1-Abschnitt mit — samt GPS, Kameraseriennummer
 *     und Uhrzeit.
 *
 * DIE LOESCHFRIST, UND WARUM SIE ZWEIMAL DURCHGESETZT WIRD.
 *
 * loeschen_ab ist NOT NULL (013_pruefungsbelege.php): Eine Zeile ohne
 * Loeschdatum ist schemaseitig unmoeglich. Gesetzt wird es beim Anlegen auf
 * angelegt_am + FRIST_UNBEARBEITET_TAGE und bei der Entscheidung auf das
 * FRUEHERE aus diesem Datum und entschieden_am + FRIST_ENTSCHIEDEN_TAGE. Es
 * wird nie nach hinten geschoben — auch nicht, wenn ein neuer Code auf
 * denselben Vorgang ausgegeben wird.
 *
 * Die Uhr laeuft ab dem ANLEGEN des Vorgangs und nicht ab dem Eingang des
 * Fotos. Das ist die strengere der beiden moeglichen Lesarten von "hoechstens
 * 30 Tage unbearbeitet" und die einzige, die das Schema von der ersten Zeile
 * an garantieren kann: Zum Zeitpunkt des INSERT gibt es das Foto noch nicht,
 * ein Datum muss aber trotzdem dastehen.
 *
 * Durchgesetzt wird die Frist an zwei Stellen, und keine ersetzt die andere:
 * bin/pflege als Cronjob und faelligeLoeschen() bei jedem Aufruf der
 * Pruefseite in der Verwaltung. Ein Cronjob, den niemand beobachtet, ist keine
 * Sicherung — er faellt still aus, und niemand merkt es, weil nichts kaputt
 * geht, wenn zu viel aufgehoben wird. Die opportunistische Raeumung faellt
 * dagegen genau demjenigen auf, der die Belege ansieht.
 */
final class Pruefbelege
{
    /** Code vergeben, Foto steht aus. */
    public const STATUS_OFFEN = 'offen';

    /** Foto liegt vor, die Verwaltung hat noch nicht entschieden. */
    public const STATUS_EINGEREICHT = 'eingereicht';

    public const STATUS_FREIGEGEBEN = 'freigegeben';
    public const STATUS_ABGELEHNT = 'abgelehnt';

    /**
     * Status, in denen ein Vorgang laeuft.
     *
     * Genau einer davon darf je Konto gleichzeitig bestehen — sonst waeren
     * zwei Codes gleichzeitig gueltig und ein Foto liesse sich dem
     * bequemeren von beiden zuordnen.
     *
     * @var list<string>
     */
    public const STATUS_LAUFEND = [self::STATUS_OFFEN, self::STATUS_EINGEREICHT];

    /** Wie lange ein vergebener Code gilt. */
    public const CODE_GUELTIG_STUNDEN = 48;

    /** Hoechstdauer ohne Bearbeitung. */
    public const FRIST_UNBEARBEITET_TAGE = 30;

    /** Hoechstdauer nach der Entscheidung. */
    public const FRIST_ENTSCHIEDEN_TAGE = 7;

    /**
     * Das Zeichenvorrat des Codes: 20 Zeichen, alle handschriftlich
     * unterscheidbar.
     *
     * Fehlend und jeweils warum — der Zettel wird mit der Hand geschrieben und
     * am Bildschirm abgelesen, also faellt jedes Paar heraus, das sich dabei
     * verwechseln laesst:
     *
     *   0 O Q  — voneinander und von D nicht sicher zu trennen
     *   1 I l  — in fast jeder Handschrift derselbe Strich
     *   2 Z    — mit Querstrich identisch
     *   5 S    — im Schwung gleich
     *   6 G    — geschlossene Schlaufe hier wie dort
     *   7 T    — der gequerte Sieber sieht aus wie ein T
     *   8 B    — dieselbe Doppelschlaufe
     *   U V    — ohne runden Boden nicht auseinanderzuhalten
     *
     * Was bleibt, sind 3 4 9 A C D E F H J K L M N P R T W X Y. Acht Stellen
     * ergeben 20^8, also rund 2,6 * 10^10 Moeglichkeiten oder knapp 35 Bit —
     * fuer ein Wort, das 48 Stunden lebt und nur zu einem einzigen Vorgang
     * passt, ist das reichlich.
     */
    public const CODE_ZEICHEN = '349ACDEFHJKLMNPRTWXY';

    /** Stellen je Gruppe und Zahl der Gruppen: 'ABCD-EFGH'. */
    public const CODE_GRUPPENLAENGE = 4;

    public const CODE_GRUPPEN = 2;

    /**
     * Die Art, unter der das Ergebnis in 'pruefungen' landet.
     *
     * Nicht 'altersidentifizierung' und nicht 'lebendnachweis': Beide
     * behaupteten mehr, als hier geschieht. 'identitaet_manuell' sagt genau,
     * was es ist — von Hand, von Menschen, ohne Verfahren.
     */
    public const PRUEFUNG_ART = 'identitaet_manuell';

    /** Es gibt keinen Anbieter. Das ist die Auskunft, nicht eine Luecke. */
    public const PRUEFUNG_ANBIETER = 'manuell';

    /** Zeilen je Seite in der Arbeitsliste — wie im uebrigen Verwaltungsbereich. */
    public const PRO_SEITE = 25;

    /** Versuche, einen kollisionsfreien Code zu finden. */
    private const CODE_VERSUCHE = 8;

    /** Die Bildpipeline aus P1. Es gibt keine zweite. */
    private readonly Bilder $bilder;

    public function __construct(
        private readonly Database $db,
        /**
         * Das Wurzelverzeichnis der Belege, absolut und OHNE
         * Schlussschraegstrich. Es liegt ausserhalb von public/ — kein
         * Webserver liefert hier je direkt aus, jeder Abruf laeuft durch die
         * Verwaltungsroute.
         */
        private readonly string $wurzel,
        /**
         * Optional aus genau dem Grund, aus dem Medien dieselbe Naht hat:
         * is_uploaded_file() ist ausserhalb einer echten HTTP-Anfrage immer
         * false, ein Test kaeme sonst nie bis zum ersten Bild. In app/ wird
         * diese Naht nie benutzt.
         */
        ?Bilder $bilder = null,
    ) {
        $this->bilder = $bilder ?? new Bilder();
    }

    /**
     * Das Belegverzeichnis zu einem Projektstamm.
     *
     * BEWUSST NICHT unterhalb von Medien::verzeichnis(): Angebotsbilder und
     * Pruefbelege haben nichts gemeinsam ausser der Pipeline, die sie
     * schreibt. Ein Angebotsbild soll gesehen werden, ein Beleg soll
     * verschwinden. Lagen sie im selben Baum, wuerde eine Sicherung, ein
     * Umzug oder ein 'rsync storage/medien' den Beleg selbstverstaendlich
     * mitnehmen — und niemandem fiele auf, dass damit eine Loeschzusage
     * gebrochen ist.
     */
    public static function verzeichnis(string $projektwurzel): string
    {
        return rtrim($projektwurzel, '/') . '/storage/belege';
    }

    // --- Vorgang der betroffenen Person ------------------------------------

    /**
     * Vergibt einen Code und legt den Vorgang an — oder erneuert den Code
     * eines laufenden Vorgangs, zu dem noch kein Foto vorliegt.
     *
     * Die Erneuerung schiebt loeschen_ab AUSDRUECKLICH NICHT nach hinten. Wer
     * sich sechsmal einen neuen Code holt, verlaengert damit nicht die Zeit,
     * die seine Daten hier liegen — sonst waere die 30-Tage-Frist ueber einen
     * Knopf beliebig dehnbar, und das ist keine Frist mehr.
     *
     * @param string|null $jetzt Zeitpunkt als 'Y-m-d H:i:s' in UTC; null = jetzt
     *
     * @return array<string,mixed> der Vorgang samt frischem Code
     *
     * @throws PruefbelegFehler 'beleg_schon_eingereicht'
     */
    public function codeVergeben(int $benutzerId, ?string $jetzt = null): array
    {
        $jetzt = $this->zeitpunkt($jetzt);
        $laufend = $this->laufenderVorgang($benutzerId);

        // Ein Vorgang mit Foto ist abgeschlossen, was die Person betrifft. Ein
        // neuer Code darauf haette nur einen Zweck: einen zweiten Anlauf am
        // selben Vorgang, und genau den gibt es nicht (Entscheidung 2 oben).
        if ($laufend !== null && $laufend['status'] === self::STATUS_EINGEREICHT) {
            throw new PruefbelegFehler(
                'beleg_schon_eingereicht',
                'Vorgang ' . $laufend['id'] . ' wartet bereits auf eine Entscheidung.'
            );
        }

        $code = $this->freierCode();
        $gueltigBis = $this->spaeter($jetzt, self::CODE_GUELTIG_STUNDEN * 3600);

        if ($laufend !== null) {
            $this->db->ausfuehren(
                'UPDATE pruefungsbelege
                    SET code = :c, code_ausgegeben_am = :a, code_gueltig_bis = :g
                  WHERE id = :id',
                ['c' => $code, 'a' => $jetzt, 'g' => $gueltigBis, 'id' => $laufend['id']]
            );

            return $this->vorgang((int) $laufend['id']) ?? $laufend;
        }

        $id = $this->db->einfuegen('pruefungsbelege', [
            'benutzer_id' => $benutzerId,
            'code' => $code,
            'code_ausgegeben_am' => $jetzt,
            'code_gueltig_bis' => $gueltigBis,
            'status' => self::STATUS_OFFEN,
            'pfad' => null,
            'vorschau_pfad' => null,
            'eingereicht_am' => null,
            'entschieden_am' => null,
            'verwalter_id' => null,
            'entscheidung' => null,
            'pruefung_id' => null,
            // Die Frist steht ab der ersten Sekunde. Sie ist kein Nachtrag.
            'loeschen_ab' => $this->spaeter($jetzt, self::FRIST_UNBEARBEITET_TAGE * 86400),
            'angelegt_am' => $jetzt,
        ]);

        $vorgang = $this->vorgang($id);

        if ($vorgang === null) {
            // Kann nur passieren, wenn zwischen INSERT und SELECT jemand die
            // Zeile entfernt. Laut statt still: Ein Vorgang, den die Person
            // nicht angezeigt bekommt, ist ein Vorgang, den sie nie
            // abschliesst.
            throw new PruefbelegFehler('gestoert', 'Vorgang ' . $id . ' ist nach dem Anlegen nicht lesbar.');
        }

        return $vorgang;
    }

    /**
     * Nimmt das eine Foto zu einem laufenden Vorgang entgegen.
     *
     * Die Reihenfolge der Pruefungen ist tragend: erst der Vorgang, dann die
     * Frist, dann erst das Bild. Ein abgelaufener Code darf nicht dazu
     * fuehren, dass ein Selfie ueberhaupt erst auf die Platte geschrieben und
     * anschliessend wieder weggeraeumt wird — das waere eine Verarbeitung ohne
     * Zweck.
     *
     * @param array<string,mixed> $datei ein Eintrag aus $_FILES
     *
     * @return int die Kennung des Vorgangs
     *
     * @throws PruefbelegFehler 'kein_vorgang', 'code_abgelaufen',
     *                          'beleg_schon_eingereicht'
     * @throws MedienFehler     alle Schluessel aus Bilder::annehmen()
     */
    public function belegHochladen(int $benutzerId, array $datei, ?string $jetzt = null): int
    {
        $jetzt = $this->zeitpunkt($jetzt);
        $vorgang = $this->laufenderVorgang($benutzerId);

        if ($vorgang === null) {
            throw new PruefbelegFehler(
                'kein_vorgang',
                'Konto ' . $benutzerId . ' hat keinen laufenden Vorgang.'
            );
        }

        if ($vorgang['status'] === self::STATUS_EINGEREICHT) {
            throw new PruefbelegFehler(
                'beleg_schon_eingereicht',
                'Vorgang ' . $vorgang['id'] . ' traegt bereits ein Foto.'
            );
        }

        // Zeichenkettenvergleich, weil alle Zeitangaben im Schema als
        // 'Y-m-d H:i:s' in UTC stehen — dieselbe Rechnung wie in
        // Verwaltung::meldungen(). Ein strtotime() hier wuerde die lokale
        // Zeitzone annehmen und die Frist je nach Serverstandort verschieben.
        if ((string) $vorgang['code_gueltig_bis'] < $jetzt) {
            throw new PruefbelegFehler(
                'code_abgelaufen',
                'Der Code zu Vorgang ' . $vorgang['id'] . ' galt bis ' . $vorgang['code_gueltig_bis'] . '.'
            );
        }

        $belegId = (int) $vorgang['id'];
        $ergebnis = $this->bilder->annehmen($datei, $this->belegverzeichnis($belegId));

        // Ab hier liegen zwei Dateien auf der Platte. Jeder weitere Fehler
        // muss sie mitnehmen — ein Selfie ohne Zeile in der Datenbank wuerde
        // von keiner Frist erfasst und laege fuer immer da.
        try {
            $anweisung = $this->db->ausfuehren(
                'UPDATE pruefungsbelege
                    SET pfad = :p, vorschau_pfad = :v, status = :s, eingereicht_am = :z
                  WHERE id = :id AND status = :offen',
                [
                    'p' => $this->relativ($ergebnis['pfad']),
                    'v' => $this->relativ($ergebnis['vorschau_pfad']),
                    's' => self::STATUS_EINGEREICHT,
                    'z' => $jetzt,
                    'id' => $belegId,
                    // Die Bedingung auf 'offen' schliesst den Wettlauf zweier
                    // gleichzeitig abgesendeter Uploads: Der zweite trifft
                    // keine Zeile mehr und raeumt seine Dateien selbst weg.
                    // Ohne sie ueberschriebe er den Pfad des ersten, und das
                    // erste Foto laege ohne Verweis auf der Platte — von
                    // keiner Frist erfasst.
                    'offen' => self::STATUS_OFFEN,
                ]
            );

            // Der Statuswechsel aendert immer mindestens eine Spalte, sobald
            // die Zeile getroffen wird. 0 heisst deshalb: nicht getroffen.
            if ($anweisung->rowCount() === 0) {
                throw new PruefbelegFehler(
                    'beleg_schon_eingereicht',
                    'Vorgang ' . $belegId . ' war beim Speichern nicht mehr offen.'
                );
            }
        } catch (\Throwable $fehler) {
            @unlink($ergebnis['pfad']);
            @unlink($ergebnis['vorschau_pfad']);
            @rmdir($this->belegverzeichnis($belegId));

            throw $fehler;
        }

        return $belegId;
    }

    /**
     * Der laufende Vorgang eines Kontos — 'offen' oder 'eingereicht'.
     *
     * @return array<string,mixed>|null
     */
    public function laufenderVorgang(int $benutzerId): ?array
    {
        [$platzhalter, $werte] = $this->inListe('s', self::STATUS_LAUFEND);

        $zeile = $this->db->eine(
            'SELECT * FROM pruefungsbelege
              WHERE benutzer_id = :b AND status IN (' . $platzhalter . ')
              ORDER BY id DESC
              LIMIT 1',
            ['b' => $benutzerId] + $werte
        );

        return $zeile === null ? null : $this->zeileFormen($zeile);
    }

    /**
     * Der neueste Vorgang eines Kontos, gleich in welchem Status.
     *
     * Fuer die eigene Seite: Nach einer Entscheidung soll die Person lesen
     * koennen, wie sie ausgefallen ist — und zwar solange der Beleg noch da
     * ist. Danach verschwindet die Zeile mitsamt der Begruendung; die
     * Zustellung nach 'benachrichtigungen' bleibt und ist der dauerhafte
     * Nachweis.
     *
     * @return array<string,mixed>|null
     */
    public function letzterVorgang(int $benutzerId): ?array
    {
        $zeile = $this->db->eine(
            'SELECT * FROM pruefungsbelege WHERE benutzer_id = :b ORDER BY id DESC LIMIT 1',
            ['b' => $benutzerId]
        );

        return $zeile === null ? null : $this->zeileFormen($zeile);
    }

    /**
     * Traegt dieses Konto das Abzeichen "Identitaet geprueft (manuell)"?
     *
     * Gelesen wird 'pruefungen', nicht 'pruefungsbelege': Das Ergebnis ist das
     * Dauerhafte, der Beleg das Fluechtige. Nach 7 Tagen gibt es den Beleg
     * nicht mehr — das Abzeichen bleibt, und genau so ist es gedacht.
     */
    public function abzeichenVorhanden(int $benutzerId): bool
    {
        return (int) $this->db->wert(
            'SELECT COUNT(*) FROM pruefungen
              WHERE benutzer_id = :b AND art = :a AND status = :s',
            ['b' => $benutzerId, 'a' => self::PRUEFUNG_ART, 's' => 'bestanden']
        ) > 0;
    }

    // --- Sicht der Verwaltung ----------------------------------------------

    /**
     * Belege, die auf eine Entscheidung warten — aelteste zuerst.
     *
     * Die Abfrage ist so geschrieben, dass sie
     * idx_pruefungsbelege_status_angelegt_am benutzen kann: Gleichheitsfilter
     * auf status ohne Funktion darum, danach ORDER BY angelegt_am. Wer hier
     * ein OR oder eine andere Sortierung einbaut, nimmt dem Index die Wirkung.
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function offeneBelege(int $seite = 1): array
    {
        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM pruefungsbelege WHERE status = :s',
            ['s' => self::STATUS_EINGEREICHT]
        );

        $seiten = max(1, (int) ceil($anzahl / self::PRO_SEITE));
        $seite = max(1, min($seite, $seiten));
        $versatz = ($seite - 1) * self::PRO_SEITE;

        $zeilen = $this->db->alle(
            'SELECT b.*, n.pseudonym AS pseudonym, n.status AS konto_status
               FROM pruefungsbelege b
               JOIN benutzer n ON n.id = b.benutzer_id
              WHERE b.status = :s
              ORDER BY b.angelegt_am ASC, b.id ASC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz,
            ['s' => self::STATUS_EINGEREICHT]
        );

        return [
            'zeilen' => array_values(array_map(fn (array $z): array => $this->zeileFormen($z), $zeilen)),
            'anzahl' => $anzahl,
            'seite' => $seite,
            'seiten' => $seiten,
            'pro_seite' => self::PRO_SEITE,
        ];
    }

    /**
     * Ein Beleg samt Pseudonym — fuer die Entscheidungsseite.
     *
     * @return array<string,mixed>|null
     */
    public function beleg(int $belegId): ?array
    {
        $zeile = $this->db->eine(
            'SELECT b.*, n.pseudonym AS pseudonym, n.status AS konto_status
               FROM pruefungsbelege b
               JOIN benutzer n ON n.id = b.benutzer_id
              WHERE b.id = :id',
            ['id' => $belegId]
        );

        return $zeile === null ? null : $this->zeileFormen($zeile);
    }

    /**
     * Entscheidet ueber einen Beleg und schreibt das Ergebnis nach
     * 'pruefungen'.
     *
     * WAS HIER IN 'pruefungen' LANDET UND WAS NICHT: das Ergebnis, der
     * Zeitpunkt und ein Referenzschluessel. Kein Pfad, kein Bild, kein
     * Zettelinhalt. Die Tabelle hat kein Feld dafuer, und sie bekommt keins —
     * docs/04-features/verifizierung-altersstufen.md verbietet es an Zeile 42
     * bis 44 ausdruecklich.
     *
     * volljaehrig BLEIBT 0, auch bei einer Freigabe. Der Zettel sagt nichts
     * ueber das Alter, und eine 1 an dieser Stelle waere die eine Zeile, mit
     * der aus einer Identitaetspruefung stillschweigend eine Altersfreigabe
     * wuerde — genau das, was die Aufsicht als unzulaessiges Gate ansaehe.
     *
     * Die Frist wird verkuerzt, nie verlaengert: das Fruehere aus dem
     * bisherigen Loeschdatum und entschieden_am + FRIST_ENTSCHIEDEN_TAGE.
     *
     * @return array<string,mixed> der entschiedene Vorgang
     *
     * @throws PruefbelegFehler 'beleg_unbekannt', 'beleg_nicht_offen',
     *                          'entscheidung_fehlt'
     */
    public function entscheiden(
        int $belegId,
        int $verwalterId,
        bool $freigeben,
        string $entscheidung,
        ?string $jetzt = null
    ): array {
        $jetzt = $this->zeitpunkt($jetzt);
        $entscheidung = trim($entscheidung);

        // Pflicht in BEIDE Richtungen. Bei der Ablehnung ist der Text die
        // Begruendung, die die Person braucht, um es besser zu machen; bei der
        // Freigabe ist er der Vermerk, worauf sich die Entscheidung stuetzte —
        // und das ist die einzige Spur, die den Beleg um sieben Tage
        // ueberlebt.
        if ($entscheidung === '') {
            throw new PruefbelegFehler('entscheidung_fehlt');
        }

        $beleg = $this->beleg($belegId);

        if ($beleg === null) {
            throw new PruefbelegFehler('beleg_unbekannt', 'Beleg ' . $belegId . ' existiert nicht.');
        }

        if ($beleg['status'] !== self::STATUS_EINGEREICHT) {
            throw new PruefbelegFehler(
                'beleg_nicht_offen',
                'Beleg ' . $belegId . ' steht auf "' . $beleg['status'] . '".'
            );
        }

        return $this->db->transaktion(function () use ($beleg, $belegId, $verwalterId, $freigeben, $entscheidung, $jetzt): array {
            $pruefungId = $this->db->einfuegen('pruefungen', [
                'benutzer_id' => (int) $beleg['benutzer_id'],
                'art' => self::PRUEFUNG_ART,
                'anbieter' => self::PRUEFUNG_ANBIETER,
                'anbieter_referenz' => 'pruefungsbeleg:' . $belegId,
                'status' => $freigeben ? 'bestanden' : 'abgelehnt',
                // Siehe Kopfkommentar dieser Methode: immer 0.
                'volljaehrig' => 0,
                'geprueft_am' => $jetzt,
                // Kein Ablaufdatum: Ein wiederkehrender Nachweis waere eine
                // eigene Entscheidung mit eigener Frist, keine Nebenwirkung
                // dieser Zeile.
                'gueltig_bis' => null,
                'angelegt_am' => $jetzt,
            ]);

            $frist = min(
                (string) $beleg['loeschen_ab'],
                $this->spaeter($jetzt, self::FRIST_ENTSCHIEDEN_TAGE * 86400)
            );

            $this->db->ausfuehren(
                'UPDATE pruefungsbelege
                    SET status = :s, entschieden_am = :z, verwalter_id = :v,
                        entscheidung = :e, pruefung_id = :p, loeschen_ab = :l
                  WHERE id = :id',
                [
                    's' => $freigeben ? self::STATUS_FREIGEGEBEN : self::STATUS_ABGELEHNT,
                    'z' => $jetzt,
                    'v' => $verwalterId,
                    'e' => $entscheidung,
                    'p' => $pruefungId,
                    'l' => $frist,
                    'id' => $belegId,
                ]
            );

            return $this->beleg($belegId) ?? $beleg;
        });
    }

    // --- Loeschen ----------------------------------------------------------

    /**
     * Entfernt alle faelligen Belege — Zeile UND Dateien.
     *
     * DIE REIHENFOLGE IST HIER UMGEKEHRT ZU Medien::entfernen(), und das ist
     * kein Versehen. Dort wird erst die Zeile geloescht, damit ein
     * gescheitertes unlink() kein abrufbares Bild zuruecklaesst — die Zusage
     * ist Zugriffskontrolle. Hier ist die Zusage LOESCHUNG: Verschwaende die
     * Zeile zuerst und scheiterte danach das unlink(), laege ein Selfie auf
     * der Platte, zu dem es keinen Verweis mehr gibt. Niemand faende es je
     * wieder, keine Frist erfasste es, und die Loeschzusage waere gebrochen,
     * ohne dass es auffiele.
     *
     * Andersherum ist der schlechteste Fall harmlos: eine Zeile ohne Dateien.
     * Sie ist weiterhin faellig und verschwindet beim naechsten Lauf.
     *
     * @param string|null $jetzt Zeitpunkt als 'Y-m-d H:i:s' in UTC; null = jetzt
     *
     * @return int Zahl der entfernten Belege
     */
    public function faelligeLoeschen(?string $jetzt = null): int
    {
        $jetzt = $this->zeitpunkt($jetzt);

        $faellige = $this->db->alle(
            'SELECT id, pfad, vorschau_pfad FROM pruefungsbelege WHERE loeschen_ab <= :jetzt',
            ['jetzt' => $jetzt]
        );

        $entfernt = 0;

        foreach ($faellige as $zeile) {
            $id = (int) $zeile['id'];

            foreach (['pfad', 'vorschau_pfad'] as $spalte) {
                $voll = $this->datei(is_string($zeile[$spalte]) ? $zeile[$spalte] : null);

                if ($voll !== null) {
                    @unlink($voll);
                }
            }

            // Das Verzeichnis mit: Ein leerer Ordner je Vorgang haeuft sich
            // sonst bis zur Inode-Grenze des Hostings an, und er verraet durch
            // seinen Namen weiterhin, dass es diesen Vorgang gab.
            @rmdir($this->belegverzeichnis($id));

            $this->db->ausfuehren('DELETE FROM pruefungsbelege WHERE id = :id', ['id' => $id]);
            ++$entfernt;
        }

        return $entfernt;
    }

    /**
     * Entfernt Belegverzeichnisse, zu denen es keine Zeile mehr gibt.
     *
     * Der Hosentraeger zur Loeschzusage. Es gibt genau einen Weg, auf dem eine
     * Zeile ohne faelligeLoeschen() verschwindet: das ON DELETE CASCADE auf
     * benutzer_id. Wird ein Konto wirklich aus der Datenbank entfernt — nicht
     * bloss auf status 'geloescht' gesetzt —, nimmt die Datenbank den Beleg
     * mit und laesst die Dateien liegen. Genau dann greift diese Methode.
     *
     * DIE ALTERSSCHWELLE IST KEINE VORSICHT, SONDERN DIE KORREKTUR EINES
     * ECHTEN FEHLERS. Diese Methode entscheidet ueber Dateien anhand einer
     * Datenbank — und sie kann nicht wissen, ob es DIE Datenbank ist, zu der
     * die Dateien gehoeren. Wer bin/pflege einmal mit einer frischen, einer
     * falschen oder einer noch nicht migrierten Verbindung startet (ein
     * verstelltes DB_NAME, eine Wiederherstellung, ein Testlauf), loescht ohne
     * diese Schwelle SAEMTLICHE Belege: Zu keinem gibt es dann eine Zeile,
     * also gilt jeder als verwaist. Genau das ist beim Bau dieses Pakets
     * passiert, und zwar an einer Stelle, an der niemand hingesehen hat.
     *
     * Die Schwelle macht daraus einen unmoeglichen Fall: Ein Verzeichnis wird
     * erst angefasst, wenn seit seiner letzten Aenderung mehr Zeit vergangen
     * ist, als ein Beleg ueberhaupt leben darf (FRIST_UNBEARBEITET_TAGE). Was
     * juenger ist, koennte zu einem laufenden Vorgang gehoeren — und eine
     * Loeschung, die sich irren kann, hat hier nichts verloren.
     *
     * Der Preis ist klein und die Zusage bleibt ganz: Ein verwaistes
     * Verzeichnis liegt hoechstens so lange, wie sein Beleg ohnehin gelegen
     * haette. Keine Belegdatei ueberlebt die Hoechstfrist — verwaist oder
     * nicht.
     *
     * Sie arbeitet ausschliesslich auf Verzeichnissen mit rein numerischem
     * Namen und fasst nichts an, was sie nicht selbst angelegt haben koennte.
     *
     * @param int|null $jetzt Unix-Zeitstempel; null = jetzt
     *
     * @return int Zahl der entfernten Verzeichnisse
     */
    public function verwaisteEntfernen(?int $jetzt = null): int
    {
        if (!is_dir($this->wurzel)) {
            return 0;
        }

        $schwelle = ($jetzt ?? time()) - self::FRIST_UNBEARBEITET_TAGE * 86400;
        $entfernt = 0;

        foreach (scandir($this->wurzel) ?: [] as $eintrag) {
            if (!ctype_digit($eintrag)) {
                continue;
            }

            $pfad = $this->wurzel . '/' . $eintrag;

            if (!is_dir($pfad) || $this->juengsteAenderung($pfad) > $schwelle) {
                continue;
            }

            $vorhanden = (int) $this->db->wert(
                'SELECT COUNT(*) FROM pruefungsbelege WHERE id = :id',
                ['id' => (int) $eintrag]
            );

            if ($vorhanden > 0) {
                continue;
            }

            foreach (scandir($pfad) ?: [] as $datei) {
                if ($datei !== '.' && $datei !== '..' && is_file($pfad . '/' . $datei)) {
                    @unlink($pfad . '/' . $datei);
                }
            }

            if (@rmdir($pfad)) {
                ++$entfernt;
            }
        }

        return $entfernt;
    }

    /**
     * Der juengste Zeitstempel im Verzeichnis, samt dem des Verzeichnisses.
     *
     * Beides zusammen, weil keines allein genuegt: Die Zeit des Verzeichnisses
     * aendert sich beim Anlegen und Entfernen von Dateien, die der Dateien
     * beim Schreiben. Ein Verzeichnis, dessen letzte Datei gerade entfernt
     * wurde, saehe ueber die Dateien allein uralt aus.
     *
     * Im Zweifel wird das Verzeichnis als frisch behandelt (PHP_INT_MAX):
     * Wenn sich das Alter nicht feststellen laesst, ist Nichtloeschen die
     * richtige Antwort.
     */
    private function juengsteAenderung(string $pfad): int
    {
        $zeit = @filemtime($pfad);

        if ($zeit === false) {
            return PHP_INT_MAX;
        }

        foreach (scandir($pfad) ?: [] as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }

            $datei = @filemtime($pfad . '/' . $eintrag);

            if ($datei === false) {
                return PHP_INT_MAX;
            }

            $zeit = max($zeit, $datei);
        }

        return $zeit;
    }

    // --- Dateien -----------------------------------------------------------

    /**
     * Loest einen gespeicherten relativen Pfad in einen absoluten auf.
     *
     * Woertlich dieselbe Absicherung wie Medien::datei(): Der relative Pfad
     * stammt aus der Datenbank und kann strukturell kein '..' enthalten, wird
     * aber trotzdem gegen das Belegverzeichnis geprueft. realpath() loest dabei
     * auch symbolische Verweise auf — ein Link im Belegverzeichnis, der nach
     * /etc zeigt, faellt hier durch.
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
     * Ein Code, den es noch nicht gibt.
     *
     * Der eindeutige Index auf 'code' ist die eigentliche Zusicherung; diese
     * Schleife erspart der Person nur die Fehlermeldung. Nach
     * CODE_VERSUCHE Anlaeufen wird abgebrochen statt endlos weitergewuerfelt:
     * Wer bei 20^8 Moeglichkeiten achtmal hintereinander kollidiert, hat kein
     * Glueckproblem, sondern einen kaputten Zufallsgenerator — und das soll
     * auffallen.
     *
     * @throws PruefbelegFehler 'gestoert'
     */
    private function freierCode(): string
    {
        for ($versuch = 0; $versuch < self::CODE_VERSUCHE; ++$versuch) {
            $code = self::codeErzeugen();

            if ((int) $this->db->wert(
                'SELECT COUNT(*) FROM pruefungsbelege WHERE code = :c',
                ['c' => $code]
            ) === 0) {
                return $code;
            }
        }

        throw new PruefbelegFehler('gestoert', 'Kein freier Code nach ' . self::CODE_VERSUCHE . ' Versuchen.');
    }

    /**
     * Erzeugt einen Code der Form 'ABCD-EFGH'.
     *
     * random_int() und nicht ord(random_bytes(1)) % 20: Beide schoepfen aus
     * derselben Quelle, aber der Rest einer Division durch 20 ist auf 256
     * Werten ungleich verteilt — die ersten 16 Zeichen des Vorrats kaemen
     * haeufiger vor als die letzten vier. random_int() verwirft
     * ueberzaehlige Werte und wuerfelt neu. Bei einem Wort, das eine
     * Identitaet binden soll, ist eine schiefe Verteilung kein
     * Schoenheitsfehler.
     */
    public static function codeErzeugen(): string
    {
        $vorrat = self::CODE_ZEICHEN;
        $letzte = strlen($vorrat) - 1;
        $gruppen = [];

        for ($g = 0; $g < self::CODE_GRUPPEN; ++$g) {
            $gruppe = '';

            for ($i = 0; $i < self::CODE_GRUPPENLAENGE; ++$i) {
                $gruppe .= $vorrat[random_int(0, $letzte)];
            }

            $gruppen[] = $gruppe;
        }

        return implode('-', $gruppen);
    }

    /** @return array<string,mixed>|null */
    private function vorgang(int $belegId): ?array
    {
        $zeile = $this->db->eine('SELECT * FROM pruefungsbelege WHERE id = :id', ['id' => $belegId]);

        return $zeile === null ? null : $this->zeileFormen($zeile);
    }

    /** Das Ablageverzeichnis eines Belegs, absolut. */
    private function belegverzeichnis(int $belegId): string
    {
        return $this->wurzel . '/' . $belegId;
    }

    /**
     * Schneidet das Belegverzeichnis vom vollen Pfad ab.
     *
     * Bleibt der Pfad unveraendert, lag er nicht unterhalb der Wurzel — dann
     * wird der volle Pfad gespeichert, und datei() weist ihn spaeter ab. Der
     * laute Ausgang, wie in Medien::relativ().
     */
    private function relativ(string $voll): string
    {
        $praefix = $this->wurzel . '/';

        return str_starts_with($voll, $praefix) ? substr($voll, strlen($praefix)) : $voll;
    }

    /**
     * Vereinheitlicht eine Zeile.
     *
     * SQLite liefert Ganzzahlen als int, MySQL ueber PDO als Zeichenkette.
     * Ohne diese Stelle stuende in der Vorlage einmal 1 und einmal '1', und
     * ein Vergleich mit === waere je nach Treiber wahr oder falsch.
     *
     * @param array<string,mixed> $zeile
     *
     * @return array<string,mixed>
     */
    private function zeileFormen(array $zeile): array
    {
        $geformt = $zeile;

        $geformt['id'] = (int) $zeile['id'];
        $geformt['benutzer_id'] = (int) $zeile['benutzer_id'];
        $geformt['status'] = (string) $zeile['status'];
        $geformt['code'] = (string) $zeile['code'];
        $geformt['verwalter_id'] = $zeile['verwalter_id'] === null ? null : (int) $zeile['verwalter_id'];
        $geformt['pruefung_id'] = $zeile['pruefung_id'] === null ? null : (int) $zeile['pruefung_id'];
        $geformt['pfad'] = $zeile['pfad'] === null ? null : (string) $zeile['pfad'];
        $geformt['vorschau_pfad'] = $zeile['vorschau_pfad'] === null ? null : (string) $zeile['vorschau_pfad'];

        return $geformt;
    }

    /**
     * Baut eine IN-Liste aus benannten Platzhaltern.
     *
     * @param list<string> $werte
     *
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function inListe(string $praefix, array $werte): array
    {
        $platzhalter = [];
        $parameter = [];

        foreach (array_values($werte) as $i => $wert) {
            $name = $praefix . $i;
            $platzhalter[] = ':' . $name;
            $parameter[$name] = $wert;
        }

        return [implode(', ', $platzhalter), $parameter];
    }

    /** Zeitpunkt in UTC, wie ihn das gesamte Schema erwartet. */
    private function zeitpunkt(?string $jetzt): string
    {
        return $jetzt ?? gmdate('Y-m-d H:i:s');
    }

    /**
     * Ein Zeitpunkt plus Sekunden, in UTC.
     *
     * Die Zeitzone wird ausdruecklich angehaengt, weil strtotime sonst die
     * lokale annimmt — und alle Zeitstempel im Schema sind UTC. Derselbe
     * Fallstrick wie in Verwaltung::vorTagen().
     *
     * @throws PruefbelegFehler 'zeitpunkt_ungueltig'
     */
    private function spaeter(string $jetzt, int $sekunden): string
    {
        $stempel = strtotime($jetzt . ' UTC');

        if ($stempel === false) {
            throw new PruefbelegFehler('zeitpunkt_ungueltig', 'Zeitpunkt "' . $jetzt . '" ist nicht lesbar.');
        }

        return gmdate('Y-m-d H:i:s', $stempel + $sekunden);
    }
}
