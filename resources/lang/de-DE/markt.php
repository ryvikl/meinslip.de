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
 * SEIT DEM MODELLWECHSEL GIBT ES KEINE VORABPRUEFUNG MEHR. Wer registriert
 * ist, darf anbieten; wer veroeffentlicht, steht sofort im Katalog. Kein Text
 * in dieser Datei darf mehr eine Pruefung, eine Freischaltung oder ein Warten
 * versprechen — das waere eine Zusage, die die Software nicht mehr einloest,
 * und im Fall von 'verkaufen_gesperrt_*' sogar eine Sanktion als offene
 * Freischaltung getarnt. Das Gegengewicht zur sofortigen Sichtbarkeit ist die
 * Nachmoderation auf Meldung hin (Art. 16 DSA), und die braucht ihrerseits
 * ehrliche Texte: 'melden_*' fuer den Weg hin, 'gesperrt_*' fuer die Folge.
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
    'angebot_nicht_aktiv_text' => 'Dieses Angebot steht im Moment nicht im Katalog. '
        . 'Es ist entweder ein Entwurf, pausiert, gesperrt oder zurückgezogen.',
    'angebot_beschreibung' => 'Beschreibung',

    // --- Kontakt: die Hauptaktion der Angebotsseite ------------------------
    // Der Bestellvorgang ist verriegelt, solange die Frage nach § 1 Abs. 1
    // S. 2 Nr. 1 KWG offen ist. Diese Texte sind deshalb nicht der
    // Ersatzbildschirm für eine kaputte Kasse, sondern der Normalfall: Auf
    // einem Kleinanzeigenmarkt schreibt man sich, bevor etwas den Besitzer
    // wechselt. Ist der Bestellvorgang offen und das Angebot bestellbar,
    // rutscht dieser Knopf auf den zweiten Platz — der Text wechselt mit.
    'kontakt_kicker' => 'Kontakt',
    'kontakt_titel' => 'Frag nach, bevor du dich entscheidest',
    'kontakt_hauptweg' => 'Schreib der Anbieterin eine Nachricht: zu Größe, Zustand, Tragedauer, '
        . 'zur Übergabe oder zum Preis. Alles Weitere klärt ihr direkt miteinander.',
    'kontakt_neben_bestellung' => 'Du kannst direkt bestellen — oder erst nachfragen, '
        . 'wenn dir etwas unklar ist.',
    'nachricht_schreiben' => 'Nachricht schreiben',
    'nachricht_anmeldung' => 'Zum Schreiben brauchst du ein Konto. '
        . 'Registrieren dauert eine Minute und schaltet auch das Anbieten frei.',

    // --- Meldeweg nach Art. 16 DSA ----------------------------------------
    // Ausdrücklich ohne Anmeldeschranke. Art. 16 Abs. 1 DSA spricht von
    // „Personen oder Einrichtungen“ und kennt keine Kontopflicht; eine solche
    // Hürde wäre der einfachste Weg, das Verfahren wirkungslos zu machen.
    // Der Satz nennt deshalb ausdrücklich, dass kein Konto nötig ist.
    'melden_kicker' => 'Etwas stimmt nicht?',
    'melden_text' => 'Melde dieses Angebot, wenn es gegen Gesetze oder gegen unsere Regeln verstößt. '
        . 'Jede Meldung wird von einem Menschen angesehen, und du bekommst eine Antwort. '
        . 'Ein Konto brauchst du dafür nicht.',
    'melden_knopf' => 'Angebot melden',

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
        . 'ohne die keine Anfertigung nach Kundenwunsch möglich ist. '
        . 'Schreib der Anbieterin — ansprechbar ist sie trotzdem.',
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

    // DIESE BEIDEN TEXTE HABEN IHRE BEDEUTUNG GEWECHSELT, NICHT NUR IHREN
    // WORTLAUT. Bis zum Modellwechsel war „verkaufen“ eine Fähigkeit, auf die
    // man wartete — die Verwaltung schaltete sie frei. Seither vergibt
    // Konten::registrieren() sie mit der Grundlage „registrierung“, und jedes
    // Bestandskonto hat sie über 010_marktmodell.php nachgetragen bekommen.
    // Wer sie NICHT hat, dem wurde sie entzogen: Verwaltung::faehigkeitEntziehen()
    // ist eine begründete, protokollierte und nach Art. 17 DSA zugestellte
    // Maßnahme — das mildere Mittel neben der Kontosperre. „Lass dich
    // verifizieren“ wäre hier nicht bloß veraltet, sondern falsch: Es schickte
    // eine sanktionierte Person in ein Verfahren, das ihr nichts zurückgibt,
    // und verschwiege ihr den Widerspruchsweg nach Art. 20 DSA.
    'verkaufen_gesperrt_titel' => 'Dir wurde das Verkaufen untersagt',
    'verkaufen_gesperrt_text' => 'Anbieten ist für dein Konto gesperrt. Das ist eine Maßnahme '
        . 'unserer Moderation und keine offene Freischaltung — sie hat eine Begründung, '
        . 'und die steht in deinem Profil. Dort findest du auch den Weg, ihr zu widersprechen.',
    'verkaufen_gesperrt_zum_profil' => 'Begründung im Profil ansehen',

    // --- Meine Angebote ----------------------------------------------------
    'verkaufen_kicker' => 'Verkaufen',
    'verkaufen_titel' => 'Meine Angebote',
    'verkaufen_unterzeile' => 'Jedes Angebot beginnt als Entwurf. Sobald du es veröffentlichst, '
        . 'steht es sofort im Katalog — und du kannst es jederzeit wieder pausieren.',
    'verkaufen_leer_titel' => 'Noch kein Angebot',
    'verkaufen_leer_text' => 'Leg dein erstes Angebot an. Du kannst es in Ruhe als Entwurf vorbereiten '
        . 'und veröffentlichen, wenn es fertig ist.',
    'verkaufen_neu' => 'Neues Angebot',
    'verkaufen_bearbeiten' => 'Bearbeiten',
    'verkaufen_ansehen' => 'Im Katalog ansehen',
    'spalte_titel' => 'Titel',
    'spalte_status' => 'Status',
    'spalte_preis' => 'Grundpreis',
    'spalte_angelegt' => 'Angelegt',
    'spalte_aktion' => 'Aktion',

    'status.entwurf' => 'Entwurf',
    // Bleibt, obwohl niemand mehr dorthin geleitet wird: Der Altpfad
    // /verkaufen/{id}/einreichen besteht fort, und ohne diesen Schlüssel
    // stünde in der Statusspalte '[[markt.status.in_pruefung]]'.
    'status.in_pruefung' => 'In Prüfung',
    'status.aktiv' => 'Im Katalog',
    'status.pausiert' => 'Pausiert',
    'status.gesperrt' => 'Gesperrt',
    'status.entfernt' => 'Zurückgezogen',

    // --- Status „gesperrt“: die Nachmoderation erklären --------------------
    // Art. 17 DSA verlangt eine Begründung der Maßnahme, und sie wird
    // zugestellt — sie landet im Profil, nicht hier. Diese Texte sagen
    // deshalb, DASS gesperrt wurde und WO die Begründung liegt. Zwei
    // Fundstellen für denselben Text wären zwei, die auseinanderlaufen können.
    'gesperrt_titel' => 'Dieses Angebot ist gesperrt',
    'gesperrt_text' => 'Nach einer Meldung hat unsere Moderation dieses Angebot aus dem Katalog '
        . 'genommen. Es lässt sich weder bearbeiten noch erneut veröffentlichen.',
    'gesperrt_begruendung' => 'Warum das geschehen ist, steht in der Zustellung in deinem Profil. '
        . 'Dort findest du auch den Weg, der Entscheidung zu widersprechen — '
        . 'ein Widerspruch kann die Sperre aufheben.',
    'gesperrt_zum_profil' => 'Zur Begründung im Profil',
    'gesperrt_liste_text' => 'Mindestens eines deiner Angebote wurde nach einer Meldung gesperrt. '
        . 'Die Begründung liegt als Zustellung in deinem Profil, samt Weg zum Widerspruch.',

    // --- Angebot anlegen ---------------------------------------------------
    'anlegen_kicker' => 'Neues Angebot',
    'anlegen_titel' => 'Angebot anlegen',
    'anlegen_unterzeile' => 'Das Angebot entsteht als Entwurf. Sichtbar wird es in dem Moment, '
        . 'in dem du es veröffentlichst.',
    'anlegen_absenden' => 'Entwurf anlegen',
    'anlegen_naechster_schritt' => 'Im nächsten Schritt legst du die Optionen des Konfigurators fest. '
        . 'Für die Sichtbarkeit brauchst du sie nicht — veröffentlichen kannst du sofort. '
        . 'Bestellbar wird das Angebot aber erst mit einer Spezifikation, in die die Käuferin '
        . 'etwas einträgt.',

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
    // Eigener Satz für die Sperre: Der Rat „pausiere das Angebot“ liefe ins
    // Leere, weil Angebote::UEBERGAENGE aus „gesperrt“ nur nach „aktiv“
    // (Widerspruch) und „entfernt“ führt.
    'bearbeiten_gesperrt_sperre' => 'Ein gesperrtes Angebot lässt sich nicht bearbeiten. '
        . 'Die Sperre hebt nur ein erfolgreicher Widerspruch auf.',

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

    // DER ABGEBAUTE VORABPRÜFUNG IN ZWEI TEXTEN. Vorher stand hier „Zur
    // Prüfung einreichen“ und „Nach dem Einreichen sieht ein Mensch das
    // Angebot an“ — beides trifft nicht mehr zu. Der Hinweis nennt jetzt
    // ausdrücklich das Gegenstück zur sofortigen Sichtbarkeit: die jederzeit
    // mögliche Pause. Ohne diesen Halbsatz klänge „sofort sichtbar“ wie eine
    // Einbahnstraße, und genau diese Sorge hält Menschen vom Veröffentlichen ab.
    'veroeffentlichen' => 'Jetzt veröffentlichen',
    'veroeffentlichen_hinweis' => 'Dein Angebot ist sofort im Katalog sichtbar — es wartet auf '
        . 'niemanden. Du kannst es jederzeit pausieren und weiter bearbeiten. '
        . 'Verstößt ein Angebot gegen Gesetze oder unsere Regeln, greifen wir auf Meldung hin ein.',
    // Bleibt für den Altpfad /verkaufen/{id}/einreichen, der weiterhin
    // erreichbar ist und diese Rückmeldung setzt (siehe 'erfolg.eingereicht').
    'einreichen' => 'Zur Prüfung einreichen',

    // Sichtbar und bestellbar sind zwei Fragen. Der Satz nennt die fehlende
    // ART der Option, nicht bloß „eine Spezifikation“: Wer ein Ankreuzfeld als
    // Spezifikation gesetzt hat, legte sonst ein zweites an und käme keinen
    // Schritt weiter. Rechtsgrund ist § 312g Abs. 2 Nr. 1 BGB in der Auslegung
    // des EuGH (C-529/19) — nur ein von der Käuferin geschriebener Wert macht
    // die Ware zur Anfertigung nach ihren Angaben.
    'nicht_bestellbar_titel' => 'Sichtbar, aber noch nicht bestellbar',
    'nicht_bestellbar_text' => 'Zum Bestellen braucht dein Angebot mindestens eine Option, '
        . 'die als Spezifikation markiert ist UND von der Art „Zahl“ oder „Freier Text“. '
        . 'Ein Ankreuzfeld genügt nicht: Es trägt keine Angabe der Käuferin und damit nicht '
        . 'den Ausschluss des Widerrufsrechts. Im Katalog steht dein Angebot trotzdem, '
        . 'und anschreiben kann man dich auch.',

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
    // Kein Ausfall, sondern der verriegelte Zustand: Solange die Frage nach
    // § 1 Abs. 1 S. 2 Nr. 1 KWG offen ist, steht der Schalter
    // BESTELLVORGANG_AKTIV auf aus und Bestellungen::anlegen() wirft. Diesen
    // Bildschirm sieht nur, wer die Anfrage von Hand baut — das Formular wird
    // in diesem Zustand gar nicht erst gerendert.
    'bestellfehler.bestellvorgang_gesperrt' => 'Bestellen ist zurzeit nicht möglich. '
        . 'Schreib der Anbieterin stattdessen eine Nachricht — alles Weitere klärt ihr direkt.',
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
    // Dieser Schlüssel existierte in Angebote.php seit jeher, hatte aber
    // weder einen Text noch einen Platz in der Weißliste von MarktRouten —
    // der Bildschirm blieb nach dem Fehlversuch stumm. Der Satz nennt die
    // fehlende ART, nicht bloß „eine Spezifikation“: Wer bereits ein
    // Ankreuzfeld als Spezifikation gesetzt hat, legte sonst ein zweites an
    // und käme keinen Schritt weiter.
    'angebotsfehler.spezifikation_braucht_eingabe' => 'Als Spezifikation zählt nur eine Option '
        . 'der Art „Zahl“ oder „Freier Text“ — etwas, das die Käuferin selbst einträgt. '
        . 'Ein Ankreuzfeld individualisiert die Ware nicht.',
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
    'erfolg.veroeffentlicht' => 'Dein Angebot steht ab sofort im Katalog.',
    // Bleibt für den Altpfad /verkaufen/{id}/einreichen.
    'erfolg.eingereicht' => 'Das Angebot liegt zur Prüfung vor.',
    'erfolg.pausiert' => 'Das Angebot ist pausiert.',
    'erfolg.fortgesetzt' => 'Das Angebot ist wieder im Katalog.',
    'erfolg.entfernt' => 'Das Angebot ist zurückgezogen.',
];
