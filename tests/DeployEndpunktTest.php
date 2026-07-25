<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Request;
use MeinSlip\Http\DeployEndpunkt;

/**
 * Der Endpunkt fuehrt Datenbankaenderungen aus und ist dauerhaft im Netz
 * erreichbar. Seine Zugangspruefung ist deshalb die Stelle, die wirklich
 * getestet gehoeren muss — nicht die Migrationen selbst, die haben eigene Tests.
 */
final class DeployEndpunktTest extends Testfall
{
    private const GUELTIGER_TOKEN = 'x7Kp2mQ9vT4nL8wR3sY6bZ1cF5hJ0aD';

    private function endpunkt(string $token = self::GUELTIGER_TOKEN): DeployEndpunkt
    {
        return new DeployEndpunkt($token, $this->db, dirname(__DIR__) . '/database/migrations');
    }

    private function anfrage(string $methode = 'POST', ?string $token = null): Request
    {
        $kopfzeilen = $token === null ? [] : ['X-Deploy-Token' => $token];

        return Request::erzeugen($methode, '/deploy/migrieren', [], $kopfzeilen);
    }

    public function testOhneTokenGesperrt(): void
    {
        $antwort = $this->endpunkt()->behandeln($this->anfrage());

        self::assertSame(403, $antwort->status);
    }

    public function testMitFalschemTokenGesperrt(): void
    {
        $antwort = $this->endpunkt()->behandeln($this->anfrage('POST', 'falsch-aber-lang-genug-12345678'));

        self::assertSame(403, $antwort->status);
    }

    /**
     * Ein zu kurzer erwarteter Token sperrt den Endpunkt vollstaendig — auch
     * wenn er richtig mitgeschickt wird. Sonst genuegte ein versehentlich
     * gesetztes "test" in der .env, um Migrationen aus dem Netz ausloesbar
     * zu machen.
     */
    public function testZuKurzerErwarteterTokenSperrtGanz(): void
    {
        $kurz = 'zu-kurz';
        $antwort = $this->endpunkt($kurz)->behandeln($this->anfrage('POST', $kurz));

        self::assertSame(403, $antwort->status);
    }

    public function testLeererErwarteterTokenSperrtGanz(): void
    {
        $antwort = $this->endpunkt('')->behandeln($this->anfrage('POST', ''));

        self::assertSame(403, $antwort->status);
    }

    public function testGetWirdAbgewiesen(): void
    {
        $antwort = $this->endpunkt()->behandeln($this->anfrage('GET', self::GUELTIGER_TOKEN));

        self::assertSame(403, $antwort->status, 'Nur POST darf Migrationen ausloesen.');
    }

    public function testMitRichtigemTokenLaufenMigrationen(): void
    {
        // Die Basisklasse hat die Migrationen bereits ausgefuehrt, deshalb
        // ist hier nichts mehr offen — genau das soll der Endpunkt melden.
        $antwort = $this->endpunkt()->behandeln($this->anfrage('POST', self::GUELTIGER_TOKEN));

        self::assertSame(200, $antwort->status);

        $daten = json_decode($antwort->inhalt, true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($daten['erfolg']);
        self::assertSame(0, $daten['anzahl'], 'Bereits ausgefuehrte Migrationen duerfen nicht erneut laufen.');
        self::assertSame(0, $daten['hauptbuch_abweichung_cent']);
    }

    public function testOffeneMigrationenWerdenAusgefuehrtUndNichtWiederholt(): void
    {
        // Frische Datenbank ohne Migrationen.
        $leer = \MeinSlip\Core\Database::imArbeitsspeicher();
        $endpunkt = new DeployEndpunkt(
            self::GUELTIGER_TOKEN,
            $leer,
            dirname(__DIR__) . '/database/migrations'
        );

        $erste = json_decode(
            $endpunkt->behandeln($this->anfrage('POST', self::GUELTIGER_TOKEN))->inhalt,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertGreaterThan(0, $erste['anzahl'], 'Beim ersten Lauf muessen Migrationen ausgefuehrt werden.');

        $zweite = json_decode(
            $endpunkt->behandeln($this->anfrage('POST', self::GUELTIGER_TOKEN))->inhalt,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(0, $zweite['anzahl'], 'Ein zweiter Aufruf darf nichts erneut ausfuehren.');
    }
}
