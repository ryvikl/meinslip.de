<?php

declare(strict_types=1);

/**
 * Termine — vorschlagen, annehmen, beidseitig quittieren.
 *
 * DIESE TEXTE MÜSSEN EINE SACHE LEISTEN, DIE KEINE ANDERE SPRACHDATEI DES
 * PROJEKTS LEISTEN MUSS: Sie beschreiben einen Vorgang, an dessen Ende zwei
 * Menschen einander gegenüberstehen. Drei Regeln halten sie deshalb ein:
 *
 *  - Kein Text fragt nach einem Ort, einer Adresse oder einer Wegbeschreibung,
 *    und keiner lädt dazu ein, so etwas in die Region zu schreiben. Der Hinweis
 *    am Feld sagt ausdrücklich, was dort NICHT hingehört. Ein Freitextfeld wird
 *    zu dem, wonach seine Beschriftung fragt.
 *  - Kein Text verharmlost die Haustür-Übergabe und keiner verbietet sie. Sie
 *    steht als letzte Wahl mit einer Erklärung, die sagt, was sie bedeutet —
 *    genauso, wie „KI-unterstützt" in chat.php gleichberechtigt neben den
 *    anderen Angaben steht, ohne entschuldigenden Zusatz.
 *  - Kein Text verspricht Sicherheit. „Öffentlicher Ort" ist eine Beschreibung,
 *    kein Siegel. Die Plattform sichert eine Verabredung ab, keinen Menschen —
 *    zwischen diesen beiden Dingen liegt die gesamte Haftung
 *    (docs/04-features/safe-meet.md).
 *
 * FLACHE SCHLÜSSEL. Lang::t() zerlegt nur am ERSTEN Punkt: 'treffpunkt.bahnhof'
 * ist ein Schlüssel mit Punkt, kein verschachteltes Array. Ein Unterarray ergäbe
 * sichtbar '[[termin.treffpunkt.bahnhof]]'.
 *
 * PLATZHALTER heißen ':name' und werden per str_replace ersetzt. Kein Name darf
 * Präfix eines anderen sein — deshalb ':zeichen', ':tage' und ':stunden', die
 * einander nicht schlucken können.
 *
 * Zusammengesetzte Schlüssel (te('termin.treffpunkt.' . $wert),
 * te('termin.fehler.' . $fehler), te('termin.status.' . $status)) prüft
 * UebersetzungenTest NICHT — sein regulärer Ausdruck erkennt nur die Form
 * te('a.b'). Dafür gibt es tests/TermineTest.php. Wer hier einen Schlüssel
 * entfernt, macht ihn rot.
 */
return [
    // --- Abschnitt im Chatfenster -------------------------------------------
    'abschnitt_titel' => 'Termine',
    'abschnitt_leer' => 'Für dieses Gespräch ist noch kein Termin vorgeschlagen.',
    'abschnitt_erklaerung' => 'Ein Termin hält nur fest, wann und in welcher Art von Umgebung ihr euch treffen '
        . 'wollt. MeinSlip speichert keinen Standort, keine Adresse und keinen Kartenpunkt — auch dann nicht, '
        . 'wenn ihr euch darauf einigt.',
    'abschnitt_neu' => 'Termin vorschlagen',

    'karte_wann' => 'Wann',
    'karte_wo' => 'Art des Treffpunkts',
    'karte_region' => 'Region',
    'karte_von_dir' => 'Von dir vorgeschlagen',
    'karte_von_gegenueber' => 'Vorgeschlagen',
    'karte_grund' => 'Begründung: :grund',
    'karte_deine_quittung' => 'Du hast quittiert',
    'karte_fremde_quittung' => 'Gegenüber hat quittiert',
    'karte_wartet_auf_gegenueber' => 'Deine Quittung steht. Sobald dein Gegenüber ebenfalls quittiert, '
        . 'gilt die Übergabe als bestätigt.',
    'karte_wartet_auf_dich' => 'Dein Gegenüber hat quittiert. Deine Bestätigung fehlt noch.',
    'karte_eigener_vorschlag' => 'Du hast diesen Termin vorgeschlagen — annehmen kann ihn nur dein Gegenüber.',
    'karte_noch_zu_frueh' => 'Quittieren geht erst, wenn der Zeitpunkt erreicht ist.',

    'knopf_annehmen' => 'Annehmen',
    'knopf_ablehnen' => 'Ablehnen',
    'knopf_quittieren' => 'Übergabe quittieren',
    'feld_grund' => 'Begründung (freiwillig)',
    'hinweis_grund' => 'Höchstens :zeichen Zeichen. Du musst nichts begründen.',

    // --- Zustände -----------------------------------------------------------
    // Die fünf Werte aus Termine::UEBERGAENGE. Fehlt einer, wird
    // tests/TermineTest.php rot.
    'status.vorgeschlagen' => 'Vorgeschlagen',
    'status.angenommen' => 'Angenommen',
    'status.uebergeben' => 'Übergabe bestätigt',
    'status.abgelehnt' => 'Abgelehnt',
    'status.verfallen' => 'Verfallen',

    'status_erklaerung.vorgeschlagen' => 'Wartet auf die Antwort des Gegenübers.',
    'status_erklaerung.angenommen' => 'Beide sind einverstanden. Nach dem Treffen quittiert jede Seite für sich.',
    'status_erklaerung.uebergeben' => 'Beide haben quittiert. Damit ist die Übergabe beidseitig bestätigt.',
    'status_erklaerung.abgelehnt' => 'Dieser Vorschlag wurde nicht angenommen. Ein neuer Vorschlag ist jederzeit '
        . 'möglich.',
    'status_erklaerung.verfallen' => 'Dieser Vorschlag ist ohne Antwort abgelaufen oder wurde nach dem Treffen '
        . 'nicht von beiden Seiten quittiert.',

    // --- Treffpunktarten ----------------------------------------------------
    // Die fünf Werte aus Termine::TREFFPUNKTARTEN. Kein Freitext, deshalb auch
    // kein Feld, in das jemand eine Anschrift schreiben könnte.
    'treffpunkt.oeffentlicher_ort' => 'Öffentlicher Ort',
    'treffpunkt.bahnhof' => 'Bahnhof oder Haltestelle',
    'treffpunkt.cafe' => 'Café oder Lokal',
    'treffpunkt.paketshop' => 'Paketshop oder Packstation',
    'treffpunkt.haustuer' => 'Übergabe an der Haustür',

    'treffpunkt_erklaerung.oeffentlicher_ort' => 'Ein Ort mit Publikum — Platz, Park, Einkaufsstraße. '
        . 'Den genauen Punkt macht ihr im Gespräch aus, nicht hier.',
    'treffpunkt_erklaerung.bahnhof' => 'Belebt, beleuchtet und meist videoüberwacht. Für viele die Wahl, '
        . 'bei der sie sich am wohlsten fühlen.',
    'treffpunkt_erklaerung.cafe' => 'Drinnen, mit Personal in der Nähe. Gut, wenn es etwas länger dauern darf.',
    'treffpunkt_erklaerung.paketshop' => 'Die Übergabe ohne Treffen: Eine Seite gibt ab, die andere holt ab. '
        . 'Keine von beiden muss der anderen begegnen.',
    'treffpunkt_erklaerung.haustuer' => 'Eine Seite erfährt dabei, wo die andere wohnt — nicht über MeinSlip, '
        . 'aber im Ergebnis doch. Das lässt sich später nicht zurücknehmen. Wähle das nur, wenn du es '
        . 'ausdrücklich willst.',

    // --- Formular -----------------------------------------------------------
    'neu_kicker' => 'Übergabe verabreden',
    'neu_titel' => 'Termin vorschlagen',
    'neu_unterzeile' => 'Du schlägst Zeitpunkt, Art des Treffpunkts und Region vor. Dein Gegenüber nimmt an '
        . 'oder lehnt ab — annehmen kannst du deinen eigenen Vorschlag nicht.',

    'neu_gegenueber' => 'Termin mit :gegenueber',
    'neu_unbekannt_titel' => 'Gespräch nicht gefunden',
    'neu_unbekannt_text' => 'Ein Termin gehört immer zu einem Gespräch. Dieses gibt es nicht, oder es gehört '
        . 'nicht zu deinem Konto.',

    'feld_zeitpunkt' => 'Wann?',
    'hinweis_zeitpunkt' => 'Der Zeitpunkt muss in der Zukunft liegen und höchstens :tage Tage voraus.',
    'hinweis_utc' => 'Alle Zeiten auf MeinSlip stehen in koordinierter Weltzeit (UTC). Die deutsche Uhrzeit '
        . 'liegt davor: im Winter eine Stunde, im Sommer zwei. Für 17 Uhr deutscher Sommerzeit trägst du '
        . 'also 15:00 ein.',

    'feld_treffpunkt' => 'Art des Treffpunkts',
    'hinweis_treffpunkt' => 'Feste Auswahl statt Freitext. Den genauen Punkt macht ihr im Gespräch aus — '
        . 'er wird hier nicht gespeichert.',

    'feld_region' => 'Region',
    'hinweis_region' => 'Grobe Region, nie eine Adresse — etwa „Raum München". Höchstens :zeichen Zeichen. '
        . 'Straße, Hausnummer oder Treffpunktname gehören hier nicht hinein.',

    'warnung_kein_standort' => 'MeinSlip fragt niemals nach deinem Standort und speichert keinen. Wer dich '
        . 'darum bittet, tut das außerhalb dieser Plattform — und du musst es nicht.',
    'warnung_frei_entscheiden' => 'Ein angenommener Termin verpflichtet zu nichts. Du kannst jederzeit '
        . 'absagen, das Gespräch beenden oder die Person sperren.',

    'neu_speichern' => 'Termin vorschlagen',
    'neu_zurueck' => 'Zurück zum Gespräch',

    // --- Störung ------------------------------------------------------------
    'gestoert_titel' => 'Gerade nicht erreichbar',
    'gestoert_text' => 'Termine lassen sich im Moment nicht laden. Das liegt an uns, nicht an dir — '
        . 'versuch es gleich noch einmal.',

    // --- Rückmeldungen ------------------------------------------------------
    'erfolg.termin_vorgeschlagen' => 'Dein Vorschlag steht im Gespräch. Dein Gegenüber entscheidet.',
    'erfolg.termin_angenommen' => 'Der Termin ist angenommen. Nach dem Treffen quittiert ihr beide für euch.',
    'erfolg.termin_abgelehnt' => 'Der Vorschlag ist abgelehnt.',
    'erfolg.termin_quittiert' => 'Deine Quittung steht. Die Übergabe gilt erst als bestätigt, wenn auch dein '
        . 'Gegenüber quittiert hat.',
    'erfolg.termin_uebergeben' => 'Beide Quittungen liegen vor. Die Übergabe ist damit beidseitig bestätigt.',
    // Fällt an, wenn eine gebaute Adresse einen unbekannten Wert trägt —
    // TerminRouten::ausListe() zieht ihn auf 'unbekannt'.
    'erfolg.unbekannt' => 'Das ist erledigt.',

    // --- Fehler -------------------------------------------------------------
    // Die ersten zwanzig sind wörtlich die Schlüssel von TerminFehler, danach
    // drei aus TerminRouten. Kein Text nennt eine Kennung, eine Uhrzeit oder
    // eine Person: Dass zwei bestimmte Menschen verabredet sind, ist die
    // empfindlichste Auskunft dieser Plattform.
    'fehler.unterhaltung_unbekannt' => 'Dieses Gespräch gibt es nicht, oder es gehört nicht zu deinem Konto.',
    'fehler.nicht_teilnehmer' => 'Dieser Termin gehört nicht zu deinem Konto.',
    'fehler.gesperrt' => 'In diesem Gespräch lässt sich gerade nichts verabreden. Zwischen euch besteht eine '
        . 'Sperre.',
    'fehler.termin_unbekannt' => 'Diesen Termin gibt es nicht.',
    'fehler.zeitpunkt_ungueltig' => 'Diesen Zeitpunkt können wir nicht lesen. Bitte wähle Datum und Uhrzeit '
        . 'noch einmal.',
    'fehler.zeitpunkt_vergangen' => 'Der Zeitpunkt liegt in der Vergangenheit. Wähle einen, der noch kommt.',
    'fehler.zeitpunkt_zu_fern' => 'Der Zeitpunkt liegt zu weit voraus. Höchstens drei Monate.',
    'fehler.treffpunkt_unbekannt' => 'Bitte wähle eine der angebotenen Arten von Treffpunkt.',
    'fehler.region_fehlt' => 'Ohne grobe Region kann dein Gegenüber nicht einschätzen, ob der Termin passt. '
        . 'Bitte trag eine ein.',
    'fehler.region_zu_lang' => 'Die Region ist zu lang. Sie soll grob sein, nicht genau.',
    'fehler.grund_zu_lang' => 'Die Begründung ist zu lang. Kürze sie und schick sie noch einmal ab.',
    'fehler.eigener_vorschlag' => 'Deinen eigenen Vorschlag kannst du weder annehmen noch ablehnen. Das '
        . 'entscheidet dein Gegenüber.',
    'fehler.nicht_offen' => 'Dieser Vorschlag ist nicht mehr offen. Schlag einen neuen Termin vor.',
    'fehler.nicht_angenommen' => 'Quittieren geht erst, wenn der Termin angenommen ist.',
    'fehler.zu_frueh' => 'Quittieren geht erst, wenn der Zeitpunkt erreicht ist.',
    'fehler.bereits_quittiert' => 'Du hast diesen Termin schon quittiert. Jetzt fehlt nur noch dein Gegenüber.',
    'fehler.zu_schnell' => 'Das waren zu viele Terminvorschläge in kurzer Zeit. Warte einen Moment — '
        . 'deine Eingaben bleiben stehen.',
    'fehler.zu_viele_offen' => 'In diesem Gespräch stehen schon mehrere unbeantwortete Vorschläge von dir. '
        . 'Warte die Antwort ab, bevor du einen weiteren machst.',
    'fehler.status_unbekannt' => 'Dieser Termin steht in einem Zustand, den wir nicht kennen. Bitte melde dich '
        . 'bei uns.',
    'fehler.unerlaubter_wechsel' => 'Dieser Schritt ist an dieser Stelle nicht vorgesehen.',

    'fehler.termin_ungueltig' => 'Es ist nicht erkennbar, welchen Termin du meinst.',
    'fehler.gestoert' => 'Das hat gerade nicht geklappt. Bitte versuch es in einem Moment noch einmal.',
    'fehler.unbekannt' => 'Das hat nicht geklappt. Bitte versuch es noch einmal.',
];
