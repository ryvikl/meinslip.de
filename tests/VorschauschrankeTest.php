<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Env;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\View;
use MeinSlip\Http\Formularschutz;
use MeinSlip\Http\Vorschauschranke;
use PHPUnit\Framework\TestCase;

/**
 * Die Vorschau-Schranke: Solange die Rechtstexte Entwuerfe sind, steht die
 * Seite hinter einem Passwort (Begruendung im Kopf der Klasse).
 *
 * Die eine Zusicherung, an der alles haengt: DIE SCHRANKE FAELLT GESCHLOSSEN
 * AUS, NIE OFFEN. Jeder Test hier prueft eine Seite dieser Muenze — kein
 * Ausweis heisst kein Durchkommen, ein falsch konfiguriertes Passwort sperrt
 * alle statt niemanden, und nur der Deploy-Endpunkt (eigenes Token, eine
 * Maschine ohne Cookies) bleibt draussen vor.
 */
final class VorschauschrankeTest extends TestCase
{
    private Vorschauschranke $schranke;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../app/Support/hilfen.php';
        Lang::einrichten(__DIR__ . '/../resources/lang', 'de-DE');

        Env::setzen('APP_SCHLUESSEL', 'nur-fuer-tests-0123456789abcdef');
        Env::setzen('VORSCHAU_PASSWORT', 'korrektes-vorschau-passwort');

        // Env haelt seine Werte statisch, $_COOKIE lebt je Prozess — beides
        // wird hier je Test auf einen bekannten Stand gebracht.
        unset($_COOKIE[Vorschauschranke::COOKIE], $_COOKIE[Formularschutz::COOKIE]);

        $this->schranke = new Vorschauschranke(new View(__DIR__ . '/../resources/views'));
    }

    protected function tearDown(): void
    {
        Env::setzen('VORSCHAU_PASSWORT', '');
        unset($_COOKIE[Vorschauschranke::COOKIE], $_COOKIE[Formularschutz::COOKIE]);

        parent::tearDown();
    }

    /** @param array<string,mixed> $formular */
    private function anfrage(string $methode = 'GET', string $pfad = '/', array $formular = []): Request
    {
        return Request::erzeugen($methode, $pfad, $formular);
    }

    /**
     * Formularschutz-Paar wie im Browser: Cookie plus verstecktes Feld.
     *
     * @return array<string,string>
     */
    private function mitFormularschutz(string $passwort): array
    {
        $token = str_repeat('a', 64);
        $_COOKIE[Formularschutz::COOKIE] = $token;

        return [
            Formularschutz::FELD => $token,
            Vorschauschranke::FELD => $passwort,
        ];
    }

    public function testOhnePasswortIstDieSchrankeAus(): void
    {
        Env::setzen('VORSCHAU_PASSWORT', '');

        self::assertNull($this->schranke->pruefen($this->anfrage()));
    }

    public function testOhneAusweisKommtDieSchranke(): void
    {
        $antwort = $this->schranke->pruefen($this->anfrage('GET', '/entdecken'));

        self::assertNotNull($antwort);
        self::assertSame(401, $antwort->status);

        // Das Formular sendet an den angefragten Pfad zurueck — ein tiefer
        // Verweis uebersteht den Einlass.
        self::assertStringContainsString('action="/entdecken"', $antwort->inhalt);
        self::assertStringContainsString(Vorschauschranke::FELD, $antwort->inhalt);

        // 401 plus no-store plus noindex: weder der Service Worker noch eine
        // Suchmaschine darf die Schrankenseite als Inhalt verbuchen.
        self::assertSame('no-store', $antwort->kopfzeilen['Cache-Control'] ?? null);
        self::assertSame('noindex, nofollow', $antwort->kopfzeilen['X-Robots-Tag'] ?? null);
    }

    public function testSchrankeVerraetNichtsVomInhalt(): void
    {
        $antwort = $this->schranke->pruefen($this->anfrage());

        self::assertNotNull($antwort);

        // Keine Navigation, keine Angebote, kein Anmeldeverweis — die Seite
        // ist eine Tuer, kein Telemedium mit Inhalt.
        self::assertStringNotContainsString('ms-untennav', $antwort->inhalt);
        self::assertStringNotContainsString('/anmelden', $antwort->inhalt);
        self::assertStringNotContainsString('/impressum', $antwort->inhalt);
    }

    public function testDeployEndpunktBleibtAusgenommen(): void
    {
        // Die Deployment-Automatik hat ein eigenes Token und kann weder
        // Cookies annehmen noch Formulare ausfuellen. Ohne die Ausnahme
        // braeche jedes Deployment, sobald die Schranke steht.
        self::assertNull($this->schranke->pruefen($this->anfrage('POST', '/deploy/migrieren')));
    }

    public function testZuKurzesPasswortSperrtAlleStattNiemanden(): void
    {
        Env::setzen('VORSCHAU_PASSWORT', 'zu-kurz');

        $antwort = $this->schranke->pruefen($this->anfrage());

        self::assertNotNull($antwort);
        self::assertSame(503, $antwort->status);

        // Auch das richtige (zu kurze) Passwort oeffnet dann nicht — die
        // Konfiguration ist abgelehnt, nicht halb angenommen.
        $einlass = $this->schranke->pruefen(
            $this->anfrage('POST', '/', $this->mitFormularschutz('zu-kurz'))
        );

        self::assertNotNull($einlass);
        self::assertSame(503, $einlass->status);
        self::assertArrayNotHasKey(Vorschauschranke::COOKIE, $_COOKIE);
    }

    public function testFalschesPasswortWirdAbgewiesen(): void
    {
        $antwort = $this->schranke->pruefen(
            $this->anfrage('POST', '/', $this->mitFormularschutz('geraten'))
        );

        self::assertNotNull($antwort);
        self::assertSame(401, $antwort->status);
        self::assertStringContainsString(Lang::t('vorschau.fehler_falsch'), $antwort->inhalt);
        self::assertArrayNotHasKey(Vorschauschranke::COOKIE, $_COOKIE);
    }

    public function testOhneFormularschutzKeinEinlass(): void
    {
        // Richtiges Passwort, aber ohne das doppelt gesendete Token: Eine
        // fremde Seite koennte den Einlass sonst im Hintergrund ausloesen.
        $antwort = $this->schranke->pruefen(
            $this->anfrage('POST', '/', [Vorschauschranke::FELD => 'korrektes-vorschau-passwort'])
        );

        self::assertNotNull($antwort);
        self::assertSame(401, $antwort->status);
        self::assertArrayNotHasKey(Vorschauschranke::COOKIE, $_COOKIE);
    }

    public function testRichtigesPasswortSetztAusweisUndLeitetWeiter(): void
    {
        $antwort = $this->schranke->pruefen(
            $this->anfrage('POST', '/angebot/7', $this->mitFormularschutz('korrektes-vorschau-passwort'))
        );

        self::assertNotNull($antwort);

        // 303: Das Ziel wird mit GET geholt, der Einlass-POST nicht wiederholt.
        self::assertSame(303, $antwort->status);
        self::assertSame('/angebot/7', $antwort->kopfzeilen['Location'] ?? null);
        self::assertArrayHasKey(Vorschauschranke::COOKIE, $_COOKIE);

        // Mit dem Ausweis ist die naechste Anfrage durch.
        self::assertNull($this->schranke->pruefen($this->anfrage('GET', '/angebot/7')));
    }

    public function testAusweisFaelltMitDemPasswort(): void
    {
        $this->schranke->pruefen(
            $this->anfrage('POST', '/', $this->mitFormularschutz('korrektes-vorschau-passwort'))
        );

        self::assertNull($this->schranke->pruefen($this->anfrage()));

        // Passwort gewechselt: Jeder ausgegebene Ausweis ist damit ungueltig,
        // ohne dass irgendwo etwas geloescht werden muesste.
        Env::setzen('VORSCHAU_PASSWORT', 'ein-neues-langes-passwort');

        $antwort = $this->schranke->pruefen($this->anfrage());

        self::assertNotNull($antwort);
        self::assertSame(401, $antwort->status);
    }
}
