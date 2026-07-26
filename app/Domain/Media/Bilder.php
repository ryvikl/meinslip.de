<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Media;

/**
 * Die Aufnahme hochgeladener Bilder.
 *
 * Vor dieser Klasse gab es im ganzen Projekt kein $_FILES, kein
 * move_uploaded_file und kein enctype. Das ist der Grund, warum hier so viel
 * Erklaerung steht: Es gibt kein Vorbild im Haus, an dem sich die naechste
 * Aenderung ausrichten koennte.
 *
 * DIE WICHTIGSTE ZEILE DER KLASSE IST DIE NEUKODIERUNG. Das Original wird
 * gelesen, dekodiert und aus dem dekodierten Bild NEU geschrieben — die
 * hochgeladenen Bytes landen NIE auf der Platte, und move_uploaded_file()
 * kommt in dieser Datei deshalb nicht vor. Drei Dinge erledigt dieser eine
 * Schritt auf einmal, die sich einzeln nur schlecht erledigen liessen:
 *
 *  1. ER ENTFERNT EXIF. Ein Waeschefoto mit den Koordinaten der eigenen
 *     Wohnung ist der schlimmste denkbare Abfluss dieser Plattform — schlimmer
 *     als jedes Passwort, weil er sich nicht zuruecknehmen laesst. Jede
 *     Handykamera schreibt GPS hinein, wenn die Nutzerin es einmal erlaubt
 *     hat. Ein neu kodiertes JPEG hat keinen APP1-Abschnitt mehr, also auch
 *     keine Koordinaten, keine Seriennummer der Kamera und keine Uhrzeit.
 *  2. ER TOETET POLYGLOT-DATEIEN. Eine Datei aus gueltigem JPEG-Kopf plus
 *     angehaengtem PHP- oder HTML-Text besteht jede Signaturpruefung. Nach dem
 *     Neuschreiben ist der Anhang weg, weil er nie Teil des Bildes war.
 *  3. ER TOETET EINGEBETTETE SKRIPTE. Was ein Dekodierer nicht als Bildpunkt
 *     versteht, kann er nicht zurueckschreiben.
 *
 * DESHALB IST DIESE KLASSE VERSCHLOSSEN, WENN GD FEHLT. Ohne GD liesse sich
 * ein Bild nur roh speichern — und ein rohes Original ist nicht nur
 * ungeprueft, es hat auch keine erzeugbare Unschaerfe. Fuer die oeffentliche
 * Zone gaebe es dann kein zulaessiges Bild, nur das scharfe Original oder gar
 * keins. Fail closed: annehmen() weist ab, bevor es irgendetwas anfasst.
 *
 * DIE VORSCHAU WIRD SERVERSEITIG INFORMATIONSVERNICHTEND ERZEUGT: erst auf 24
 * Pixel lange Kante herunter, dann auf 640 Pixel hoch, dann zweimal
 * weichgezeichnet. Nach dem ersten Schritt sind die Bildpunkte tatsaechlich
 * fort, nicht nur unkenntlich gemacht. Ein CSS-Filter waere wirkungslos, weil
 * das scharfe Original dafuer im Browser liegen muesste — public/assets/css/
 * app.css sagt das an seiner Weichzeichnerregel bereits.
 *
 * Was diese Klasse NICHT tut: Sie schreibt nichts in die Datenbank, kennt
 * weder Angebot noch Benutzer und entscheidet nicht ueber 'explizit'. Sie
 * nimmt eine Datei entgegen und gibt zwei Pfade zurueck. Die Zuordnung, die
 * Berechtigung und die Auslieferung gehoeren in die Medienroute.
 */
final class Bilder
{
    /**
     * Der Wert fuer angebot_medien.art.
     *
     * Es gibt nur einen: Nach der Neukodierung IST jedes Medium ein JPEG,
     * gleich was hochgeladen wurde. Die Spalte haelt die Gattung fest, nicht
     * das Format — Video oder Ton bekaemen spaeter eigene Werte.
     */
    public const ART_BILD = 'bild';

    /**
     * Die Byte-Grenze der Einzeldatei: 8 MiB.
     *
     * Sie liegt bewusst UNTER dem, was ein aktuelles Handy produziert (10 bis
     * 15 MB bei 48 Megapixeln), und das ist Absicht: Nach der Verkleinerung
     * auf 1600 Pixel bleiben ohnehin nur ein paar hundert Kilobyte uebrig, ein
     * groesserer Upload kostet also nur Uebertragung und Speicher waehrend der
     * Verarbeitung. Wer die Grenze anhebt, muss upload_max_filesize UND
     * post_max_size in der php.ini mit anheben — sonst greift die Grenze nie,
     * weil PHP schon vorher abbricht.
     */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Die Pixelgrenze gegen Dekompressionsbomben: 40 Megapixel.
     *
     * Ein PNG von 60000 x 60000 Punkten passt in wenige Kilobyte, belegt beim
     * Dekodieren aber 14 Gigabyte. Geprueft wird deshalb die im DATEIKOPF
     * angegebene Groesse, VOR dem Dekodieren. 40 MP liegen weit ueber jeder
     * Handykamera (48 MP Sensoren speichern regulaer 12 MP) und weit unter
     * dem, was Speicher kostet.
     */
    public const MAX_PIXEL = 40000000;

    /** Die lange Kante des gespeicherten Bildes. */
    public const MAX_KANTE = 1600;

    /**
     * Die lange Kante des Zwischenschritts der Vorschau.
     *
     * 24 Pixel sind der eigentliche Wirkstoff: Was auf 24 Pixel gerechnet
     * wurde, ist verloren. Alles danach ist Kosmetik.
     */
    public const VORSCHAU_KERN = 24;

    /** Die lange Kante der ausgelieferten Vorschau. */
    public const VORSCHAU_KANTE = 640;

    /** JPEG-Qualitaet des gespeicherten Bildes. */
    private const QUALITAET = 82;

    /** JPEG-Qualitaet der Vorschau — sie traegt ohnehin keine Details mehr. */
    private const QUALITAET_VORSCHAU = 70;

    /**
     * Sicherheitsabstand zum Speicherlimit in Bytes, siehe speicherbedarf().
     */
    private const SPEICHER_RESERVE = 16 * 1024 * 1024;

    /**
     * Die erlaubten Bildtypen und der MIME-Typ, den finfo dazu melden MUSS.
     *
     * Alles, was hier fehlt, ist abgewiesen — und das ist die vollstaendige
     * Begruendung fuer drei Formate, nach denen sonst gefragt wird:
     *
     *  - GIF fehlt, weil GIF animiert sein kann und imagecreatefromgif nur das
     *    erste Einzelbild liest. Der Upload waere dann etwas anderes als das,
     *    was die Hochladende gesehen hat.
     *  - SVG fehlt, weil SVG kein Bild ist, sondern ein XML-Dokument mit
     *    <script>-Unterstuetzung. Es gibt keinen Rasterisierer in GD, also
     *    auch keine Neukodierung, also keine Entschaerfung. getimagesize()
     *    weist SVG ohnehin ab — der Eintrag fehlt trotzdem ausdruecklich,
     *    damit niemand ihn spaeter "nachtraegt".
     *  - AVIF und HEIC fehlen, obwohl iPhones HEIC liefern. Sie liessen sich
     *    ergaenzen, sobald der Server sie zuverlaessig dekodiert; bis dahin
     *    wandelt iOS beim Hochladen selbst nach JPEG.
     *
     * @var array<int,string>
     */
    private const ERLAUBT = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    /**
     * Der Dekodierer je Typ.
     *
     * Getrennt von ERLAUBT, weil er einzeln fehlen kann: imagecreatefromwebp
     * ist in manchen Distributionsbauten von GD nicht enthalten, obwohl
     * extension_loaded('gd') true meldet. Genau deshalb sondiert formate()
     * Funktion fuer Funktion statt die Erweiterung als Ganzes zu glauben.
     *
     * @var array<int,string>
     */
    private const LESER = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    /**
     * Was ohne Rueckfall gebraucht wird — unabhaengig vom Eingabeformat.
     *
     * @var list<string>
     */
    private const GRUNDFUNKTIONEN = [
        'imagecreatetruecolor',
        'imagecopyresampled',
        'imagefilter',
        'imagejpeg',
        'imagesx',
        'imagesy',
        'imagedestroy',
    ];

    /**
     * Die Pruefung auf einen echten Upload.
     *
     * @var callable(string):bool
     */
    private $istHochgeladen;

    /**
     * WARUM DIESE PRUEFUNG AUSTAUSCHBAR IST, obwohl sie sicherheitsrelevant
     * ist: is_uploaded_file() liefert ausserhalb einer echten HTTP-Anfrage
     * IMMER false. Ohne diese eine Naht liesse sich die gesamte Aufnahmekette
     * nur von Hand im Browser pruefen — also in der Praxis nie.
     *
     * Die Voreinstellung ist die echte Pruefung. Wer sie ersetzt, tut das
     * sichtbar in einer eigenen Zeile und kann sich nicht darauf berufen, es
     * nicht gewusst zu haben. In app/ darf das nirgends vorkommen; der Aufruf
     * lautet dort schlicht new Bilder().
     *
     * @param (callable(string):bool)|null $istHochgeladen nur fuer Tests
     */
    public function __construct(?callable $istHochgeladen = null)
    {
        $this->istHochgeladen = $istHochgeladen ?? static fn (string $pfad): bool => is_uploaded_file($pfad);
    }

    /**
     * Kann dieser Server Bilder verarbeiten?
     *
     * Geprueft und nicht vorausgesetzt, obwohl composer.json ext-gd und
     * ext-fileinfo verlangt: Eine Composer-Anforderung wirkt beim Installieren
     * mit Composer. Ein per FTP hochgeladenes Verzeichnis, ein Wechsel der
     * PHP-Version im Hosting-Menue oder ein Wiederherstellen aus dem
     * Sicherungsband haben sie nie gesehen. Die Behauptung steht in
     * composer.json, der Beweis steht hier, und die Einrichtungsseite fragt
     * danach.
     */
    public static function verfuegbar(): bool
    {
        if (!extension_loaded('gd') || !extension_loaded('fileinfo')) {
            return false;
        }

        foreach (self::GRUNDFUNKTIONEN as $funktion) {
            if (!function_exists($funktion)) {
                return false;
            }
        }

        return self::formate() !== [];
    }

    /**
     * Die MIME-Typen, die dieser Server tatsaechlich lesen kann.
     *
     * Fuer das accept-Attribut des Formulars und fuer die Fehlermeldung. Der
     * Unterschied zu ERLAUBT ist der Fallstrick aus LESER: Ein GD-Bau ohne
     * WebP meldet hier nur zwei Typen, und ein WebP-Upload wird dann mit
     * 'format_nicht_unterstuetzt' abgewiesen statt mit einem Fatal Error
     * ueber eine undefinierte Funktion.
     *
     * @return list<string>
     */
    public static function formate(): array
    {
        $formate = [];

        foreach (self::ERLAUBT as $typ => $mime) {
            if (function_exists(self::LESER[$typ])) {
                $formate[] = $mime;
            }
        }

        return $formate;
    }

    /**
     * Nimmt eine hochgeladene Datei an und legt Bild und Vorschau ab.
     *
     * $datei ist ein Eintrag aus $_FILES: name, type, tmp_name, error, size.
     * 'name' und 'type' werden dabei NICHT verwendet — beide kommen vom
     * Client und sind frei erfunden. Der Dateiname entsteht hier aus
     * random_bytes(), der Typ aus dem Inhalt.
     *
     * Zurueck kommen die vollen Pfade der beiden geschriebenen Dateien. Was
     * davon in angebot_medien.pfad landet, entscheidet die Route: Sinnvoll ist
     * der Teil unterhalb des Medienwurzelverzeichnisses, damit ein Umzug des
     * Servers die Zeilen nicht entwertet.
     *
     * @param array<string,mixed> $datei ein Eintrag aus $_FILES
     * @return array{pfad: string, vorschau_pfad: string, art: string}
     * @throws MedienFehler
     */
    public function annehmen(array $datei, string $zielVerzeichnis): array
    {
        // Schritt 0: Fail closed. Ohne Bildverarbeitung wird nichts
        // gespeichert — auch nicht roh. Ein Original ohne erzeugbare
        // Unschaerfe haette fuer die oeffentliche Zone kein zulaessiges Bild,
        // und ein ungeprueftes Original haette gar nichts fuer sich.
        if (!self::verfuegbar()) {
            throw new MedienFehler('bildverarbeitung_fehlt', 'GD oder fileinfo fehlt auf diesem Server.');
        }

        $temporaer = $this->quelldateiPruefen($datei);
        [$breite, $hoehe, $typ] = $this->typPruefen($temporaer);
        $this->massePruefen($breite, $hoehe);

        return $this->schreiben($temporaer, $typ, $zielVerzeichnis);
    }

    // --- Aufnahmekette -----------------------------------------------------

    /**
     * Schritte 1 bis 3: Transportfehler, echter Upload, Byte-Grenze.
     *
     * @param array<string,mixed> $datei
     * @throws MedienFehler
     */
    private function quelldateiPruefen(array $datei): string
    {
        $fehler = isset($datei['error']) && is_int($datei['error']) ? $datei['error'] : UPLOAD_ERR_NO_FILE;

        // Schritt 1: Die beiden Groessenfehler bekommen einen EIGENEN
        // Schluessel. Sie sind ein Bedienfehler — die Datei war zu gross fuer
        // php.ini oder fuer MAX_FILE_SIZE des Formulars —, kein
        // Angriffsversuch. Wer sie mit UPLOAD_ERR_PARTIAL in einen Topf wirft,
        // beantwortet ein Handyfoto mit "Der Upload ist fehlgeschlagen" und
        // laesst die Nutzerin es dreimal wiederholen.
        if ($fehler === UPLOAD_ERR_INI_SIZE || $fehler === UPLOAD_ERR_FORM_SIZE) {
            throw new MedienFehler('upload_zu_gross', 'PHP hat den Upload wegen seiner Groesse abgebrochen.');
        }

        if ($fehler !== UPLOAD_ERR_OK) {
            throw new MedienFehler('upload_fehlgeschlagen', 'Upload-Fehlercode ' . $fehler . '.');
        }

        $temporaer = isset($datei['tmp_name']) && is_string($datei['tmp_name']) ? $datei['tmp_name'] : '';

        // Schritt 2: is_uploaded_file() ist PFLICHT und nicht Zierde. Ohne
        // diese Zeile genuegt ein erfundenes tmp_name im Formular, um
        // /etc/passwd, die .env oder die SQLite-Datei "hochzuladen" — und die
        // Antwort waere ein huebsch verkleinertes Bild davon oder eine
        // Fehlermeldung, die verraet, ob die Datei existiert.
        if ($temporaer === '' || !($this->istHochgeladen)($temporaer)) {
            throw new MedienFehler('nicht_hochgeladen', 'Die Datei stammt nicht aus einem HTTP-Upload.');
        }

        // Schritt 3: Die Byte-Grenze wird an der Datei gemessen, nicht an
        // $datei['size']. In einer echten Anfrage rechnet PHP diesen Wert
        // selbst aus; hier kommt $datei aber als gewoehnliches Array an, und
        // ein Aufrufer koennte es zusammenbauen. filesize() ist die Tatsache.
        $groesse = @filesize($temporaer);

        if ($groesse === false) {
            throw new MedienFehler('upload_fehlgeschlagen', 'Die hochgeladene Datei ist nicht lesbar.');
        }

        if ($groesse > self::MAX_BYTES) {
            throw new MedienFehler('datei_zu_gross', $groesse . ' Bytes, erlaubt sind ' . self::MAX_BYTES . '.');
        }

        if ($groesse === 0) {
            throw new MedienFehler('upload_fehlgeschlagen', 'Die hochgeladene Datei ist leer.');
        }

        return $temporaer;
    }

    /**
     * Schritt 4: die doppelte Typpruefung, dazu die Abweisung von Animation.
     *
     * WARUM ZWEI PRUEFUNGEN UND NICHT EINE — beide sind noetig, keine genuegt:
     *
     *  - getimagesize() ist gegenueber angehaengten Daten NACHLAESSIG. Es
     *    liest den Kopf und hoert dann auf; was hinter dem Bild steht,
     *    interessiert es nicht. Eine Datei aus JPEG-Kopf plus angehaengtem
     *    PHP-Text besteht diese Pruefung anstandslos. Gebraucht wird sie
     *    trotzdem, denn nur sie liefert Breite und Hoehe — und ohne die gibt
     *    es keine Bombenpruefung vor dem Dekodieren.
     *  - finfo_file() liest eine unabhaengige Signaturdatenbank. Es BEWEIST
     *    aber keine Dekodierbarkeit: Ein PNG, das nach dem IHDR-Abschnitt
     *    abbricht, meldet weiterhin image/png.
     *
     * Zusammen sind sie eine Kreuzprobe zweier verschiedener Implementierungen
     * auf DENSELBEN Wert. Eine Datei, die beide gleichzeitig in dieselbe
     * falsche Richtung tauscht, ist deutlich schwerer zu bauen als eine, die
     * eine von beiden taeuscht. Den eigentlichen Beweis liefert erst der
     * Dekodierer in schreiben() — diese Pruefung sortiert vor, damit er gar
     * nicht erst mit Fremdartigem in Beruehrung kommt.
     *
     * @return array{0:int,1:int,2:int} Breite, Hoehe, IMAGETYPE_*
     * @throws MedienFehler
     */
    private function typPruefen(string $temporaer): array
    {
        $masse = @getimagesize($temporaer);

        if ($masse === false || !isset($masse[0], $masse[1], $masse[2])) {
            throw new MedienFehler('kein_bild', 'getimagesize() erkennt kein Bild.');
        }

        $breite = (int) $masse[0];
        $hoehe = (int) $masse[1];
        $typ = (int) $masse[2];

        if (!isset(self::ERLAUBT[$typ])) {
            throw new MedienFehler('format_nicht_erlaubt', 'Bildtyp ' . $typ . ' ist nicht zugelassen.');
        }

        if (!function_exists(self::LESER[$typ])) {
            throw new MedienFehler('format_nicht_unterstuetzt', 'Dieser Server kann ' . self::ERLAUBT[$typ] . ' nicht lesen.');
        }

        $kennung = finfo_open(FILEINFO_MIME_TYPE);

        if ($kennung === false) {
            throw new MedienFehler('bildverarbeitung_fehlt', 'finfo_open() ist fehlgeschlagen.');
        }

        $gemeldet = finfo_file($kennung, $temporaer);
        finfo_close($kennung);

        if ($gemeldet !== self::ERLAUBT[$typ]) {
            throw new MedienFehler(
                'typ_widerspruch',
                'getimagesize meldet ' . self::ERLAUBT[$typ] . ', finfo meldet ' . var_export($gemeldet, true) . '.'
            );
        }

        // Animation wird abgewiesen, nicht auf das erste Einzelbild
        // zurechtgeschnitten. Ein animiertes WebP meldet ueberall brav
        // image/webp; imagecreatefromwebp liest dann entweder gar nichts oder
        // genau ein Einzelbild — und dieses eine Bild ist etwas anderes als
        // das, was die Hochladende ausgewaehlt und gesehen hat. Wer eine
        // Bewegtdarstellung will, bekommt sie spaeter als eigene Medienart.
        if ($this->istAnimiert($temporaer, $typ)) {
            throw new MedienFehler('animation_nicht_erlaubt', 'Die Datei enthaelt mehrere Einzelbilder.');
        }

        return [$breite, $hoehe, $typ];
    }

    /**
     * Schritt 5: Dekompressionsbombe — geprueft VOR dem Dekodieren.
     *
     * Beide Grenzen weisen ab, statt es zu versuchen. Ein
     * Speicherueberlauf waere kein abfangbarer Fehler, sondern ein Fatal Error:
     * Er beendet die Anfrage sofort, laesst angelegte Datenbankzeilen ohne
     * Datei zurueck und liefert der Nutzerin eine weisse Seite.
     *
     * @throws MedienFehler
     */
    private function massePruefen(int $breite, int $hoehe): void
    {
        if ($breite < 1 || $hoehe < 1) {
            throw new MedienFehler('kein_bild', 'Bildmasse ' . $breite . 'x' . $hoehe . '.');
        }

        if ($breite * $hoehe > self::MAX_PIXEL) {
            throw new MedienFehler(
                'zu_viele_pixel',
                $breite . 'x' . $hoehe . ' Punkte, erlaubt sind ' . self::MAX_PIXEL . '.'
            );
        }

        if (!self::passtInSpeicher($breite, $hoehe, self::speichergrenze(), memory_get_usage(true))) {
            throw new MedienFehler(
                'speicher_reicht_nicht',
                $breite . 'x' . $hoehe . ' braeuchte etwa ' . self::speicherbedarf($breite, $hoehe)
                . ' Bytes, memory_limit ist ' . self::speichergrenze() . '.'
            );
        }
    }

    /**
     * Passt die Verarbeitung dieses Bildes in den verfuegbaren Speicher?
     *
     * Oeffentlich und ohne Seiteneffekte, damit die Rechnung pruefbar ist:
     * Sie ist eine Gratwanderung. Zu grosszuegig gerechnet endet ein Upload im
     * Fatal Error — der ist nicht abfangbar, reisst die ganze Anfrage mit und
     * hinterlaesst der Nutzerin eine weisse Seite. Zu streng gerechnet weist
     * der Server ein voellig normales Handyfoto ab, und das faellt niemandem
     * auf, der auf seinem Entwicklungsrechner ohne Speichergrenze arbeitet.
     *
     * @param int $grenze memory_limit in Bytes; 0 bedeutet unbegrenzt
     * @param int $belegt bereits belegter Speicher in Bytes
     */
    public static function passtInSpeicher(int $breite, int $hoehe, int $grenze, int $belegt = 0): bool
    {
        if ($grenze <= 0) {
            return true;
        }

        return $belegt + self::speicherbedarf($breite, $hoehe) <= $grenze;
    }

    /**
     * Der geschaetzte Spitzenbedarf in Bytes.
     *
     * GD haelt jeden Bildpunkt eines Echtfarbbildes als 4 Byte. Waehrend der
     * Verkleinerung leben zwei Bilder gleichzeitig: die Quelle in voller
     * Groesse und das Ziel, das nie groesser als MAX_KANTE im Quadrat wird.
     * Nicht Quelle mal zwei — das waere bei einem 20-Megapixel-Foto um 70
     * Megabyte zu pessimistisch und wuerde auf einem 128-MB-Hosting jedes
     * Handyfoto abweisen.
     *
     * Die Reserve deckt ab, was sich von aussen nicht ausrechnen laesst: die
     * Zeilenpuffer des Dekodierers, den Zwischenschritt der Vorschau und den
     * Aufschlag der Speicherverwaltung.
     */
    public static function speicherbedarf(int $breite, int $hoehe): int
    {
        return $breite * $hoehe * 4
            + self::MAX_KANTE * self::MAX_KANTE * 4
            + self::SPEICHER_RESERVE;
    }

    /**
     * Schritte 6 bis 8: neu kodieren, Vorschau erzeugen, ablegen.
     *
     * @return array{pfad: string, vorschau_pfad: string, art: string}
     * @throws MedienFehler
     */
    private function schreiben(string $temporaer, int $typ, string $zielVerzeichnis): array
    {
        $verzeichnis = $this->verzeichnisSichern($zielVerzeichnis);

        $leser = self::LESER[$typ];
        $quelle = @$leser($temporaer);

        // HIER wird die Dekodierbarkeit endlich bewiesen, und nur hier. Kopf
        // und Signatur haben es bis hierher nur behauptet.
        if (!$quelle instanceof \GdImage) {
            throw new MedienFehler('dekodierung_fehlgeschlagen', 'GD kann die Datei nicht dekodieren.');
        }

        $pfad = '';
        $vorschauPfad = '';

        try {
            $quelle = $this->orientierungAnwenden($quelle, $temporaer, $typ);

            // DIE NEUKODIERUNG. Ab dieser Zeile hat keine Zeile der
            // Originaldatei mehr Bestand: Was gespeichert wird, entsteht aus
            // Bildpunkten, nicht aus Bytes. EXIF-GPS, angehaengter Text und
            // eingebettete Skripte ueberleben das nicht — sie sind keine
            // Bildpunkte.
            $bild = $this->skalieren($quelle, self::MAX_KANTE, true);

            try {
                $pfad = $verzeichnis . '/' . $this->dateiname();

                if (!@imagejpeg($bild, $pfad, self::QUALITAET)) {
                    throw new MedienFehler('speichern_fehlgeschlagen', 'imagejpeg() nach ' . $pfad . ' fehlgeschlagen.');
                }

                @chmod($pfad, 0640);

                $vorschauPfad = $verzeichnis . '/' . $this->dateiname();
                $this->vorschauSchreiben($bild, $vorschauPfad);
            } finally {
                imagedestroy($bild);
            }
        } catch (\Throwable $f) {
            // Kein halbes Ergebnis hinterlassen: Eine Bilddatei ohne Vorschau
            // waere fuer die oeffentliche Zone unbrauchbar und wuerde nie
            // wieder aufgeraeumt, weil es keine Datenbankzeile dazu gibt.
            if ($pfad !== '') {
                @unlink($pfad);
            }

            if ($vorschauPfad !== '') {
                @unlink($vorschauPfad);
            }

            throw $f;
        } finally {
            imagedestroy($quelle);
        }

        return [
            'pfad' => $pfad,
            'vorschau_pfad' => $vorschauPfad,
            'art' => self::ART_BILD,
        ];
    }

    /**
     * Schritt 7: die Vorschau.
     *
     * Erst auf VORSCHAU_KERN herunterrechnen — hier stirbt die Information,
     * unwiderruflich und auf dem Server. Erst danach wieder hochrechnen und
     * zweimal weichzeichnen, damit aus den 24 sichtbaren Kloetzchen eine
     * Flaeche wird, die nach Absicht aussieht statt nach Fehler.
     *
     * Die Reihenfolge ist der ganze Punkt. Weichzeichnen allein waere
     * umkehrbar, und ein CSS-Filter waere wirkungslos, weil das scharfe
     * Original dafuer im Browser liegen muesste.
     *
     * @throws MedienFehler
     */
    private function vorschauSchreiben(\GdImage $bild, string $pfad): void
    {
        $kern = $this->skalieren($bild, self::VORSCHAU_KERN, false);

        try {
            $vorschau = $this->skalieren($kern, self::VORSCHAU_KANTE, false);
        } finally {
            imagedestroy($kern);
        }

        try {
            imagefilter($vorschau, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($vorschau, IMG_FILTER_GAUSSIAN_BLUR);

            if (!@imagejpeg($vorschau, $pfad, self::QUALITAET_VORSCHAU)) {
                throw new MedienFehler('speichern_fehlgeschlagen', 'imagejpeg() nach ' . $pfad . ' fehlgeschlagen.');
            }

            @chmod($pfad, 0640);
        } finally {
            imagedestroy($vorschau);
        }
    }

    // --- Werkzeuge ---------------------------------------------------------

    /**
     * Wertet die EXIF-Orientierung aus, bevor sie mit dem Rest verschwindet.
     *
     * Ein Handy speichert das Bild fast immer quer und legt daneben die
     * Notiz "beim Anzeigen um 90 Grad drehen". Die Neukodierung wirft diese
     * Notiz weg — richtig so, sie steht neben den Koordinaten im selben
     * Abschnitt. Wer sie nicht vorher ANWENDET, veroeffentlicht jedes
     * Hochkantfoto liegend.
     */
    private function orientierungAnwenden(\GdImage $bild, string $pfad, int $typ): \GdImage
    {
        if ($typ !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $bild;
        }

        $daten = @exif_read_data($pfad);

        if (!is_array($daten) || !isset($daten['Orientation'])) {
            return $bild;
        }

        $orientierung = (int) $daten['Orientation'];

        // imagerotate dreht gegen den Uhrzeigersinn; die EXIF-Werte beschreiben
        // die noetige Drehung im Uhrzeigersinn. 5 und 7 sind gespiegelte
        // Aufnahmen (Frontkamera) und brauchen beides.
        $gedreht = match ($orientierung) {
            3 => imagerotate($bild, 180, 0),
            5, 8 => imagerotate($bild, 90, 0),
            6, 7 => imagerotate($bild, 270, 0),
            default => null,
        };

        if ($gedreht instanceof \GdImage) {
            imagedestroy($bild);
            $bild = $gedreht;
        }

        if ($orientierung === 2 || $orientierung === 5 || $orientierung === 7) {
            imageflip($bild, IMG_FLIP_HORIZONTAL);
        }

        if ($orientierung === 4) {
            imageflip($bild, IMG_FLIP_VERTICAL);
        }

        return $bild;
    }

    /**
     * Rechnet auf eine lange Kante um und gibt IMMER ein neues Bild zurueck.
     *
     * Auch dann, wenn nichts zu tun waere: Ein zurueckgegebenes Original waere
     * ein zweiter Verweis auf dasselbe GD-Handle, und das erste imagedestroy()
     * wuerde dem zweiten Aufrufer den Boden wegziehen. Der Preis ist eine
     * ueberfluessige Kopie bei kleinen Bildern, der Gewinn ist, dass jeder
     * Aufrufer sein Ergebnis bedingungslos freigeben darf.
     *
     * $nurVerkleinern verhindert, dass ein 300-Pixel-Bild auf 1600 aufgeblasen
     * wird; die Vorschau braucht das Hochrechnen dagegen ausdruecklich.
     */
    private function skalieren(\GdImage $quelle, int $langeKante, bool $nurVerkleinern): \GdImage
    {
        $breite = imagesx($quelle);
        $hoehe = imagesy($quelle);
        $faktor = $langeKante / max($breite, $hoehe);

        if ($nurVerkleinern && $faktor > 1.0) {
            $faktor = 1.0;
        }

        $neueBreite = max(1, (int) round($breite * $faktor));
        $neueHoehe = max(1, (int) round($hoehe * $faktor));

        $ziel = imagecreatetruecolor($neueBreite, $neueHoehe);

        if (!$ziel instanceof \GdImage) {
            throw new MedienFehler('verarbeitung_fehlgeschlagen', 'imagecreatetruecolor() fehlgeschlagen.');
        }

        // Weiss fuellen, bevor kopiert wird: JPEG kennt keine Transparenz, und
        // ein frisches GD-Bild ist schwarz. Ein PNG mit durchsichtigem Rand
        // saehe sonst aus, als haette jemand mit dem Filzstift gearbeitet.
        $weiss = imagecolorallocate($ziel, 255, 255, 255);

        if ($weiss !== false) {
            imagefilledrectangle($ziel, 0, 0, $neueBreite - 1, $neueHoehe - 1, $weiss);
        }

        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);

        return $ziel;
    }

    /**
     * Erkennt Animation im Container, ohne zu dekodieren.
     *
     * Bei WebP steht die Animation als RIFF-Abschnitt 'ANIM' im Kopf, direkt
     * hinter 'VP8X'. Vier Kilobyte reichen, um ihn zu finden — und sie sind
     * billiger als jeder Dekodierversuch.
     */
    private function istAnimiert(string $pfad, int $typ): bool
    {
        if ($typ !== IMAGETYPE_WEBP) {
            return false;
        }

        $kopf = @file_get_contents($pfad, false, null, 0, 4096);

        if (!is_string($kopf)) {
            return true;
        }

        return str_contains($kopf, 'ANIM') || str_contains($kopf, 'ANMF');
    }

    /**
     * Ein Dateiname aus dem Zufallsgenerator.
     *
     * NIE der Clientname, und zwar aus vier Gruenden auf einmal: Er kann
     * '../../public/index.php' heissen, er kann 'bild.php.jpg' heissen, er
     * kann in einer Anzeige den echten Namen der Hochladenden verraten
     * ('IMG_Wohnung_Silvia.jpg'), und er kann mit dem Namen einer anderen
     * Datei kollidieren. 16 Zufallsbytes loesen alle vier Probleme
     * gleichzeitig, und die Endung ist .jpg, weil das Ergebnis IMMER ein JPEG
     * ist.
     */
    private function dateiname(): string
    {
        return bin2hex(random_bytes(16)) . '.jpg';
    }

    /**
     * Legt das Zielverzeichnis an und gibt es ohne Schlussschraegstrich
     * zurueck.
     *
     * 0750 statt 0755: Der Webserver-Benutzer und seine Gruppe genuegen. Und
     * 0640 an den Dateien selbst — sie werden von PHP gelesen und
     * ausgeliefert, nicht vom Webserver direkt geoeffnet. Wer 'other' Rechte
     * gibt, oeffnet auf einem geteilten Hosting jedem Nachbarkonto das
     * Medienverzeichnis.
     *
     * @throws MedienFehler
     */
    private function verzeichnisSichern(string $zielVerzeichnis): string
    {
        $verzeichnis = rtrim($zielVerzeichnis, '/');

        if ($verzeichnis === '') {
            throw new MedienFehler('ziel_unbrauchbar', 'Kein Zielverzeichnis angegeben.');
        }

        if (!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0750, true) && !is_dir($verzeichnis)) {
            throw new MedienFehler('ziel_unbrauchbar', 'Verzeichnis ' . $verzeichnis . ' laesst sich nicht anlegen.');
        }

        @chmod($verzeichnis, 0750);

        if (!is_writable($verzeichnis)) {
            throw new MedienFehler('ziel_unbrauchbar', 'Verzeichnis ' . $verzeichnis . ' ist nicht beschreibbar.');
        }

        return $verzeichnis;
    }

    /**
     * memory_limit in Bytes; 0 bedeutet "unbegrenzt, keine Pruefung noetig".
     */
    private static function speichergrenze(): int
    {
        $roh = trim((string) ini_get('memory_limit'));

        if ($roh === '' || $roh === '-1') {
            return 0;
        }

        $zahl = (int) $roh;

        if ($zahl <= 0) {
            return 0;
        }

        return match (strtolower(substr($roh, -1))) {
            'g' => $zahl * 1024 * 1024 * 1024,
            'm' => $zahl * 1024 * 1024,
            'k' => $zahl * 1024,
            default => $zahl,
        };
    }
}
