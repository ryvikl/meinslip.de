<?php

declare(strict_types=1);

/**
 * Manuelle Identitätsprüfung und Altersschranke.
 *
 * Flach mit Punkt im Schlüssel, wie alle Sprachdateien dieses Projekts:
 * Lang::t trennt nur am ERSTEN Punkt und steigt nicht in Unterarrays ab. Ein
 * 'fehler' => [...] hier hieße '[[verifizierung.fehler.x]]' auf dem
 * Bildschirm.
 *
 * ZWEI REGELN FÜR JEDEN TEXT IN DIESER DATEI:
 *
 *  1. NIRGENDS „Alter geprüft", „altersverifiziert" oder etwas, das danach
 *     klingt. Was hier geprüft wird, ist ein Mensch mit einem Zettel — nicht
 *     sein Geburtsdatum. Ein Abzeichen, das mehr behauptet, als es trägt, ist
 *     schlimmer als gar keines (docs/04-features/verifizierung-altersstufen.md).
 *     Deshalb heißt es überall „Identität geprüft (manuell)", und überall, wo
 *     es steht, steht daneben, was es nicht ist.
 *
 *  2. DIE SEITE DER ALTERSSCHRANKE VERSPRICHT NICHTS. Sie sagt der Person,
 *     dass ihre Erklärung nichts freischaltet, und sie sagt auch warum. Eine
 *     Schaltfläche, die so tut, als öffne sie etwas, wäre eine Täuschung in
 *     beide Richtungen: gegenüber der Person und gegenüber der Aufsicht.
 *
 * Die Schlüssel unter 'fehler.' sind wörtlich die aus MedienFehler (über die
 * Bildpipeline), aus PruefbelegFehler und die eigenen von
 * VerifizierungsRouten. tests/PruefungenTest.php hält das nach, weil der
 * zusammengesetzte Schlüssel te('verifizierung.fehler.' . $x) von
 * UebersetzungenTest nicht erfasst wird.
 */

return [
    // --- Rahmen -----------------------------------------------------------
    'titel' => 'Identität bestätigen',
    'kicker' => 'Wer bist du?',
    'unterzeile' => 'Ein Foto von dir mit einem handgeschriebenen Zettel. Wir vergeben dir '
        . 'dafür einen Code, der :stunden Stunden gilt. Mehr brauchen wir nicht — und mehr '
        . 'behalten wir auch nicht.',

    'gestoert' => 'Der Stand lässt sich gerade nicht laden. Bitte versuche es später noch einmal.',

    // --- Das Abzeichen ----------------------------------------------------
    // Der Name steht an genau einer Stelle. Wer ihn ändert, ändert ihn
    // überall — und muss den Satz darunter mitändern.
    'abzeichen' => 'Identität geprüft (manuell)',
    // Dieser Satz gehört an JEDE Stelle, an der das Abzeichen erscheint. Er
    // ist der Unterschied zwischen einem ehrlichen Hinweis und einer
    // Behauptung, für die es keine Grundlage gibt.
    'abzeichen_was_nicht' => 'Das Abzeichen sagt: Ein Mensch aus unserem Team hat ein Foto '
        . 'von dir mit unserem Code gesehen. Es sagt nichts über dein Alter — dafür braucht '
        . 'es ein geprüftes Verfahren, und ein handgeschriebener Zettel ist keines.',
    'abzeichen_vorhanden' => 'Du trägst dieses Abzeichen.',
    'abzeichen_fehlt' => 'Du trägst dieses Abzeichen noch nicht.',

    // --- Vorgang starten --------------------------------------------------
    'start_titel' => 'Prüfung starten',
    'start_text' => 'Wir vergeben dir einen Code. Den schreibst du zusammen mit dem heutigen '
        . 'Datum auf einen Zettel, hältst ihn gut lesbar ins Bild und lädst genau ein Foto hoch.',
    'start_knopf' => 'Code anfordern',

    'code_titel' => 'Dein Code',
    'code_gilt_bis' => 'Gültig bis :zeitpunkt UTC',
    'code_warum' => 'Der Code kommt von uns und nicht von dir, und er läuft ab. Nur so ist '
        . 'das Foto an genau diesen Vorgang und an genau diesen Zeitraum gebunden — ein Bild '
        . 'ohne Frist ließe sich wiederverwenden oder kaufen.',
    'code_abgelaufen_titel' => 'Der Code ist abgelaufen',
    'code_abgelaufen_text' => 'Das ist kein Fehler von dir. Hol dir einen neuen Code und '
        . 'mach ein neues Foto — das alte gilt nicht mehr, weil das Datum darauf nicht mehr passt.',
    'code_neu' => 'Neuen Code anfordern',

    'zettel_titel' => 'Was auf den Zettel gehört',
    'zettel_code' => 'Der Code oben, Zeichen für Zeichen.',
    'zettel_datum' => 'Das heutige Datum in der Form Jahr-Monat-Tag.',
    'zettel_hand' => 'Von Hand geschrieben, nicht gedruckt und nicht auf einem Bildschirm.',
    'zettel_gesicht' => 'Dein Gesicht und der Zettel zusammen auf einem Bild, beides lesbar.',

    // --- Hochladen --------------------------------------------------------
    'beleg_titel' => 'Foto hochladen',
    'feld_datei' => 'Dein Foto',
    'hinweis_datei' => 'JPEG, PNG oder WebP, höchstens :mb MB. Größere Bilder rechnen wir auf '
        . ':kante Pixel herunter.',
    'hochladen' => 'Foto einreichen',
    'nur_eines' => 'Genau ein Foto je Vorgang. Nachreichen und Ersetzen gibt es nicht — wer '
        . 'nachbessern darf, bis es passt, probiert aus, welches Bild durchkommt.',
    'exif_hinweis' => 'Beim Hochladen rechnen wir das Bild neu. Aufnahmeort, Kameradaten und '
        . 'Uhrzeit verschwinden dabei. Ein Selfie trägt fast immer die Koordinaten deiner '
        . 'Wohnung — die kommen bei uns nie an.',

    // --- Stand des Vorgangs -----------------------------------------------
    'eingereicht_titel' => 'Dein Foto liegt bei uns',
    'eingereicht_text' => 'Ein Mensch aus unserem Team sieht es sich an. Du bekommst die '
        . 'Entscheidung mit Begründung in deinem Profil.',

    'freigegeben_titel' => 'Geprüft',
    'freigegeben_text' => 'Wir haben dein Foto gesehen und den Code erkannt.',
    'abgelehnt_titel' => 'Nicht anerkannt',
    'abgelehnt_text' => 'Wir konnten dem Foto nicht folgen. Der Grund steht darunter. Du '
        . 'kannst jederzeit neu anfangen.',
    'entscheidung_ueberschrift' => 'Begründung',
    'entschieden_am' => 'Entschieden am',
    'neu_starten' => 'Neu anfangen',

    // --- Die Löschfrist ---------------------------------------------------
    // Sie steht auf der Seite und nicht nur in der Datenschutzerklärung: Wer
    // ein Foto von sich hochlädt, soll vorher lesen, wann es wieder weg ist.
    'frist_titel' => 'Wann das Foto wieder weg ist',
    'frist_text' => 'Spätestens :entscheidungstage Tage nach der Entscheidung, spätestens '
        . ':tage Tage nach dem Start des Vorgangs — was zuerst eintritt. Danach ist das Foto '
        . 'gelöscht, samt Vorschau. Was bleibt, ist das Ergebnis: geprüft ja oder nein.',
    'frist_loeschung_am' => 'Wird gelöscht ab :zeitpunkt UTC',

    // --- Altersschranke ---------------------------------------------------
    'schranke_titel' => 'Altersschranke',
    'schranke_kicker' => 'Selbsterklärung',
    'schranke_was_das_ist' => 'Hier erklärst du, dass du volljährig bist. Diese Erklärung '
        . 'gilt :minuten Minuten und hängt an dieser Sitzung — nicht an deinem Konto.',

    // Der Kern der Seite. Sie sagt zuerst, was sie nicht ist.
    'schranke_ehrlich_titel' => 'Was diese Erklärung nicht ist',
    'schranke_ehrlich_1' => 'Eine Selbsterklärung ist keine geschlossene Benutzergruppe nach '
        . '§ 4 Abs. 2 JMStV. Sie ist überhaupt keine Schranke, sondern eine Schaltfläche.',
    'schranke_ehrlich_2' => 'Der Bundesgerichtshof hat 2007 sogar die Abfrage von '
        . 'Ausweisnummer und Adresse zusammen mit einer Kontoüberweisung als unzureichend '
        . 'verworfen (I ZR 102/05). Ein Knopf ist weniger als das.',
    'schranke_ehrlich_3' => 'Deshalb schaltet deine Erklärung hier nichts frei. Inhalte, die '
        . 'als nicht jugendfrei gekennzeichnet sind, bekommt außer der hochladenden Person '
        . 'und unserem Team niemand zu sehen — mit Erklärung genauso wenig wie ohne.',
    'schranke_ehrlich_4' => 'Das ändert sich erst, wenn ein von der KJM positiv bewertetes '
        . 'Verfahren angebunden ist. Bis dahin wäre jede Freischaltung eine Lücke, die so '
        . 'tut, als wäre sie eine Prüfung.',

    'schranke_knopf' => 'Ich bin volljährig',
    'schranke_gilt' => 'Deine Erklärung gilt derzeit.',
    'schranke_gilt_nicht' => 'Derzeit liegt keine gültige Erklärung vor.',
    'schranke_laeuft_ab' => 'Sie läuft nach :minuten Minuten ab und muss dann erneut '
        . 'abgegeben werden. Angemeldet zu sein genügt ausdrücklich nicht — das AVS-Raster '
        . 'der KJM verlangt eine Bestätigung bei jedem Nutzungsvorgang.',
    'schranke_ohne_sitzung' => 'Die Erklärung wird an der Sitzung vermerkt. Ohne Anmeldung '
        . 'gibt es keine Sitzung und damit nichts, woran sie hängen könnte.',
    'schranke_anmelden' => 'Anmelden',
    'schranke_gebunden' => 'Ein geprüftes Altersverfahren ist angebunden.',

    // --- Rückmeldungen ----------------------------------------------------
    'erfolg.code_vergeben' => 'Dein Code steht bereit. Schreib ihn mit dem heutigen Datum auf '
        . 'einen Zettel.',
    'erfolg.beleg_eingereicht' => 'Dein Foto ist bei uns. Wir melden uns über dein Profil.',
    'erfolg.schranke_bestanden' => 'Deine Erklärung ist vermerkt. Sie schaltet nichts frei — '
        . 'warum, steht oben.',

    // Aus Bilder::annehmen(). Die Texte sagen, was zu tun ist, nicht was
    // schiefging: „Die Dekodierung ist fehlgeschlagen" ist für jemanden, der
    // gerade ein Foto von sich hochladen wollte, keine Auskunft.
    'fehler.animation_nicht_erlaubt' => 'Bewegte Bilder gehen nicht. Nimm ein Einzelfoto.',
    'fehler.bildverarbeitung_fehlt' => 'Auf diesem Server fehlt die Bildverarbeitung. Bitte '
        . 'melde dich bei uns — hochladen kann gerade niemand.',
    'fehler.datei_zu_gross' => 'Die Datei ist größer als :mb MB. Verkleinere sie oder nimm '
        . 'ein anderes Foto.',
    'fehler.dekodierung_fehlgeschlagen' => 'Diese Datei lässt sich nicht als Bild lesen. '
        . 'Speichere sie noch einmal als JPEG und versuche es erneut.',
    'fehler.format_nicht_erlaubt' => 'Dieses Format nehmen wir nicht an. Erlaubt sind JPEG, '
        . 'PNG und WebP.',
    'fehler.format_nicht_unterstuetzt' => 'Dieses Format kann der Server nicht lesen. '
        . 'Speichere das Foto als JPEG.',
    'fehler.kein_bild' => 'In der Datei steckt kein Bild. Hast du versehentlich etwas anderes '
        . 'ausgewählt?',
    'fehler.nicht_hochgeladen' => 'Der Upload ist unterwegs verloren gegangen. Bitte versuche '
        . 'es noch einmal.',
    'fehler.speicher_reicht_nicht' => 'Das Foto hat zu viele Bildpunkte für diesen Server. '
        . 'Verkleinere es und lade es erneut hoch.',
    'fehler.speichern_fehlgeschlagen' => 'Das Foto ließ sich nicht ablegen. Bitte versuche es '
        . 'noch einmal.',
    'fehler.typ_widerspruch' => 'Die Datei gibt sich als etwas aus, das sie nicht ist. '
        . 'Speichere das Foto neu und versuche es erneut.',
    'fehler.upload_fehlgeschlagen' => 'Der Upload ist fehlgeschlagen. Bitte versuche es noch '
        . 'einmal.',
    'fehler.upload_zu_gross' => 'Das Foto ist zu groß für den Server. Verkleinere es auf '
        . 'höchstens :mb MB.',
    'fehler.verarbeitung_fehlgeschlagen' => 'Beim Umrechnen des Fotos ist etwas '
        . 'schiefgegangen. Bitte versuche es noch einmal.',
    'fehler.ziel_unbrauchbar' => 'Der Ablageort ist nicht beschreibbar. Bitte melde dich bei uns.',
    'fehler.zu_viele_pixel' => 'Das Foto hat zu viele Bildpunkte. Verkleinere es und lade es '
        . 'erneut hoch.',

    // Aus PruefbelegFehler
    'fehler.kein_vorgang' => 'Es läuft gerade keine Prüfung. Fordere zuerst einen Code an.',
    'fehler.code_abgelaufen' => 'Der Code ist abgelaufen. Hol dir einen neuen und mach ein '
        . 'neues Foto — auf dem alten steht ein Datum, das nicht mehr passt.',
    'fehler.beleg_schon_eingereicht' => 'Zu diesem Vorgang liegt bereits ein Foto vor. Warte '
        . 'die Entscheidung ab; danach kannst du neu anfangen.',
    'fehler.beleg_unbekannt' => 'Diesen Vorgang gibt es nicht.',
    'fehler.beleg_nicht_offen' => 'Über diesen Vorgang ist bereits entschieden.',
    'fehler.entscheidung_fehlt' => 'Ohne Begründung wird nichts entschieden.',
    'fehler.zeitpunkt_ungueltig' => 'Der Zeitpunkt ist nicht lesbar.',
    'fehler.gestoert' => 'Gerade geht das nicht. Bitte versuche es später noch einmal.',

    // Aus VerifizierungsRouten
    'fehler.keine_datei' => 'Du hast keine Datei ausgewählt.',
    'fehler.post_zu_gross' => 'Das Foto war zu groß, um überhaupt anzukommen — der Server hat '
        . 'die Übertragung abgebrochen. Verkleinere es auf höchstens :mb MB und versuche es '
        . 'noch einmal.',
    'fehler.unbekannt' => 'Das hat nicht funktioniert. Bitte versuche es noch einmal.',
];
