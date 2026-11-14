<?php

declare(strict_types=1);

/**
 * Allgemeine Geschäftsbedingungen.
 *
 * NICHT anwaltlich geprüft. Der Text bildet ab, was die Plattform heute tut
 * und was die Architektur für die Kasse vorsieht:
 *
 *  - Kasse und Guthaben sind abgeschaltet (BESTELLVORGANG_AKTIV=false). Bis
 *    dahin kommen Geschäfte nur zwischen den Nutzer:innen selbst zustande.
 *  - Mit Kasse verkauft die Gesellschaft im eigenen Namen (Kommission,
 *    §§ 383 ff. HGB) — docs/06-steuern-kommissionsmodell.md.
 *  - Fristen (Annahme 48 Stunden, Prüfzeit 72 Stunden) kommen aus der .env.
 *    Ändern sie sich dort, ändern sie sich hier.
 *  - Treffen ohne Warenbezug erzeugen nie eine Zahlung: docs/04-features/safe-meet.md.
 *
 * OFFEN: Wer die Kasse einschaltet, prüft vorher § 6 bis § 9 gegen den dann
 * gewählten Zahlungs- und Versanddienstleister.
 */
return [
    'kicker' => 'Nutzungsbedingungen',
    'titel' => 'Allgemeine Geschäftsbedingungen',

    'geltung_titel' => '§ 1 Worum es geht',
    'geltung' => <<<'TEXT'
        Diese Bedingungen regeln die Nutzung von MeinSlip. Betreiberin ist die Chic & Chill GmbH, Leipziger Straße 90, 04552 Borna (im Folgenden „wir“). Alle weiteren Angaben stehen im Impressum.

        MeinSlip ist ein Marktplatz für Erwachsene. Nutzer:innen stellen dort Ware ein, schreiben einander und verabreden Übergaben. Abweichende Bedingungen von Nutzer:innen gelten nicht.
        TEXT,

    'konto_titel' => '§ 2 Dein Konto',
    'konto' => <<<'TEXT'
        Die Plattform ist ausschließlich für Erwachsene. Ein Konto bekommt nur, wer mindestens 18 Jahre alt und unbeschränkt geschäftsfähig ist. Mit der Registrierung versicherst du das ausdrücklich. Stellt sich heraus, dass es nicht stimmt, sperren wir das Konto sofort.

        - Du registrierst dich selbst, mit einer E-Mail-Adresse, die dir gehört.
        - Ein Mensch, ein Konto. Damit kaufst und verkaufst du.
        - Dein Konto ist nicht übertragbar. Zugangsdaten gibst du nicht weiter.
        - Merkst du, dass jemand anderes Zugriff hat, sagst du uns sofort Bescheid.

        Der Nutzungsvertrag kommt zustande, sobald wir dein Konto anlegen. Die Registrierung kostet nichts.
        TEXT,

    'leistung_titel' => '§ 3 Was wir leisten',
    'leistung' => <<<'TEXT'
        Wir stellen die Plattform bereit: Katalog, Profile, Nachrichten, Terminabsprachen, den Meldeweg und die Moderation. Werbung gibt es nicht.

        Wir halten die Plattform so verfügbar, wie es technisch und wirtschaftlich vernünftig ist. Wartung, Störungen und Sicherheitsgründe können den Zugang zeitweise unterbrechen. Einen Anspruch auf bestimmte Funktionen oder auf ununterbrochene Erreichbarkeit gibt es nicht.

        Kasse und Guthaben sind derzeit abgeschaltet. Was in § 6 bis § 9 zur Bestellung über die Kasse steht, gilt erst, sobald wir sie freischalten.
        TEXT,

    'angebote_titel' => '§ 4 Deine Angebote und Inhalte',
    'angebote' => <<<'TEXT'
        Für alles, was du einstellst oder schreibst, bist du selbst verantwortlich. Wir prüfen Angebote nicht vorab. Sie sind sofort sichtbar; wir greifen ein, wenn uns ein Verstoß gemeldet wird oder auffällt.

        - Du beschreibst deine Ware wahrheitsgemäß und vollständig.
        - Du lädst nur Bilder hoch, die du selbst aufgenommen hast oder verwenden darfst.
        - Dein Profilbild ist für alle sichtbar und deshalb immer jugendfrei.
        - Für die Seiten, auf die du von deinem Profil aus verweist, bist du verantwortlich. Verweise auf rechtswidrige Inhalte entfernen wir.
        - Auf deinen Bildern ist niemand außer dir zu sehen, es sei denn, die Person hat nachweislich zugestimmt und ist volljährig.
        - Du gibst an, ob Nachrichten von dir selbst, von einem Team oder mit KI-Unterstützung kommen.
        - Steuern und Abgaben auf deine Einnahmen klärst und zahlst du selbst.
        TEXT,

    'verbote_titel' => '§ 5 Was nicht geht',
    'verbote' => <<<'TEXT'
        Verboten ist insbesondere:

        - jede Darstellung oder Beteiligung Minderjähriger und jede Anspielung darauf,
        - pornografische Darstellungen in Bildern, Profilen und Angebotstexten: keine sexuellen Handlungen, keine Nahaufnahmen von Genitalien. Gezeigt wird die Ware,
        - Ware, deren Verkauf oder Versand gesetzlich verboten ist,
        - das Anbieten oder Vermitteln sexueller Dienstleistungen und jedes Angebot, das Zeit statt Ware bepreist,
        - Inhalte ohne Einwilligung der abgebildeten Person, fremde Bilder und fremde Identitäten,
        - Täuschung über Ware, Person oder Zahlung,
        - Belästigung, Drohung, Druck und das Weiterschreiben, nachdem jemand dich gesperrt hat,
        - das Abgreifen von Daten, automatisierte Zugriffe und jeder Versuch, technische Schutzmaßnahmen zu umgehen,
        - das Weitergeben oder Veröffentlichen fremder Bilder und Nachrichten außerhalb der Plattform.
        TEXT,

    'vertrag_titel' => '§ 6 Wer mit wem einen Vertrag schließt',
    'vertrag' => <<<'TEXT'
        Es gibt zwei Wege, und sie unterscheiden sich darin, wer dein Vertragspartner ist.

        Bestellung über die Kasse: Die Anbieter:innen beauftragen uns, ihre Ware im eigenen Namen und für ihre Rechnung zu verkaufen (Kommission, §§ 383 ff. HGB). Verkäuferin ist damit immer die Chic & Chill GmbH. Ein Angebot im Katalog ist noch kein bindendes Angebot. Mit dem Abschicken der Bestellung gibst du ein verbindliches Kaufangebot ab. Der Vertrag kommt zustande, wenn die Anbieter:in die Bestellung innerhalb von 48 Stunden annimmt; darüber informieren wir dich. Nimmt sie nicht an, verfällt die Bestellung und der hinterlegte Betrag geht an dich zurück. Den Vertragstext speichern wir; du findest ihn in deiner Bestellübersicht.

        Absprache ohne Kasse: Verabredet ihr ein Geschäft im Chat und wickelt es selbst ab, kommt der Vertrag allein zwischen euch zustande. Wir sind daran nicht beteiligt, nehmen kein Geld entgegen und stehen für die Erfüllung nicht ein. Die Schutzmechanismen aus § 8 und § 9 greifen dann nicht.
        TEXT,

    'preise_titel' => '§ 7 Preise und Provision',
    'preise' => <<<'TEXT'
        Alle Preise sind Endpreise in Euro und enthalten die gesetzliche Umsatzsteuer, soweit sie anfällt. Versandkosten zeigen wir vor dem Abschicken der Bestellung gesondert an.

        Für Käufer:innen kommt nichts dazu. Anbieter:innen zahlen eine Provision ausschließlich auf verkaufte Ware. Sie ist im angezeigten Preis enthalten, und wir nennen dir vor dem Einstellen den Betrag in Euro, der bei dir ankommt. Kein Verkauf, keine Kosten — es gibt weder Grund- noch Einstellgebühr.
        TEXT,

    'treuhand_titel' => '§ 8 Wann Geld fließt',
    'treuhand' => <<<'TEXT'
        Bei einer Bestellung über die Kasse wird dein Betrag hinterlegt und nicht sofort ausgezahlt. Nach Zustellung oder bestätigter Übergabe läuft eine Prüfzeit von 72 Stunden. In dieser Zeit kannst du widersprechen, wenn die Ware nicht angekommen ist oder wesentlich von der Beschreibung abweicht. Widersprichst du, bleibt der Betrag hinterlegt, bis der Fall geklärt ist.

        Kein Ereignis gibt Geld sofort frei, auch eine bestätigte persönliche Übergabe nicht. Deine gesetzlichen Rechte bestehen unabhängig von der Prüfzeit weiter.
        TEXT,

    'versand_titel' => '§ 9 Versand',
    'versand' => 'Versendet wird innerhalb Deutschlands. Die Anbieter:in erhält einen '
        . 'Einlieferungscode und kein Etikett mit deiner Anschrift; als Absender steht unsere '
        . 'Anschrift auf dem Paket. Sie erfährt deine Adresse nicht und du nicht ihre. Die '
        . 'Lieferzeit steht am Angebot. Die Gefahr geht auf dich über, sobald dir die Ware '
        . 'übergeben wurde.',

    'treffen_titel' => '§ 10 Persönliche Übergaben',
    'treffen' => <<<'TEXT'
        Über die Plattform könnt ihr Ort und Zeit für eine Übergabe von Ware verabreden. Mehr ist ein Termin nicht.

        Wir vermitteln keine sexuellen Dienstleistungen und verdienen nichts an Treffen. Abgerechnet werden ausschließlich Warengeschäfte. Für das, was bei einem Treffen geschieht, seid ihr selbst verantwortlich. Trefft euch an einem öffentlichen Ort und sagt jemandem Bescheid, wo ihr seid.
        TEXT,

    'maengel_titel' => '§ 11 Wenn etwas nicht stimmt',
    'maengel' => 'Für Ware, die du über die Kasse bei uns kaufst, gelten die gesetzlichen '
        . 'Mängelrechte. Bei gebrauchter Ware beträgt die Verjährungsfrist ein Jahr ab Ablieferung; '
        . 'das gilt nicht für Schadensersatzansprüche nach § 14. Getragene Ware ist gebraucht — '
        . 'das ist ihre vereinbarte Beschaffenheit und kein Mangel. Ob du widerrufen kannst, steht '
        . 'in der Widerrufsbelehrung.',

    'moderation_titel' => '§ 12 Meldungen und Maßnahmen',
    'moderation' => <<<'TEXT'
        Jede Person kann Angebote, Profile und Nachrichten melden, auch ohne Konto. Wir prüfen jede Meldung durch einen Menschen und entscheiden in der Regel innerhalb von 72 Stunden.

        Verstößt etwas gegen Gesetze oder diese Bedingungen, können wir je nach Schwere:

        - einzelne Inhalte verbergen oder ein Angebot sperren,
        - dir das Anbieten untersagen,
        - dein Konto vorübergehend oder dauerhaft sperren.

        Wir wählen das mildeste Mittel, das den Verstoß abstellt. Jede Maßnahme begründen wir, und die Begründung stellen wir dir in deinem Profil zu. Bist du nicht einverstanden, kannst du innerhalb von sechs Monaten kostenlos widersprechen — eine formlose E-Mail an die Adresse im Impressum genügt. Über den Widerspruch entscheidet ein Mensch, der an der ersten Entscheidung nicht beteiligt war, soweit das möglich ist. Wer wiederholt offensichtlich unbegründet meldet, dessen Meldungen können wir für eine angemessene Zeit zurückstellen.
        TEXT,

    'rechte_titel' => '§ 13 Rechte an deinen Inhalten',
    'rechte' => 'Deine Inhalte bleiben deine. Du räumst uns nur das einfache, unentgeltliche Recht '
        . 'ein, sie auf der Plattform zu speichern, technisch anzupassen und anzuzeigen, solange du '
        . 'sie eingestellt lässt. Wir verwenden sie nicht für Werbung außerhalb der Plattform und '
        . 'geben sie nicht an Dritte weiter. Ziehst du einen Inhalt zurück, endet das Recht; '
        . 'ausgenommen ist, was wir zur Klärung einer Meldung oder eines Streits aufbewahren müssen.',

    'haftung_titel' => '§ 14 Haftung',
    'haftung' => <<<'TEXT'
        Wir haften unbeschränkt bei Vorsatz und grober Fahrlässigkeit, bei Verletzung von Leben, Körper und Gesundheit, nach dem Produkthaftungsgesetz und soweit wir eine Garantie übernommen haben.

        Bei leichter Fahrlässigkeit haften wir nur, wenn wir eine Pflicht verletzen, auf deren Erfüllung du vertrauen darfst und ohne die der Vertrag nicht durchführbar wäre. Die Haftung ist dann auf den Schaden begrenzt, mit dem bei Vertragsschluss typischerweise zu rechnen war.

        Für Inhalte anderer Nutzer:innen und für Geschäfte, die ihr ohne Kasse untereinander abschließt, haften wir nicht. Unsere Pflichten als Plattformbetreiberin, auf Meldungen zu reagieren, bleiben davon unberührt.
        TEXT,

    'laufzeit_titel' => '§ 15 Laufzeit und Kündigung',
    'laufzeit' => <<<'TEXT'
        Der Nutzungsvertrag läuft unbefristet. Du kannst ihn jederzeit ohne Frist beenden, indem du dein Konto im Profil löschst. Wir können mit einer Frist von 14 Tagen kündigen. Das Recht zur fristlosen Kündigung aus wichtigem Grund bleibt für beide Seiten bestehen; ein wichtiger Grund für uns ist insbesondere ein Verstoß gegen § 2 oder § 5.

        Laufende Bestellungen wickeln wir trotz Kündigung zu Ende ab.
        TEXT,

    'aenderung_titel' => '§ 16 Änderungen dieser Bedingungen',
    'aenderung' => 'Ändern wir diese Bedingungen, schreiben wir dir das mindestens sechs Wochen '
        . 'vorher an deine E-Mail-Adresse, mit einer Gegenüberstellung der Änderungen. Änderungen, '
        . 'die das Verhältnis von Leistung und Gegenleistung verschieben, gelten nur, wenn du '
        . 'ausdrücklich zustimmst. Stimmst du nicht zu, bleibt es für dich bei der alten Fassung; '
        . 'wir können den Vertrag dann nach § 15 ordentlich kündigen.',

    'schluss_titel' => '§ 17 Schlussbestimmungen',
    'schluss' => <<<'TEXT'
        Es gilt deutsches Recht unter Ausschluss des UN-Kaufrechts. Bist du Verbraucher:in mit gewöhnlichem Aufenthalt in einem anderen Staat der Europäischen Union, behältst du den Schutz der zwingenden Vorschriften dieses Staates.

        Vertragssprache ist Deutsch. An Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle nehmen wir nicht teil. Ist eine Bestimmung unwirksam, bleiben die übrigen gültig.
        TEXT,

    'stand' => 'Stand: 14. November 2026',
];
