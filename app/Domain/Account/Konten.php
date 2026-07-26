<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Account;

use MeinSlip\Core\Database;

/**
 * Registrierung, Anmeldung und Faehigkeiten.
 *
 * Grundsatz aus docs/08-architektur.md: EINE Registrierung, alle Rollen.
 * Es gibt keine getrennten Kaeufer- und Verkaeuferkonten. Wer sich
 * registriert, bekommt ein Konto; die Berechtigungen haengen als Faehigkeiten
 * daran — dieselbe Person, dasselbe Konto, zusaetzliche Rechte.
 *
 * Die drei Faehigkeiten entstehen auf drei verschiedenen Wegen:
 *
 * - 'verkaufen' kommt mit der Registrierung. Frueher war sie das Tor, das die
 *   Verwaltung erst aufstossen musste; das war ein Modell fuer eine Plattform
 *   mit Personal. Aus dem Tor ist ein Sanktionsgriff geworden: entzogen wird
 *   sie ueber Verwaltung::faehigkeitEntziehen(), das die Entscheidung
 *   protokolliert und der betroffenen Person nach Art. 17 DSA zustellt. Das
 *   ist das mildere Mittel neben der Kontosperre — und es wirkt erst auf
 *   Meldung hin (Art. 16 DSA), nicht vorab gegen alle.
 * - 'kaufen' wird NICHT mitvergeben. Sie steht fuer die Altersverifikation
 *   nach § 4 Abs. 2 JMStV, deren lizenziertes Verfahren noch nicht angebunden
 *   ist (docs/11-offene-fragen.md, Punkt 4).
 * - 'verwalten' erst recht nicht — siehe die Konstante weiter unten.
 */
final class Konten
{
    public const FAEHIGKEIT_KAUFEN = 'kaufen';
    public const FAEHIGKEIT_VERKAUFEN = 'verkaufen';

    /**
     * Zugang zum Verwaltungsbereich.
     *
     * Wird niemals durch eine Pruefung, eine Registrierung oder irgendeinen
     * Weg ueber das Web vergeben, sondern ausschliesslich von Hand ueber
     * bin/verwalter. Wer sie vergeben kann, hat bereits Zugriff auf den
     * Server — das ist die Absicherung.
     */
    public const FAEHIGKEIT_VERWALTEN = 'verwalten';

    /**
     * Alle bekannten Faehigkeiten.
     *
     * Ohne diese Liste nimmt faehigkeitFreischalten() jede Zeichenkette an und
     * ein Tippfehler ('verwaltn') legt still eine wirkungslose Zeile an, die
     * niemandem auffaellt, weil hatFaehigkeit() weiter false liefert.
     *
     * @var list<string>
     */
    public const FAEHIGKEITEN = [
        self::FAEHIGKEIT_KAUFEN,
        self::FAEHIGKEIT_VERKAUFEN,
        self::FAEHIGKEIT_VERWALTEN,
    ];

    /**
     * Grundlage der bei der Registrierung vergebenen Verkaufsfaehigkeit.
     *
     * Die Spalte benutzer_faehigkeiten.grundlage ist ein schluesselwort ohne
     * Datenbank-Enum, ein neuer Wert braucht also keine Migration. Als
     * Konstante steht er trotzdem an genau einer Stelle: nur so laesst sich
     * spaeter aufzaehlen, welche Konten die Faehigkeit allein durch das
     * Registrieren haben — und welche durch eine echte Pruefung.
     */
    public const GRUNDLAGE_REGISTRIERUNG = 'registrierung';

    private const PSEUDONYM_MUSTER = '/^[\p{L}\p{N}_-]{3,30}$/u';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Legt ein Konto an und schaltet die Verkaufsfaehigkeit frei.
     *
     * Registrieren genuegt: das Konto darf sofort einstellen und wird sofort
     * gesehen. Bewusst NICHT mitvergeben werden 'kaufen' (Altersverifikation,
     * § 4 Abs. 2 JMStV, Verfahren noch nicht angebunden) und 'verwalten'.
     *
     * Beides — Kontozeile und Faehigkeit — laeuft in EINER Transaktion. Bricht
     * der zweite Schritt ab, darf der erste nicht stehen bleiben: sonst
     * entstuende ein Konto, das nicht verkaufen darf, ohne dass es jemals
     * jemand entschieden haette. Der Fehler waere unsichtbar, weil das Konto
     * sich anmelden kann und erst beim Einstellen abgewiesen wird.
     *
     * @throws KontoFehler
     */
    public function registrieren(string $pseudonym, string $email, string $passwort): int
    {
        $pseudonym = trim($pseudonym);
        $email = strtolower(trim($email));

        if (!preg_match(self::PSEUDONYM_MUSTER, $pseudonym)) {
            throw new KontoFehler('pseudonym_ungueltig');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new KontoFehler('email_ungueltig');
        }

        // Mindestens zwoelf Zeichen statt Zeichenklassen-Regeln: Laenge traegt
        // mehr zur Sicherheit bei als erzwungene Sonderzeichen, und sie
        // erzeugt keine Passwoerter, die sich niemand merken kann.
        if (mb_strlen($passwort) < 12) {
            throw new KontoFehler('passwort_zu_kurz');
        }

        if ($this->emailVergeben($email)) {
            throw new KontoFehler('email_vergeben');
        }

        if ($this->pseudonymVergeben($pseudonym)) {
            throw new KontoFehler('pseudonym_vergeben');
        }

        return $this->db->transaktion(function () use ($pseudonym, $email, $passwort): int {
            $id = $this->db->einfuegen('benutzer', [
                'pseudonym' => $pseudonym,
                'email' => $email,
                'passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT),
                'status' => 'aktiv',
                'sprache' => 'de-DE',
                'land' => 'DE',
                'homescreen_name' => null,
                'push_vorschau' => 0,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
                'zuletzt_aktiv_am' => null,
            ]);

            $this->faehigkeitFreischalten($id, self::FAEHIGKEIT_VERKAUFEN, self::GRUNDLAGE_REGISTRIERUNG);

            return $id;
        });
    }

    /**
     * Prueft Anmeldedaten.
     *
     * @return array<string,mixed>|null Das Konto, oder null bei falschen Daten
     */
    public function anmelden(string $email, string $passwort): ?array
    {
        $email = strtolower(trim($email));

        $konto = $this->db->eine(
            'SELECT * FROM benutzer WHERE email = :e AND status = :s',
            ['e' => $email, 's' => 'aktiv']
        );

        if ($konto === null) {
            // Auch ohne Treffer einmal hashen, damit die Antwortzeit nicht
            // verraet, ob die Adresse existiert.
            password_verify($passwort, '$2y$12$usercheckusercheckuserchecku');

            return null;
        }

        if (!password_verify($passwort, (string) $konto['passwort_hash'])) {
            return null;
        }

        // Hash-Verfahren nachziehen, falls PHP inzwischen einen besseren kennt.
        if (password_needs_rehash((string) $konto['passwort_hash'], PASSWORD_DEFAULT)) {
            $this->db->ausfuehren(
                'UPDATE benutzer SET passwort_hash = :h WHERE id = :id',
                ['h' => password_hash($passwort, PASSWORD_DEFAULT), 'id' => $konto['id']]
            );
        }

        $this->db->ausfuehren(
            'UPDATE benutzer SET zuletzt_aktiv_am = :z WHERE id = :id',
            ['z' => gmdate('Y-m-d H:i:s'), 'id' => $konto['id']]
        );

        return $konto;
    }

    /** @return array<string,mixed>|null */
    public function laden(int $benutzerId): ?array
    {
        return $this->db->eine('SELECT * FROM benutzer WHERE id = :id', ['id' => $benutzerId]);
    }

    /**
     * Konto zu einer E-Mail-Adresse, unabhaengig vom Status.
     *
     * Anders als anmelden() filtert diese Abfrage NICHT auf status = 'aktiv':
     * die Kommandozeile muss auch ein gesperrtes Konto finden koennen, um ihm
     * die Verwaltung zu entziehen.
     *
     * @return array<string,mixed>|null
     */
    public function nachEmail(string $email): ?array
    {
        return $this->db->eine(
            'SELECT * FROM benutzer WHERE email = :e',
            ['e' => strtolower(trim($email))]
        );
    }

    /** @return list<string> */
    public static function bekannteFaehigkeiten(): array
    {
        return self::FAEHIGKEITEN;
    }

    public static function faehigkeitBekannt(string $faehigkeit): bool
    {
        return in_array($faehigkeit, self::FAEHIGKEITEN, true);
    }

    /** @throws KontoFehler wenn die Faehigkeit unbekannt ist */
    public function faehigkeitFreischalten(int $benutzerId, string $faehigkeit, string $grundlage): void
    {
        $this->faehigkeitPruefen($faehigkeit);

        if ($this->hatFaehigkeit($benutzerId, $faehigkeit)) {
            return;
        }

        $jetzt = gmdate('Y-m-d H:i:s');

        // Der eindeutige Index ueber (benutzer_id, faehigkeit) laesst keine
        // zweite Zeile zu. Eine entzogene Faehigkeit wird deshalb wiederbelebt
        // statt neu eingefuegt — sonst scheitert jedes erneute Freischalten am
        // Index, obwohl fachlich nichts dagegen spricht.
        if ($this->faehigkeitszeileVorhanden($benutzerId, $faehigkeit)) {
            $this->db->ausfuehren(
                'UPDATE benutzer_faehigkeiten
                    SET entzogen_am = NULL, grundlage = :g, freigeschaltet_am = :z
                  WHERE benutzer_id = :b AND faehigkeit = :f',
                ['g' => $grundlage, 'z' => $jetzt, 'b' => $benutzerId, 'f' => $faehigkeit]
            );

            return;
        }

        $this->db->einfuegen('benutzer_faehigkeiten', [
            'benutzer_id' => $benutzerId,
            'faehigkeit' => $faehigkeit,
            'grundlage' => $grundlage,
            'freigeschaltet_am' => $jetzt,
            'entzogen_am' => null,
        ]);
    }

    /**
     * Entzieht eine Faehigkeit.
     *
     * Die Zeile bleibt stehen und bekommt nur entzogen_am gesetzt: dass jemand
     * eine Berechtigung einmal hatte und wann sie endete, muss nachvollziehbar
     * bleiben. Loeschen wuerde diese Spur vernichten.
     *
     * Mehrfaches Entziehen ist folgenlos — die Bedingung entzogen_am IS NULL
     * trifft beim zweiten Aufruf keine Zeile mehr, der erste Zeitpunkt bleibt.
     *
     * @throws KontoFehler wenn die Faehigkeit unbekannt ist
     */
    public function faehigkeitEntziehen(int $benutzerId, string $faehigkeit): void
    {
        $this->faehigkeitPruefen($faehigkeit);

        $this->db->ausfuehren(
            'UPDATE benutzer_faehigkeiten SET entzogen_am = :z
              WHERE benutzer_id = :b AND faehigkeit = :f AND entzogen_am IS NULL',
            ['z' => gmdate('Y-m-d H:i:s'), 'b' => $benutzerId, 'f' => $faehigkeit]
        );
    }

    public function hatFaehigkeit(int $benutzerId, string $faehigkeit): bool
    {
        $treffer = $this->db->wert(
            'SELECT COUNT(*) FROM benutzer_faehigkeiten
              WHERE benutzer_id = :b AND faehigkeit = :f AND entzogen_am IS NULL',
            ['b' => $benutzerId, 'f' => $faehigkeit]
        );

        return (int) $treffer > 0;
    }

    /** @return list<string> */
    public function faehigkeiten(int $benutzerId): array
    {
        $zeilen = $this->db->alle(
            'SELECT faehigkeit FROM benutzer_faehigkeiten
              WHERE benutzer_id = :b AND entzogen_am IS NULL',
            ['b' => $benutzerId]
        );

        return array_map(static fn (array $z): string => (string) $z['faehigkeit'], $zeilen);
    }

    /**
     * Alle Konten, die eine Faehigkeit derzeit besitzen.
     *
     * Gedacht fuer bin/verwalter: wer den Verwaltungsbereich betreten darf,
     * muss von aussen aufzaehlbar sein, sonst weiss niemand, wer Zugriff hat.
     *
     * @return list<array<string,mixed>>
     *
     * @throws KontoFehler wenn die Faehigkeit unbekannt ist
     */
    public function mitFaehigkeit(string $faehigkeit): array
    {
        $this->faehigkeitPruefen($faehigkeit);

        return $this->db->alle(
            'SELECT b.id, b.pseudonym, b.email, b.status, f.grundlage, f.freigeschaltet_am
               FROM benutzer_faehigkeiten f
               JOIN benutzer b ON b.id = f.benutzer_id
              WHERE f.faehigkeit = :f AND f.entzogen_am IS NULL
              ORDER BY b.pseudonym',
            ['f' => $faehigkeit]
        );
    }

    /** @throws KontoFehler */
    private function faehigkeitPruefen(string $faehigkeit): void
    {
        if (!self::faehigkeitBekannt($faehigkeit)) {
            throw new KontoFehler('faehigkeit_unbekannt');
        }
    }

    /** Auch entzogene Zeilen zaehlen — im Gegensatz zu hatFaehigkeit(). */
    private function faehigkeitszeileVorhanden(int $benutzerId, string $faehigkeit): bool
    {
        return (int) $this->db->wert(
            'SELECT COUNT(*) FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND faehigkeit = :f',
            ['b' => $benutzerId, 'f' => $faehigkeit]
        ) > 0;
    }

    private function emailVergeben(string $email): bool
    {
        return $this->db->wert('SELECT COUNT(*) FROM benutzer WHERE email = :e', ['e' => $email]) > 0;
    }

    private function pseudonymVergeben(string $pseudonym): bool
    {
        // Ohne Beachtung der Gross- und Kleinschreibung, damit "Lina" und
        // "lina" nicht nebeneinander existieren und verwechselt werden.
        return $this->db->wert(
            'SELECT COUNT(*) FROM benutzer WHERE LOWER(pseudonym) = LOWER(:p)',
            ['p' => $pseudonym]
        ) > 0;
    }
}
