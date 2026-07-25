<?php

declare(strict_types=1);

/**
 * Marktplatz: Katalog, Angebotsseite, Konfigurator, Verkaufen, Bestellung.
 *
 * Die Rechtstexte in dieser Datei sind keine Zierde. Der Widerrufsausschluss
 * stuetzt sich auf § 312g Abs. 2 Nr. 1 BGB (Anfertigung nach
 * Kundenspezifikation); die Pflicht, darueber VOR der Bestellung zu
 * unterrichten, folgt aus § 312d Abs. 1 BGB i. V. m. Art. 246a § 1 Abs. 3
 * Nr. 1 EGBGB. Wer diese Saetze kuerzt, kuerzt die Rechtsgrundlage mit.
 *
 * DREI SCHLUESSEL SIND NICHT FREI WAEHLBAR:
 *
 *  - 'konfigurator_absenden' ist die Beschriftung der Bestellschaltflaeche.
 *    § 312j Abs. 3 S. 2 BGB verlangt, dass sie "mit nichts anderem als den
 *    Woertern 'zahlungspflichtig bestellen' oder mit einer entsprechenden
 *    eindeutigen Formulierung" beschriftet ist. Nach EuGH C-249/21
 *    (Fuhrmann-2) zaehlt allein der Text AUF der Schaltflaeche; Umgebungstext
 *    heilt nichts. Wer hier 'Verbindlich bestellen', 'Sicher bestellen' oder
 *    'Jetzt absenden' einsetzt, verhindert nach § 312j Abs. 4 BGB das
 *    Zustandekommen des Vertrags — die Ware waere geliefert und bezahlt,
 *    ohne dass ein Kaufvertrag bestuende.
 *
 *  - 'konfigurator_summe_gesamt' und 'konfigurator_summe_vorlaeufig' sind die
 *    Beschriftungen des Betrags unmittelbar ueber der Schaltflaeche. Welche
 *    von beiden erscheint, entscheidet resources/views/markt/bestellen.php
 *    danach, ob der Betrag ohne JavaScript beweisbar vollstaendig ist.
 *    § 312j Abs. 2 BGB verlangt dort den Gesamtpreis — eine Zahl, die
 *    "Gesamtbetrag" heisst und es nicht ist, ist schlechter als keine.
 *
 *  - 'preis_hinweis' erfuellt § 6 Abs. 1 PAngV. Der Satz zu den Versandkosten
 *    ist durch das Schema gedeckt: database/migrations/004_katalog.php kennt
 *    keine Versandkostenspalte, Bestellungen::anlegen() addiert keine. Wird
 *    das je eingefuehrt, muss dieser Satz im selben Schritt mitwandern.
 *
 * Die Schluessel sind flach mit Punkt geschrieben — Lang::t zerlegt nur am
 * ersten Punkt, echte Unterarrays funktionieren nicht.
 */
return [
    // --- Katalog einer Kategorie ------------------------------------------
    'kategorie_kicker' => 'Kategorie',
    'kategorie_anzahl' => ':anzahl Angebote in dieser Kategorie.',
    'kategorie_leer_titel' => 'Noch nichts hier',
    'kategorie_leer_text' => 'In dieser Kategorie ist gerade kein Angebot freigeschaltet. '
        . 'Schau später wieder vorbei — oder biete selbst etwas an.',
    'kategorie_unbekannt_titel' => 'Diese Kategorie gibt es nicht',
    'kategorie_unbekannt_text' => 'Der Weg führt ins Leere. Vielleicht hilft die Übersicht weiter.',
    'kategorie_zurueck' => 'Zur Übersicht',
    'gestoert_titel' => 'Gerade nicht erreichbar',
    'gestoert_text' => 'Der Katalog ist im Moment nicht abrufbar. Bitte versuche es gleich noch einmal.',

    'blaettern' => 'Seitenwahl',
    'blaettern_zurueck' => 'Zurück',
    'blaettern_weiter' => 'Weiter',
    // ACHTUNG, DEFEKT: Lang::t ersetzt die Platzhalter nacheinander per
    // str_replace. ':seite' ist ein Praefix von ':seiten' und frisst dessen
    // Anfang — aus 'von :seiten' wird 'von 1n'. resources/lang/de-DE/verwaltung.php
    // umgeht das seit jeher mit ':gesamt'. Die Behebung braucht ZWEI Zeilen in
    // ZWEI Dateien und darf nur gemeinsam geschehen, sonst steht ein
    // unersetzter Platzhalter auf der Seite:
    //   hier:                             'Seite :seite von :gesamt'
    //   resources/views/markt/kategorie.php:77:
    //       te('markt.blaettern_stand', ['seite' => $seite, 'gesamt' => $seiten])
    // kategorie.php gehoert zu dieser Aenderung nicht in meine Dateihoheit.
    'blaettern_stand' => 'Seite :seite von :seiten',

    // --- Angebotsseite -----------------------------------------------------
    'angebot_kicker' => 'Angebot',
    'angebot_von' => 'Angeboten von',
    'angebot_grundpreis' => 'Grundpreis',
    // § 6 Abs. 1 PAngV: Zum Gesamtpreis gehoert die Angabe, dass die
    // Umsatzsteuer enthalten ist und ob Versandkosten hinzukommen. Der
    // angezeigte Betrag IST der Endpreis — Preisrechner::zerlegen() rechnet
    // die Umsatzsteuer aus dem Bruttobetrag heraus, statt sie aufzuschlagen.
    // Der Satz muss raeumlich am Preis stehen, nicht im Seitenfuss.
    'preis_hinweis' => 'Alle Preise sind Endpreise einschließlich der gesetzlichen Umsatzsteuer. '
        . 'Zusätzliche Fracht-, Liefer- oder Versandkosten fallen nicht an — '
        . 'weder beim anonymen Versand noch bei persönlicher Übergabe.',
    'angebot_bearbeitungstage' => 'Anfertigung in etwa :tage Tagen',
    'angebot_versand' => 'Anonymer Versand möglich',
    'angebot_uebergabe' => 'Persönliche Übergabe möglich',
    'angebot_region' => 'Raum: :region',
    'angebot_unbekannt_titel' => 'Dieses Angebot gibt es nicht',
    'angebot_unbekannt_text' => 'Der Weg führt ins Leere. Möglicherweise wurde das Angebot zurückgezogen.',
    'angebot_nicht_aktiv_titel' => 'Gerade nicht bestellbar',
    'angebot_nicht_aktiv_text' => 'Dieses Angebot ist im Moment nicht freigeschaltet. '
        . 'Es ist entweder noch in der Prüfung, pausiert oder zurückgezogen.',
    'angebot_beschreibung' => 'Beschreibung',

    // --- Konfigurator ------------------------------------------------------
    'konfigurator_kicker' => 'Konfigurator',
    'konfigurator_titel' => 'Nach deinen Angaben anfertigen',
    'konfigurator_unterzeile' => 'Was du hier festlegst, wird eigens für dich angefertigt. '
        . 'Genau das macht die Ware zu deiner — und schließt den Widerruf aus.',
    'konfigurator_pflicht' => 'Pflichtangabe',
    'konfigurator_spezifikation' => 'Spezifikation',
    'konfigurator_zusatz' => 'Zusatz',
    'konfigurator_auswaehlen' => 'Dazubuchen',
    'konfigurator_aufpreis' => 'Aufpreis :betrag',
    'konfigurator_ohne_aufpreis' => 'ohne Aufpreis',
    'konfigurator_warnung' => 'Bitte lege mindestens eine Spezifikation fest. '
        . 'Ohne Angabe von dir kann nichts nach deinen Wünschen angefertigt werden.',
    'konfigurator_keine_optionen_titel' => 'Noch nicht bestellbar',
    'konfigurator_keine_optionen_text' => 'Diesem Angebot fehlt die Spezifikation, '
        . 'ohne die keine Anfertigung nach Kundenwunsch möglich ist.',
    'konfigurator_lieferart' => 'Lieferung',
    'konfigurator_lieferart_versand' => 'Anonymer Versand',
    'konfigurator_lieferart_uebergabe' => 'Persönliche Übergabe',
    'konfigurator_summe_titel' => 'Das bestellst du',
    'konfigurator_summe_grundpreis' => 'Grundpreis',
    // Beide Beschriftungen gehoeren zusammen: 'gesamt' erscheint nur, wenn das
    // Angebot ueberhaupt keine Option mit Aufpreis hat und der Betrag damit
    // auch ohne JavaScript beweisbar der Gesamtpreis ist. Sonst 'vorlaeufig'.
    'konfigurator_summe_gesamt' => 'Gesamtbetrag',
    'konfigurator_summe_vorlaeufig' => 'Vorläufiger Gesamtbetrag',
    'konfigurator_summe_aufpreise' => 'Aufpreise dieses Angebots',
    'konfigurator_summe_aufpreis_zeile' => ':bezeichnung: Aufpreis :betrag',
    'konfigurator_summe_offen' => 'Der Betrag enthält den Grundpreis und die Aufpreise der Angaben, '
        . 'die beim Aufbau dieser Seite feststanden. Jede weitere Angabe, die du oben machst, '
        . 'kostet den hier genannten Aufpreis zusätzlich.',
    'konfigurator_summe_ohne_js' => 'Ohne JavaScript wird dieser Betrag nicht mitgerechnet. '
        . 'Rechne die oben aufgeführten Aufpreise deiner Angaben selbst hinzu — '
        . 'abgerechnet wird die Summe, die der Server nach dem Absenden bildet.',
    'konfigurator_summe_hinweis' => 'Der Betrag wird beim Bestellen aus deinem Guthaben hinterlegt '
        . 'und erst nach Ablauf deiner Prüfzeit an die Verkäuferin ausgezahlt.',
    // Wortlaut des § 312j Abs. 3 S. 2 BGB. Siehe Kopfkommentar dieser Datei:
    // "mit nichts anderem als" — keine Zusaetze, kein Betrag, kein Icon.
    'konfigurator_absenden' => 'Zahlungspflichtig bestellen',

    // --- Widerruf: Pflichtinformation VOR der Bestellung -------------------
    'widerruf_titel' => 'Kein Widerrufsrecht bei Anfertigung nach deinen Angaben',
    'widerruf_text' => 'Diese Ware wird nach deinen Angaben eigens für dich angefertigt. '
        . 'Für solche Waren besteht nach § 312g Abs. 2 Nr. 1 BGB kein Widerrufsrecht. '
        . 'Du kannst die Bestellung also nach dem Absenden nicht mehr widerrufen. '
        . 'Deine Rechte bei mangelhafter Ware bleiben davon unberührt, und der Betrag bleibt '
        . 'bis zum Ende deiner Prüfzeit hinterlegt.',
    'widerruf_bestaetigung' => 'Ich habe verstanden, dass die Ware nach meinen Angaben angefertigt wird '
        . 'und dass mir deshalb kein Widerrufsrecht zusteht.',

    // --- Zugang ------------------------------------------------------------
    'anmeldung_noetig_titel' => 'Dafür brauchst du ein Konto',
    'anmeldung_noetig_text' => 'Bestellen geht nur angemeldet. Ein Konto genügt für Kaufen und Verkaufen.',
    'anmelden_knopf' => 'Anmelden',
    'registrieren_knopf' => 'Konto erstellen',
    'kaufen_gesperrt_titel' => 'Erst die Altersprüfung',
    'kaufen_gesperrt_text' => 'Bestellen wird nach bestandener Altersprüfung freigeschaltet. '
        . 'Das ist keine Formalie: § 4 Abs. 2 JMStV verlangt eine geschlossene Benutzergruppe.',
    'verkaufen_gesperrt_titel' => 'Verkaufen ist noch nicht freigeschaltet',
    'verkaufen_gesperrt_text' => 'Verkaufen ist eine Freischaltung, kein zweites Konto. '
        . 'Sie folgt auf die Identitätsprüfung — ohne geprüfte Identität gibt es kein Impressum '
        . 'und keine steuerlich saubere Auszahlung.',

    // --- Meine Angebote ----------------------------------------------------
    'verkaufen_kicker' => 'Verkaufen',
    'verkaufen_titel' => 'Meine Angebote',
    'verkaufen_unterzeile' => 'Jedes Angebot beginnt als Entwurf, geht durch die Prüfung und '
        . 'erscheint erst danach im Katalog.',
    'verkaufen_leer_titel' => 'Noch kein Angebot',
    'verkaufen_leer_text' => 'Leg dein erstes Angebot an. Du kannst es in Ruhe als Entwurf vorbereiten '
        . 'und erst einreichen, wenn es fertig ist.',
    'verkaufen_neu' => 'Neues Angebot',
    'verkaufen_bearbeiten' => 'Bearbeiten',
    'verkaufen_ansehen' => 'Im Katalog ansehen',
    'spalte_titel' => 'Titel',
    'spalte_status' => 'Status',
    'spalte_preis' => 'Grundpreis',
    'spalte_angelegt' => 'Angelegt',
    'spalte_aktion' => 'Aktion',

    'status.entwurf' => 'Entwurf',
    'status.in_pruefung' => 'In Prüfung',
    'status.aktiv' => 'Im Katalog',
    'status.pausiert' => 'Pausiert',
    'status.entfernt' => 'Zurückgezogen',

    // --- Angebot anlegen ---------------------------------------------------
    'anlegen_kicker' => 'Neues Angebot',
    'anlegen_titel' => 'Angebot anlegen',
    'anlegen_unterzeile' => 'Das Angebot entsteht als Entwurf. Sichtbar wird es erst nach der Prüfung.',
    'anlegen_absenden' => 'Entwurf anlegen',
    'anlegen_naechster_schritt' => 'Im nächsten Schritt legst du die Optionen des Konfigurators fest. '
        . 'Mindestens eine davon muss eine Spezifikation sein — sonst lässt sich das Angebot '
        . 'nicht einreichen und wäre unverkäuflich.',

    'feld_titel' => 'Titel',
    'feld_beschreibung' => 'Beschreibung',
    'feld_kategorie' => 'Kategorie',
    'feld_grundpreis' => 'Grundpreis in Euro',
    'feld_bearbeitungstage' => 'Anfertigung in Tagen',
    'feld_versand' => 'Anonymer Versand möglich',
    'feld_uebergabe' => 'Persönliche Übergabe möglich',
    'feld_uebergabe_region' => 'Region für die Übergabe',

    'hinweis_titel' => 'Bis 190 Zeichen. Der Titel steht im Katalog.',
    'hinweis_beschreibung' => 'Beschreibe die Ware ehrlich. Keine Kontaktdaten, keine Absprachen an der Plattform vorbei.',
    'hinweis_grundpreis' => 'Ohne Optionen. Aufpreise legst du im nächsten Schritt fest.',
    'hinweis_bearbeitungstage' => 'Zwischen 1 und 90 Tagen. Halte die Zusage lieber knapp und sicher.',
    'hinweis_uebergabe_region' => 'Grobe Region, nie eine Adresse — etwa „Raum München“. Höchstens 40 Zeichen.',
    'auswahl_bitte_waehlen' => 'Bitte wählen',

    // --- Angebot bearbeiten ------------------------------------------------
    'bearbeiten_kicker' => 'Angebot bearbeiten',
    'stammdaten_kicker' => 'Angaben zur Ware',
    'bearbeiten_stammdaten' => 'Stammdaten',
    'bearbeiten_speichern' => 'Änderungen speichern',
    'bearbeiten_gesperrt' => 'In diesem Status sind keine Änderungen möglich. '
        . 'Pausiere das Angebot, wenn du es überarbeiten willst.',

    'optionen_titel' => 'Optionen des Konfigurators',
    'optionen_unterzeile' => 'Eine Option mit Spezifikation ist Pflicht. Sie ist die Angabe, '
        . 'nach der die Ware für die Käuferin angefertigt wird — und damit die Grundlage '
        . 'des Widerrufsausschlusses nach § 312g Abs. 2 Nr. 1 BGB.',
    'optionen_leer' => 'Noch keine Option angelegt.',
    'optionen_neu' => 'Option anlegen oder überschreiben',
    'option_schluessel' => 'Schlüssel',
    'option_bezeichnung' => 'Bezeichnung',
    'option_art' => 'Art der Eingabe',
    'option_aufpreis' => 'Aufpreis in Euro',
    'option_erlaeuterung' => 'Erläuterung',
    'option_reihenfolge' => 'Reihenfolge',
    'option_ist_spezifikation' => 'Ist eine Spezifikation',
    'option_pflicht' => 'Pflichtangabe',
    'option_speichern' => 'Option speichern',
    'option_entfernen' => 'Entfernen',
    'option_hinweis_schluessel' => 'Kleinbuchstaben, Ziffern und Unterstrich, 2 bis 80 Zeichen. '
        . 'Der Schlüssel bleibt für immer im Kaufbeleg stehen und lässt sich nicht ändern — '
        . 'ein gleicher Schlüssel überschreibt die vorhandene Option.',
    'option_hinweis_spezifikation' => 'Angekreuzt heißt: Diese Angabe macht die Ware zur Anfertigung '
        . 'nach Kundenwunsch. Ohne mindestens eine solche Option lässt sich nichts einreichen.',
    'option_hinweis_aufpreis' => 'Kein negativer Aufpreis. Preisnachlässe gehören in den Grundpreis.',

    'art.auswahl' => 'Ankreuzen (ja oder nein)',
    'art.zahl' => 'Zahl',
    'art.freitext' => 'Freier Text',

    'ablauf_titel' => 'Weg des Angebots',
    'einreichen' => 'Zur Prüfung einreichen',
    'einreichen_hinweis' => 'Nach dem Einreichen sieht ein Mensch das Angebot an. '
        . 'Bis dahin ist es nicht bearbeitbar.',
    'aktion_pausieren' => 'Pausieren',
    'aktion_fortsetzen' => 'Wieder anbieten',
    'aktion_entfernen' => 'Endgültig zurückziehen',
    'aktion_entfernen_hinweis' => 'Zurückgezogene Angebote kommen nicht zurück. '
        . 'Bereits verkaufte Bestellungen bleiben davon unberührt.',

    // --- Bestellung --------------------------------------------------------
    'bestellung_kicker' => 'Bestellung',
    'bestellung_nummer' => 'Bestellnummer',
    'bestellung_zustand' => 'Stand',
    'bestellung_lieferart' => 'Lieferung',
    'bestellung_angelegt' => 'Angelegt am',
    'bestellung_rolle' => 'Deine Rolle',
    'bestellung_rolle_kaeufer' => 'Käufer',
    'bestellung_rolle_verkaeufer' => 'Verkäuferin',

    // Spaltenköpfe der Spezifikationstabelle. Bei „Aufpreis" bewusst ohne
    // Währung: Die Zelle gibt den Betrag über geld() samt Währung der
    // Bestellung aus, ein „in Euro" im Kopf wäre dort irgendwann falsch.
    'bestellung_spalte_angabe' => 'Angabe',
    'bestellung_spalte_wert' => 'Deine Wahl',
    'bestellung_spalte_aufpreis' => 'Aufpreis',
    'bestellung_positionen' => 'Das hast du bestellt',
    'bestellung_spezifikationen' => 'Deine Angaben',
    'bestellung_summe' => 'Gesamtbetrag',
    'bestellung_ust' => 'davon Umsatzsteuer',
    'bestellung_provision' => 'davon Vermittlungsgebühr',
    'bestellung_auszahlung' => 'Auszahlung an die Verkäuferin',
    'bestellung_treuhand' => 'Dein Geld liegt hinterlegt. Es geht erst weiter, wenn alles geklappt hat.',
    'bestellung_widerruf' => 'Diese Ware wurde nach deinen Angaben angefertigt. '
        . 'Ein Widerrufsrecht besteht deshalb nach § 312g Abs. 2 Nr. 1 BGB nicht.',
    'bestellung_unbekannt_titel' => 'Diese Bestellung gibt es nicht',
    'bestellung_unbekannt_text' => 'Der Weg führt ins Leere.',
    'bestellung_kein_zugang_titel' => 'Nicht für dich',
    'bestellung_kein_zugang_text' => 'Eine Bestellung sehen nur die beiden Beteiligten.',
    'bestellung_erfolg' => 'Deine Bestellung ist angelegt und der Betrag ist hinterlegt.',

    // --- Fehler beim Bestellen ---------------------------------------------
    // 'guthaben' ist KEIN Fehler, sondern der erwartete Zustand: Guthaben ist
    // aufsichtsrechtlich gesperrt (§ 1 Abs. 1 S. 2 Nr. 1 KWG). Der Text sagt
    // das ehrlich, statt einen Aufladeknopf zu versprechen, den es nicht gibt.
    'bestellfehler.guthaben' => 'Bestellen ist noch nicht möglich: Es gibt keinen Weg, Guthaben aufzuladen.',
    'bestellfehler.guthaben_erklaerung' => 'Ein aufladbares Guthaben kann ein erlaubnispflichtiges '
        . 'Einlagengeschäft nach § 1 Abs. 1 S. 2 Nr. 1 KWG sein. Diese Frage ist offen, deshalb ist '
        . 'die Aufladung gesperrt und kein Zahlungsdienstleister angebunden. Das ist Absicht und '
        . 'kein Ausfall — bis zur Klärung lässt sich hier nichts kaufen.',
    'bestellfehler.selbstkauf' => 'Du kannst nicht bei dir selbst bestellen.',
    'bestellfehler.verbundene_konten' => 'Diese Bestellung wurde zur Prüfung angehalten. Wir melden uns.',
    'bestellfehler.spezifikation' => 'Ohne mindestens eine Spezifikation lässt sich nichts anfertigen. '
        . 'Bitte triff im Konfigurator eine Angabe.',
    'bestellfehler.pflichtoption' => 'Bitte fülle alle Pflichtangaben aus.',
    'bestellfehler.widerruf' => 'Bitte bestätige, dass du den Ausschluss des Widerrufsrechts zur '
        . 'Kenntnis genommen hast.',
    'bestellfehler.lieferart' => 'Diese Lieferart bietet das Angebot nicht an.',
    'bestellfehler.land' => 'Für dein Land ist der Marktplatz noch nicht geöffnet.',
    'bestellfehler.waehrung' => 'Dieses Angebot ist in einer Währung ausgezeichnet, '
        . 'die noch nicht abgerechnet werden kann.',
    'bestellfehler.angebot' => 'Dieses Angebot ist gerade nicht bestellbar.',
    'bestellfehler.allgemein' => 'Die Bestellung konnte nicht angelegt werden. Bitte versuche es erneut.',

    // --- Fehler aus MeinSlip\Domain\Catalog\AngebotFehler ------------------
    'angebotsfehler.keine_verkaufsfaehigkeit' => 'Für dein Konto ist Verkaufen nicht freigeschaltet.',
    'angebotsfehler.titel_fehlt' => 'Ohne Titel geht es nicht.',
    'angebotsfehler.titel_zu_lang' => 'Der Titel ist zu lang. Höchstens 190 Zeichen.',
    'angebotsfehler.grundpreis_ungueltig' => 'Der Grundpreis muss größer als null sein.',
    'angebotsfehler.kategorie_unbekannt' => 'Diese Kategorie gibt es nicht.',
    'angebotsfehler.bearbeitungstage_ungueltig' => 'Die Anfertigungszeit muss zwischen 1 und 90 Tagen liegen.',
    'angebotsfehler.uebergabe_region_zu_lang' => 'Die Region ist zu lang. Höchstens 40 Zeichen.',
    'angebotsfehler.waehrung_ungueltig' => 'Diese Währung gibt es nicht.',
    'angebotsfehler.angebot_unbekannt' => 'Dieses Angebot gibt es nicht.',
    'angebotsfehler.nicht_der_eigentuemer' => 'Dieses Angebot gehört dir nicht.',
    'angebotsfehler.nicht_bearbeitbar' => 'In diesem Status sind keine Änderungen möglich.',
    'angebotsfehler.feld_unbekannt' => 'Ein Feld war nicht erlaubt. Bitte lade die Seite neu.',
    'angebotsfehler.option_schluessel_ungueltig' => 'Der Schlüssel darf nur Kleinbuchstaben, Ziffern '
        . 'und Unterstriche enthalten und braucht 2 bis 80 Zeichen.',
    'angebotsfehler.option_bezeichnung_ungueltig' => 'Die Bezeichnung fehlt oder ist zu lang.',
    'angebotsfehler.option_art_unbekannt' => 'Diese Art der Eingabe gibt es nicht.',
    'angebotsfehler.option_aufpreis_ungueltig' => 'Der Aufpreis darf nicht negativ sein.',
    'angebotsfehler.option_unbekannt' => 'Diese Option gibt es nicht.',
    'angebotsfehler.keine_spezifikation' => 'Das Angebot braucht mindestens eine Option, '
        . 'die als Spezifikation markiert ist. Ohne sie trägt der Widerrufsausschluss nicht.',
    'angebotsfehler.ablehnungsgrund_fehlt' => 'Eine Ablehnung braucht eine Begründung.',
    'angebotsfehler.eigenpruefung_unzulaessig' => 'Niemand prüft das eigene Angebot.',
    'angebotsfehler.statuswechsel_unzulaessig' => 'Dieser Schritt ist von hier aus nicht möglich.',
    'angebotsfehler.status_unbekannt' => 'Das Angebot steht in einem unbekannten Status.',
    'angebotsfehler.preis_unlesbar' => 'Der Betrag ist nicht lesbar. Schreibe ihn wie 24,90.',
    'angebotsfehler.allgemein' => 'Das hat nicht geklappt. Bitte versuche es erneut.',

    // --- Rueckmeldungen nach erfolgreichem Speichern -----------------------
    'erfolg.angelegt' => 'Der Entwurf ist angelegt.',
    'erfolg.gespeichert' => 'Gespeichert.',
    'erfolg.option_gespeichert' => 'Die Option ist gespeichert.',
    'erfolg.option_entfernt' => 'Die Option ist entfernt.',
    'erfolg.eingereicht' => 'Das Angebot liegt zur Prüfung vor.',
    'erfolg.pausiert' => 'Das Angebot ist pausiert.',
    'erfolg.fortgesetzt' => 'Das Angebot ist wieder im Katalog.',
    'erfolg.entfernt' => 'Das Angebot ist zurückgezogen.',
];
