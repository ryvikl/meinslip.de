<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Domain\Admin\Verwaltung;

/**
 * Haelt die Zustellung nach Art. 17 DSA zusammen.
 *
 * Die Profilseite baut ihre Ueberschrift als te('profil.art.' . $art). Solche
 * zusammengesetzten Schluessel prueft UebersetzungenTest NICHT — sein regulaerer
 * Ausdruck erkennt nur die Form te('a.b'). Ohne diesen Test faellt eine neue
 * Handlungsart erst auf, wenn eine betroffene Person '[[profil.art.x]]' liest,
 * also genau in dem Moment, in dem die Begruendung ankommen soll.
 */
final class ProfilTest extends Testfall
{
    protected function setUp(): void
    {
        parent::setUp();
        Lang::einrichten(dirname(__DIR__) . '/resources/lang', 'de-DE');
    }

    /**
     * Jede Handlung, die zugestellt werden kann, braucht einen Text.
     */
    public function testJedeHandlungsartHatEinenText(): void
    {
        $fehlend = [];

        foreach ($this->handlungsarten() as $art) {
            if (str_starts_with(Lang::t('profil.art.' . $art), '[[')) {
                $fehlend[] = $art;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Handlungsarten haetten keinen Text auf /profil und erschienen als\n"
            . "'[[profil.art.x]]'. Ergaenze sie in resources/lang/de-DE/profil.php:\n"
            . implode("\n", $fehlend)
        );
    }

    public function testEineBeschraenkungWirdZugestelltUndIstLesbar(): void
    {
        $verwalter = $this->verwalterin('Chefin');
        $betroffen = $this->benutzer('Lina');

        $verwaltung = new Verwaltung($this->db);
        $verwaltung->kontoSperren($verwalter, $betroffen, 'Wiederholte Verstoesse gegen die Regeln.');

        $zustellungen = $verwaltung->benachrichtigungen($betroffen);

        self::assertCount(1, $zustellungen);
        self::assertSame(Verwaltung::HANDLUNG_KONTO_GESPERRT, $zustellungen[0]['art']);
        self::assertStringContainsString('Wiederholte Verstoesse', (string) $zustellungen[0]['begruendung']);
        self::assertFalse($zustellungen[0]['gelesen']);

        // Und der Text dazu existiert wirklich — sonst steht auf der Seite
        // die Kennung statt einer Erklaerung.
        self::assertStringNotContainsString('[[', Lang::t('profil.art.' . $zustellungen[0]['art']));
    }

    public function testDerLesezeitpunktWirdFestgehalten(): void
    {
        $verwalter = $this->verwalterin('Chefin');
        $betroffen = $this->benutzer('Lina');

        $verwaltung = new Verwaltung($this->db);
        $verwaltung->kontoSperren($verwalter, $betroffen, 'Begruendung.');

        $vorher = $verwaltung->benachrichtigungen($betroffen);
        $verwaltung->benachrichtigungGelesen($betroffen, (int) $vorher[0]['id']);

        $nachher = $verwaltung->benachrichtigungen($betroffen);

        self::assertTrue($nachher[0]['gelesen'], 'Der Lesezeitpunkt ist der Nachweis der Zustellung.');
        self::assertNotNull($nachher[0]['gelesen_am']);
    }

    /** Ein Konto mit der Faehigkeit 'verwalten' — sonst weist Verwaltung ab. */
    private function verwalterin(string $pseudonym): int
    {
        $id = $this->benutzer($pseudonym);
        (new \MeinSlip\Domain\Account\Konten($this->db))->faehigkeitFreischalten(
            $id,
            \MeinSlip\Domain\Account\Konten::FAEHIGKEIT_VERWALTEN,
            'Test'
        );

        return $id;
    }

    /** @return list<string> */
    private function handlungsarten(): array
    {
        $spiegel = new \ReflectionClass(Verwaltung::class);
        $arten = [];

        foreach ($spiegel->getConstants() as $name => $wert) {
            if (str_starts_with($name, 'HANDLUNG_') && is_string($wert)) {
                $arten[] = $wert;
            }
        }

        // Die Angebotsablehnung entsteht in VerwaltungsRouten, nicht als
        // Konstante in Verwaltung — sie wird trotzdem zugestellt.
        $arten[] = 'angebot_abgelehnt';

        self::assertNotEmpty($arten);

        return $arten;
    }
}
