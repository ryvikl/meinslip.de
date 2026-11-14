<?php

declare(strict_types=1);

/**
 * Widerrufsbelehrung.
 *
 * NICHT anwaltlich geprüft. Die Abschnitte 'recht' und 'folgen' folgen dem
 * gesetzlichen Muster (Anlage 1 zu Art. 246a § 1 Abs. 2 S. 2 EGBGB) für den
 * Warenkauf mit Rücksendung auf Kosten des Verbrauchers. Wer daran etwas
 * umformuliert, verliert die Schutzwirkung des Musters.
 *
 * Die Belehrung gilt nur für Verträge mit der Gesellschaft, also für
 * Bestellungen über die Kasse. Die ist abgeschaltet.
 *
 * OFFEN, bevor die Kasse eingeschaltet wird:
 *  - Elektronische Widerrufsfunktion (§ 356a BGB, Pflicht seit 19.06.2026)
 *    auf dieser Seite einbauen und in 'recht' nennen.
 *  - Rücksendeanschrift bestätigen. Das Schwesterangebot nennt für Retouren
 *    eine andere Anschrift als im Impressum.
 */
return [
    'kicker' => 'Verbraucherrechte',
    'titel' => 'Widerrufsbelehrung',

    'geltung_titel' => 'Für wen das gilt',
    'geltung' => <<<'TEXT'
        Ein Widerrufsrecht hast du als Verbraucher:in bei Verträgen, die du über die Kasse der Plattform mit uns schließt. Verbraucher:in ist, wer überwiegend privat handelt.

        Geschäfte, die du ohne Kasse direkt mit einer anderen Person verabredest, schließt du nicht mit uns. Verkauft dir dabei eine Privatperson, besteht kein gesetzliches Widerrufsrecht.

        Die meisten Angebote auf dieser Plattform werden eigens nach deinen Angaben angefertigt und sind deshalb vom Widerruf ausgenommen — siehe „Ausnahmen“. Für alles andere gilt die folgende Belehrung.
        TEXT,

    'recht_titel' => 'Widerrufsrecht',
    'recht' => <<<'TEXT'
        Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.

        Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.

        Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (Chic & Chill GmbH, Leipziger Straße 90, 04552 Borna, Telefon: 03433 859 94 60, E-Mail: info@chicandchill.de) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.

        Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.
        TEXT,

    'folgen_titel' => 'Folgen des Widerrufs',
    'folgen' => <<<'TEXT'
        Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.

        Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist.

        Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden.

        Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.

        Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.
        TEXT,

    'ausnahmen_titel' => 'Ausnahmen vom Widerrufsrecht',
    'ausnahmen' => <<<'TEXT'
        Das Widerrufsrecht besteht nicht bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch dich maßgeblich ist (§ 312g Abs. 2 Nr. 1 BGB). Das trifft zu, wenn du im Konfigurator Tragedauer, Anlass oder weitere Wünsche festlegst und die Ware danach eigens für dich angefertigt wird. Deine Angaben speichern wir mit Zeitstempel, damit nachvollziehbar bleibt, was vereinbart war. Am Angebot und vor dem Abschicken der Bestellung steht, ob diese Ausnahme greift.

        Das Widerrufsrecht erlischt vorzeitig bei versiegelter Ware, die aus Gründen des Gesundheitsschutzes oder der Hygiene nicht zur Rückgabe geeignet ist, wenn du die Versiegelung nach der Lieferung entfernst (§ 312g Abs. 2 Nr. 3 BGB).
        TEXT,

    'digital_titel' => 'Digitale Inhalte',
    'digital' => 'Digitale Inhalte verkaufen wir derzeit nicht. Sobald es sie gibt, gilt: Das '
        . 'Widerrufsrecht erlischt, wenn du ausdrücklich zustimmst, dass wir vor Ablauf der '
        . 'Widerrufsfrist mit der Ausführung beginnen, du bestätigst, dass du dadurch dein '
        . 'Widerrufsrecht verlierst, und wir dir beides bestätigt haben (§ 356 Abs. 5 BGB).',

    'schutz_titel' => 'Was dich schützt, wenn du nicht widerrufen kannst',
    'schutz' => 'Kein Widerrufsrecht heißt nicht schutzlos. Kommt die Ware nicht an oder weicht '
        . 'sie wesentlich von der Beschreibung ab, widersprichst du innerhalb der Prüfzeit von '
        . '72 Stunden — dann wird der hinterlegte Betrag nicht freigegeben. Deine gesetzlichen '
        . 'Mängelrechte bleiben in jedem Fall bestehen.',

    'formular_titel' => 'Muster-Widerrufsformular',
    'formular' => <<<'TEXT'
        Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.

        An: Chic & Chill GmbH, Leipziger Straße 90, 04552 Borna, E-Mail: info@chicandchill.de

        - Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)
        - Bestellt am (*) / erhalten am (*)
        - Name des/der Verbraucher(s)
        - Anschrift des/der Verbraucher(s)
        - Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)
        - Datum

        (*) Unzutreffendes streichen.
        TEXT,

    'stand' => 'Stand: 14. November 2026',
];
