<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Http\NachrichtenRouten;
use PHPUnit\Framework\TestCase;

/**
 * Haelt die Texte des Chats an seinen Fachklassen fest.
 *
 * WARUM ES DIESEN TEST BRAUCHT. Die Chatoberflaeche baut ihre wichtigsten
 * Texte ZUSAMMENGESETZT: te('chat.deklaration.' . $wert) und
 * te('chat.fehler.' . $fehler). Solche Schluessel prueft UebersetzungenTest
 * NICHT — sein regulaerer Ausdruck erkennt nur die Form te('a.b') mit
 * einfachen Anfuehrungszeichen und ohne zweites Argument
 * (tests/UebersetzungenTest.php:74). Ohne diesen Test faellt ein fehlender
 * Text erst auf, wenn ihn jemand als '[[chat.deklaration.person]]' im
 * Fensterkopf liest — also genau an der Stelle, die das ganze Paket traegt.
 *
 * Gebaut nach dem Vorbild von tests/ProfilTest.php: Die moeglichen Werte
 * werden aus der Fachklasse eingesammelt, nicht in den Test abgeschrieben.
 * Eine abgeschriebene Liste waere beim naechsten neuen Wert stumm veraltet und
 * gaebe genau dann gruenes Licht, wenn sie es nicht duerfte.
 *
 * Die Fehlerschluessel kommen aus den Wurfstellen selbst und nicht aus einer
 * Konstante, weil ChatFehler keine hat: Der Schluessel ist ein Argument des
 * Konstruktors. Gesucht wird deshalb ueber app/ nach "new ChatFehler('…')" —
 * die einzige Quelle, die nicht veralten kann.
 */
final class ChatTexteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/app/Support/hilfen.php';
        Lang::einrichten(dirname(__DIR__) . '/resources/lang', 'de-DE');
    }

    /**
     * Jede Deklaration braucht ihr Label.
     *
     * Das Label steht dauerhaft im Fensterkopf, fuer beide Seiten sichtbar
     * (docs/04-features/chat-monetarisierung.md). Fehlt es, steht dort die
     * technische Kennung — und die Zusicherung, die diese Plattform von jeder
     * anderen unterscheidet, liest sich wie ein Fehler.
     */
    public function testJedeDeklarationHatEinLabel(): void
    {
        $fehlend = $this->ohneText('chat.deklaration.', Profile::DEKLARATIONEN);

        self::assertSame(
            [],
            $fehlend,
            "Diese Deklarationen haetten kein Label im Chatfenster und erschienen als\n"
            . "'[[chat.deklaration.x]]'. Ergaenze sie in resources/lang/de-DE/chat.php:\n"
            . implode("\n", $fehlend)
        );
    }

    /**
     * Und ihre Erklaerung auf der Auswahlseite.
     *
     * Ohne Erklaerung ist die Wahl zwischen 'team' und 'ki' geraten. Eine
     * geratene Deklaration ist wertlos — sie soll eine Behauptung sein, die
     * jemand bewusst abgibt.
     */
    public function testJedeDeklarationHatEineErklaerung(): void
    {
        $fehlend = $this->ohneText('chat.deklaration_erklaerung.', Profile::DEKLARATIONEN);

        self::assertSame(
            [],
            $fehlend,
            "Diese Deklarationen haetten auf /nachrichten/deklaration keine Erklaerung.\n"
            . "Ergaenze sie in resources/lang/de-DE/chat.php:\n"
            . implode("\n", $fehlend)
        );
    }

    /**
     * Jeder Fehler, den der Chat werfen kann, braucht einen Text.
     */
    public function testJederChatFehlerHatEinenText(): void
    {
        $schluessel = $this->chatFehlerSchluessel();
        $fehlend = $this->ohneText('chat.fehler.', $schluessel);

        self::assertSame(
            [],
            $fehlend,
            "Diese Chatfehler haetten keinen Text und erschienen als '[[chat.fehler.x]]'.\n"
            . "Ergaenze sie in resources/lang/de-DE/chat.php:\n"
            . implode("\n", $fehlend)
        );
    }

    /**
     * Und er muss durch die Weissliste der Route kommen.
     *
     * NachrichtenRouten::ausListe() zieht jeden unbekannten Schluessel auf
     * 'unbekannt'. Ein Fehler, der dort fehlt, hat also einen Text — und wird
     * ihn trotzdem nie zeigen. Der Mensch liest dann "Das hat nicht geklappt"
     * statt "Warte einen Moment", und die Drosselung sieht aus wie ein Defekt.
     */
    public function testJederChatFehlerStehtInDerWeissliste(): void
    {
        $weissliste = $this->weissliste('FEHLER');
        $fehlend = [];

        foreach ($this->chatFehlerSchluessel() as $schluessel) {
            if (!in_array($schluessel, $weissliste, true)) {
                $fehlend[] = $schluessel;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Chatfehler wuerden von NachrichtenRouten::ausListe() auf 'unbekannt'\n"
            . "gezogen und nie im Klartext erscheinen. Ergaenze sie in\n"
            . "app/Http/NachrichtenRouten.php::FEHLER:\n"
            . implode("\n", $fehlend)
        );
    }

    /**
     * Und umgekehrt: Was die Route durchlaesst, muss auch lesbar sein.
     *
     * Beide Weisslisten landen als 'chat.fehler.x' bzw. 'chat.erfolg.x' in der
     * Vorlage. Ein Eintrag ohne Text waere die schlechteste aller Varianten:
     * Die Rueckmeldung erscheint, aber als Kennung.
     */
    public function testBeideWeisslistenSindVollstaendigUebersetzt(): void
    {
        $fehlend = array_merge(
            $this->ohneText('chat.fehler.', $this->weissliste('FEHLER')),
            $this->ohneText('chat.erfolg.', $this->weissliste('ERFOLGE'))
        );

        self::assertSame(
            [],
            $fehlend,
            "Diese Rueckmeldungen der Chatstrecke haben keinen Text:\n" . implode("\n", $fehlend)
        );
    }

    // --- Hilfen -------------------------------------------------------------

    /**
     * Alle Schluessel aus $werte, fuer die es unter $praefix keinen Text gibt.
     *
     * @param list<string> $werte
     *
     * @return list<string>
     */
    private function ohneText(string $praefix, array $werte): array
    {
        $fehlend = [];

        foreach ($werte as $wert) {
            if (str_starts_with(Lang::t($praefix . $wert), '[[')) {
                $fehlend[] = $praefix . $wert;
            }
        }

        return $fehlend;
    }

    /**
     * Die Schluessel, mit denen irgendwo in app/ ein ChatFehler erzeugt wird.
     *
     * @return list<string>
     */
    private function chatFehlerSchluessel(): array
    {
        $gefunden = [];

        foreach ($this->quelldateien() as $pfad) {
            $inhalt = (string) file_get_contents($pfad);
            preg_match_all("/new ChatFehler\(\s*'([a-z0-9_]+)'/", $inhalt, $treffer);

            foreach ($treffer[1] as $schluessel) {
                $gefunden[$schluessel] = $schluessel;
            }
        }

        $liste = array_values($gefunden);
        sort($liste);

        // Wenn der regulaere Ausdruck eines Tages ins Leere greift — etwa weil
        // die Wurfstellen auf Konstanten umgestellt werden —, soll dieser Test
        // scheitern und nicht stillschweigend nichts mehr pruefen.
        self::assertNotEmpty(
            $liste,
            'Keine ChatFehler-Wurfstelle gefunden. Der Test prueft dann gar nichts mehr — '
            . 'entweder ist der regulaere Ausdruck veraltet oder der Chat ist verschwunden.'
        );

        return $liste;
    }

    /**
     * Eine der beiden Weisslisten aus NachrichtenRouten.
     *
     * getConstants() liefert auch private Konstanten. Die Liste wird bewusst
     * ueber Reflexion geholt und nicht abgeschrieben — sonst prueft der Test
     * seine eigene Kopie.
     *
     * @return list<string>
     */
    private function weissliste(string $name): array
    {
        $konstanten = (new \ReflectionClass(NachrichtenRouten::class))->getConstants();

        self::assertArrayHasKey($name, $konstanten, 'NachrichtenRouten::' . $name . ' fehlt.');
        self::assertIsArray($konstanten[$name]);

        /** @var list<string> $liste */
        $liste = array_values(array_filter($konstanten[$name], 'is_string'));

        return $liste;
    }

    /**
     * Alle PHP-Dateien unter app/.
     *
     * @return list<string>
     */
    private function quelldateien(): array
    {
        $verzeichnis = new \RecursiveDirectoryIterator(
            dirname(__DIR__) . '/app',
            \FilesystemIterator::SKIP_DOTS
        );

        $gefunden = [];

        foreach (new \RecursiveIteratorIterator($verzeichnis) as $datei) {
            if ($datei->isFile() && $datei->getExtension() === 'php' && $datei->isReadable()) {
                $gefunden[] = $datei->getPathname();
            }
        }

        sort($gefunden);

        return $gefunden;
    }
}
