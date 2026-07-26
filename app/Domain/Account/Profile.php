<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Account;

use MeinSlip\Core\Database;

/**
 * Das Profil eines Kontos — Anzeigename, Vorstellung, Bild und die
 * Chatter-Deklaration.
 *
 * Eine eigene Tabelle im Verhaeltnis 1:1 zu 'benutzer', kein Spaltenanbau
 * dort. Die Begruendung steht in database/migrations/011_profile_und_chat.php:
 * Ddl kann kein ALTER TABLE, und tests/Testfall.php::benutzer() schreibt in
 * 'benutzer' eine feste Spaltenliste.
 *
 * DIE DEKLARATION IST DIE EIGENTLICHE FRACHT DIESER KLASSE. Sie beantwortet
 * die Frage, an der die Branche ihre Kaeufer verliert: "Rede ich mit ihr oder
 * mit einem Chatter?" (docs/04-features/chat-monetarisierung.md). Drei Werte,
 * keine Vorgabe, und die Nachricht traegt die Angabe als Momentaufnahme mit —
 * siehe Unterhaltungen::senden(). Deshalb hat sie eine eigene Methode und ist
 * ueber speichern() ausdruecklich NICHT erreichbar: Eine Behauptung ueber die
 * Urheberschaft ist kein Profilfeld wie ein Anzeigename, und ihre Pruefung
 * darf sich nicht dadurch umgehen lassen, dass jemand sie als Feld unter
 * anderen mitschickt.
 *
 * Zwei Sichten, die nicht dasselbe sind:
 *
 *  - laden() ist die Sicht der Person auf ihr EIGENES Profil. Sie legt die
 *    Zeile bei Bedarf an und liefert sie unabhaengig davon, ob das Profil
 *    oeffentlich ist.
 *  - nachPseudonym() ist die OEFFENTLICHE Nachschlage. Sie liefert nur, was
 *    wirklich oeffentlich sein soll — aktives Konto und oeffentlich = 1.
 *    Damit haengt die Sichtbarkeit an dieser Abfrage und nicht an der
 *    Sorgfalt jeder einzelnen Route.
 */
final class Profile
{
    /** Ausschliesslich die verifizierte Person schreibt selbst. */
    public const DEKLARATION_PERSON = 'person';

    /** Autorisierte Mitarbeitende schreiben mit. */
    public const DEKLARATION_TEAM = 'team';

    /** Antworten werden maschinell erzeugt oder vorgeschlagen. */
    public const DEKLARATION_KI = 'ki';

    /**
     * Die zulaessigen Werte.
     *
     * Es gibt bewusst KEINEN vierten Wert "keine Angabe". Wer nichts angibt,
     * hat in der Datenbank NULL stehen und darf nicht schreiben — eine
     * ausdrueckliche Angabe "keine Angabe" waere ein Weg, die Pflicht zu
     * erfuellen, ohne die Frage zu beantworten.
     *
     * @var list<string>
     */
    public const DEKLARATIONEN = [
        self::DEKLARATION_PERSON,
        self::DEKLARATION_TEAM,
        self::DEKLARATION_KI,
    ];

    /** Spaltenbreite von profile.anzeigename laut 011_profile_und_chat.php. */
    private const ANZEIGENAME_MAXLAENGE = 60;

    /**
     * Obergrenze der Vorstellung.
     *
     * Die Spalte ist TEXT und koennte mehr. Die Grenze schuetzt nicht das
     * Schema, sondern die Seite: Eine Vorstellung, die niemand zu Ende liest,
     * ist keine Vorstellung mehr.
     */
    private const VORSTELLUNG_MAXLAENGE = 2000;

    /** Spaltenbreite von profile.bild_pfad und profile.bild_vorschau_pfad. */
    private const PFAD_MAXLAENGE = 255;

    /**
     * Die ueber speichern() beschreibbaren Spalten.
     *
     * Diese Liste ist eine Sicherheitsgrenze, keine Bequemlichkeit:
     * Database::einfuegen() und das UPDATE hier bauen ihre Spaltennamen aus
     * Array-Schluesseln. Kaemen die Schluessel aus einem Formular, waere der
     * Spaltenteil der Anweisung nutzergesteuert. Deshalb entscheidet
     * ausschliesslich diese Konstante, was ueberhaupt eine Spalte werden kann.
     *
     * 'chat_deklaration' fehlt mit Absicht — sie hat ihre eigene Methode.
     *
     * @var list<string>
     */
    private const FELDER = [
        'anzeigename',
        'vorstellung',
        'oeffentlich',
        'bild_pfad',
        'bild_vorschau_pfad',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Das eigene Profil, bei Bedarf angelegt.
     *
     * Das Anlegen bei Bedarf statt bei der Registrierung: Konten::registrieren()
     * gehoert einem anderen Paket, und ein Profil, das erst beim ersten
     * Zugriff entsteht, gilt auch fuer die Konten, die es vor dieser Migration
     * schon gab. Ein Nachtragen per Migration waere derselbe Zustand mit einem
     * Datenschritt mehr.
     *
     * 'name' ist das Feld, das die Oberflaeche anzeigt: der Anzeigename, und
     * wenn keiner gesetzt ist, das Pseudonym. Der Rueckfall steht hier und
     * nicht in der Vorlage, damit nicht jede Seite ihn einzeln richtig
     * hinschreiben muss — und jede Seite, die ihn vergisst, einen leeren Namen
     * zeigt.
     *
     * @return array<string,mixed>
     *
     * @throws KontoFehler 'benutzer_unbekannt'
     */
    public function laden(int $benutzerId): array
    {
        $zeile = $this->zeile($benutzerId);

        if ($zeile === null) {
            $this->anlegen($benutzerId);
            $zeile = $this->zeile($benutzerId);
        }

        if ($zeile === null) {
            // anlegen() hat nichts geschrieben, also gibt es das Konto nicht.
            // Der Fremdschluessel haette dasselbe gesagt, aber als
            // PDOException — und die faengt oben niemand.
            throw new KontoFehler('benutzer_unbekannt');
        }

        return $this->aufbereiten($zeile);
    }

    /**
     * Schreibt die Profilfelder.
     *
     * Unbekannte Schluessel werden ABGEWIESEN, nicht ueberlesen. Ein
     * ueberlesener Tippfehler im Feldnamen heisst: Die Person speichert, die
     * Seite bestaetigt, und der Text ist trotzdem weg — bemerkt wird das nie.
     * Die Route soll ihre Felder deshalb ausdruecklich abbilden und nicht das
     * ganze Formular durchreichen (dort steckt ohnehin '_token' darin).
     *
     * @param array<string,mixed> $felder Erlaubt sind nur die Namen aus self::FELDER
     *
     * @throws KontoFehler 'benutzer_unbekannt', 'feld_unbekannt',
     *                     'anzeigename_zu_lang', 'vorstellung_zu_lang',
     *                     'bild_pfad_zu_lang'
     */
    public function speichern(int $benutzerId, array $felder): void
    {
        // Zuerst pruefen, dann anlegen: Sonst entstuende bei einer verworfenen
        // Eingabe trotzdem eine Profilzeile.
        $werte = [];

        foreach ($felder as $name => $wert) {
            if (!is_string($name) || !in_array($name, self::FELDER, true)) {
                throw new KontoFehler('feld_unbekannt');
            }

            $werte[$name] = $this->gepruefterWert($name, $wert);
        }

        if ($werte === []) {
            return;
        }

        $this->laden($benutzerId);

        $zuweisungen = [];

        foreach (array_keys($werte) as $name) {
            // Der Spaltenname stammt aus self::FELDER, nie aus der Eingabe —
            // die Schleife oben hat jeden anderen Namen bereits abgewiesen.
            $zuweisungen[] = $name . ' = :' . $name;
        }

        $zuweisungen[] = 'geaendert_am = :geaendert_am';
        $werte['geaendert_am'] = gmdate('Y-m-d H:i:s');
        $werte['benutzer_id'] = $benutzerId;

        $this->db->ausfuehren(
            'UPDATE profile SET ' . implode(', ', $zuweisungen) . ' WHERE benutzer_id = :benutzer_id',
            $werte
        );
    }

    /**
     * Setzt die Chatter-Deklaration.
     *
     * Eigene Methode statt eines Feldes in speichern(): Die Angabe ist eine
     * Behauptung ueber die Urheberschaft jeder kuenftigen Nachricht, keine
     * Profilangabe. Sie bekommt deshalb eine Stelle, an der ihre Pruefung
     * nicht zu umgehen ist.
     *
     * @throws KontoFehler 'deklaration_unbekannt', 'benutzer_unbekannt'
     */
    public function deklarationSetzen(int $benutzerId, string $wert): void
    {
        $wert = trim($wert);

        if (!in_array($wert, self::DEKLARATIONEN, true)) {
            throw new KontoFehler('deklaration_unbekannt');
        }

        $this->laden($benutzerId);

        $this->db->ausfuehren(
            'UPDATE profile SET chat_deklaration = :wert, geaendert_am = :jetzt WHERE benutzer_id = :b',
            ['wert' => $wert, 'jetzt' => gmdate('Y-m-d H:i:s'), 'b' => $benutzerId]
        );
    }

    /**
     * Die gesetzte Deklaration, oder null.
     *
     * Legt bewusst NICHTS an: Diese Methode laeuft bei jedem Senden, und ein
     * Leseweg, der schreibt, waere an dieser Stelle sowohl teuer als auch
     * ueberraschend. null heisst "noch nichts gesagt" — Unterhaltungen::senden()
     * weist dann mit 'deklaration_fehlt' ab.
     */
    public function deklaration(int $benutzerId): ?string
    {
        $wert = $this->db->wert(
            'SELECT chat_deklaration FROM profile WHERE benutzer_id = :b',
            ['b' => $benutzerId]
        );

        if (!is_string($wert) || $wert === '') {
            return null;
        }

        return $wert;
    }

    /**
     * Die oeffentliche Nachschlage ueber das Pseudonym — fuer /p/{pseudonym}.
     *
     * Liefert null, wenn es das Konto nicht gibt, es nicht aktiv ist oder das
     * Profil nicht oeffentlich geschaltet wurde. Die Sichtbarkeitsregel sitzt
     * damit in der Abfrage und nicht in der Vorlage: Eine Seite, die die
     * Pruefung vergisst, bekommt gar keine Daten statt zu viele.
     *
     * Der Vergleich auf das Pseudonym ist ein einfaches '=' und damit auf
     * MySQL (utf8mb4_unicode_ci) gross-/kleinschreibungsunabhaengig, auf
     * SQLite nicht. Der Unterschied ist hier hinnehmbar, weil das Pseudonym
     * aus einem Verweis stammt, den die Plattform selbst erzeugt hat. LOWER()
     * auf beiden Seiten waere schlechter: Es kennt in SQLite nur ASCII, waere
     * also fuer genau die Pseudonyme falsch, fuer die es gedacht ist, und
     * verhinderte nebenbei die Nutzung des eindeutigen Index.
     *
     * @return array<string,mixed>|null
     */
    public function nachPseudonym(string $pseudonym): ?array
    {
        $pseudonym = trim($pseudonym);

        if ($pseudonym === '') {
            return null;
        }

        $zeile = $this->db->eine(
            'SELECT p.id, p.benutzer_id, p.anzeigename, p.vorstellung, p.chat_deklaration,
                    p.bild_pfad, p.bild_vorschau_pfad, p.oeffentlich, p.angelegt_am, p.geaendert_am,
                    b.pseudonym
               FROM benutzer b
               JOIN profile p ON p.benutzer_id = b.id
              WHERE b.pseudonym = :p
                AND b.status = :status
                AND p.oeffentlich = 1',
            ['p' => $pseudonym, 'status' => 'aktiv']
        );

        if ($zeile === null) {
            return null;
        }

        return $this->aufbereiten($zeile);
    }

    // --- intern ------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function zeile(int $benutzerId): ?array
    {
        return $this->db->eine(
            'SELECT p.id, p.benutzer_id, p.anzeigename, p.vorstellung, p.chat_deklaration,
                    p.bild_pfad, p.bild_vorschau_pfad, p.oeffentlich, p.angelegt_am, p.geaendert_am,
                    b.pseudonym
               FROM profile p
               JOIN benutzer b ON b.id = p.benutzer_id
              WHERE p.benutzer_id = :b',
            ['b' => $benutzerId]
        );
    }

    /**
     * Legt die Profilzeile an — nur, wenn es das Konto gibt und noch keine da
     * ist.
     *
     * INSERT ... SELECT ... WHERE NOT EXISTS statt "pruefen, dann schreiben":
     * Die Doppeltenpruefung liegt damit in derselben Anweisung wie das
     * Einfuegen und kann nicht zwischen Pruefung und Schreiben veralten. Und
     * das fehlende Konto braucht keine eigene Abfrage — die Anweisung
     * schreibt dann einfach nichts, und laden() erkennt das am erneut leeren
     * Ergebnis.
     *
     * Der Wettlauf zweier gleichzeitiger Anfragen bleibt trotzdem moeglich;
     * dann gewinnt der eindeutige Index. Die PDOException wird geschluckt,
     * weil das Ergebnis genau das gewuenschte ist: Es gibt eine Zeile.
     */
    private function anlegen(int $benutzerId): void
    {
        try {
            $this->db->ausfuehren(
                'INSERT INTO profile (benutzer_id, oeffentlich, angelegt_am)'
                . ' SELECT b.id, 0, :jetzt FROM benutzer b'
                . ' WHERE b.id = :b'
                . ' AND NOT EXISTS (SELECT 1 FROM profile p WHERE p.benutzer_id = b.id)',
                ['jetzt' => gmdate('Y-m-d H:i:s'), 'b' => $benutzerId]
            );
        } catch (\PDOException) {
            // Ein anderer Aufruf war schneller. Das Ziel ist erreicht.
        }
    }

    /**
     * @param array<string,mixed> $zeile
     *
     * @return array<string,mixed>
     */
    private function aufbereiten(array $zeile): array
    {
        $anzeigename = is_string($zeile['anzeigename'] ?? null) ? trim((string) $zeile['anzeigename']) : '';
        $pseudonym = (string) ($zeile['pseudonym'] ?? '');

        return [
            'id' => (int) $zeile['id'],
            'benutzer_id' => (int) $zeile['benutzer_id'],
            'pseudonym' => $pseudonym,
            'anzeigename' => $anzeigename === '' ? null : $anzeigename,
            // Der Rueckfall auf das Pseudonym — die Oberflaeche zeigt 'name'.
            'name' => $anzeigename === '' ? $pseudonym : $anzeigename,
            'vorstellung' => $zeile['vorstellung'],
            'chat_deklaration' => $zeile['chat_deklaration'],
            'bild_pfad' => $zeile['bild_pfad'],
            'bild_vorschau_pfad' => $zeile['bild_vorschau_pfad'],
            'oeffentlich' => (int) $zeile['oeffentlich'] === 1,
            'angelegt_am' => $zeile['angelegt_am'],
            'geaendert_am' => $zeile['geaendert_am'],
        ];
    }

    /**
     * @throws KontoFehler
     */
    private function gepruefterWert(string $name, mixed $wert): mixed
    {
        if ($name === 'oeffentlich') {
            // Nicht (bool) auf den Rohwert: '0' aus einem Formular waere
            // wahr. Die Oberflaeche schickt bei einem Kaestchen entweder
            // nichts oder '1'.
            return in_array($wert, [true, 1, '1', 'ja', 'on'], true) ? 1 : 0;
        }

        $text = is_string($wert) ? trim($wert) : '';

        if ($name === 'anzeigename') {
            if (mb_strlen($text) > self::ANZEIGENAME_MAXLAENGE) {
                throw new KontoFehler('anzeigename_zu_lang');
            }

            return $text === '' ? null : $text;
        }

        if ($name === 'vorstellung') {
            if (mb_strlen($text) > self::VORSTELLUNG_MAXLAENGE) {
                throw new KontoFehler('vorstellung_zu_lang');
            }

            return $text === '' ? null : $text;
        }

        // bild_pfad und bild_vorschau_pfad. Der Pfad entsteht im Medienpaket
        // aus random_bytes und nie aus einem Clientnamen; hier wird nur die
        // Spaltenbreite gehalten.
        if (mb_strlen($text) > self::PFAD_MAXLAENGE) {
            throw new KontoFehler('bild_pfad_zu_lang');
        }

        return $text === '' ? null : $text;
    }
}
