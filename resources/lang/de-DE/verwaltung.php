<?php

declare(strict_types=1);

/**
 * Verwaltungsbereich (Backend).
 *
 * Der Ton ist hier ein anderer als vorne: knapp, sachlich, ohne Werbung. Wer
 * hier arbeitet, entscheidet über fremde Konten und muss in einer Zeile
 * erfassen, worum es geht.
 *
 * Die Schlüssel unter 'fehler.' entsprechen wörtlich VerwaltungsFehler::schluessel()
 * und AngebotFehler::schluessel(). Kommt dort ein Schlüssel hinzu, fehlt hier
 * sonst der Text und die Oberfläche zeigt '[[verwaltung.fehler.x]]'.
 */
return [
    // --- Rahmen ----------------------------------------------------------
    'bereich' => 'Verwaltung',
    'nav_bereiche' => 'Bereiche der Verwaltung',
    'nav.uebersicht' => 'Übersicht',
    'nav.konten' => 'Konten',
    'nav.angebote' => 'Angebote',
    'nav.meldungen' => 'Meldungen',
    'nav.verifizierung' => 'Prüfbelege',
    'nav.protokoll' => 'Protokoll',
    'nav.hauptbuch' => 'Hauptbuch',
    'nav.website' => 'Zur Website',

    'blaettern_zurueck' => 'Zurück',
    'blaettern_weiter' => 'Weiter',
    // Der zweite Platzhalter heisst bewusst nicht ':seiten': Lang::t ersetzt der
    // Reihe nach per str_replace, und ':seite' ist ein Anfang von ':seiten' —
    // aus "von :seiten" würde sonst "von 2n".
    'blaettern_seite' => 'Seite :seite von :gesamt',
    'anzahl_gesamt' => ':anzahl Einträge',
    'ja' => 'Ja',
    'nein' => 'Nein',
    'nie' => 'nie',
    'ohne' => 'ohne',

    // --- Übersicht -------------------------------------------------------
    'uebersicht_kicker' => 'Stand der Plattform',
    'uebersicht_titel' => 'Übersicht',

    // Die Hauptbuchabweichung steht bewusst vor allen anderen Zahlen: Stimmt
    // sie nicht, ist Geld entstanden oder verschwunden — dann ist alles
    // andere zweitrangig.
    'abweichung_titel' => 'Das Hauptbuch stimmt nicht',
    'abweichung_betrag' => 'Abweichung: :betrag',
    'abweichung_text' => 'Die Summe aller Buchungen ist nicht null. Es ist Geld entstanden '
        . 'oder verschwunden. Korrigiert wird ausschließlich mit einer Gegenbuchung, '
        . 'niemals durch Ändern einer bestehenden Zeile.',
    'abweichung_vorgaenge' => 'Betroffene Vorgänge: :anzahl',
    'abweichung_pruefen' => 'Vorgänge ansehen',
    'hauptbuch_in_ordnung' => 'Hauptbuch ausgeglichen.',
    'hauptbuch_in_ordnung_text' => 'Die Summe aller Buchungen ist null.',

    'kennzahl_konten_gesamt' => 'Konten',
    'kennzahl_konten_neu' => 'Neu in sieben Tagen',
    'kennzahl_konten_gesperrt' => 'Gesperrte Konten',
    'kennzahl_meldungen_offen' => 'Offene Meldungen',
    'kennzahl_meldungen_frist' => 'Meldungen über der Frist',
    'kennzahl_pruefungen_offen' => 'Offene Prüfungen',
    // Die einzige Kennzahl mit einer Verfallsfrist: Was hier stehen bleibt,
    // wird gelöscht, nicht aufgeschoben.
    'kennzahl_belege_offen' => 'Prüfbelege mit Frist',

    'uebersicht_angebote' => 'Angebote nach Status',
    'uebersicht_bestellungen' => 'Bestellungen nach Zustand',

    // --- Konten ----------------------------------------------------------
    'konten_kicker' => 'Personen und Rechte',
    'konten_titel' => 'Konten',
    'konten_suche' => 'Pseudonym oder E-Mail-Adresse',
    'konten_suche_absenden' => 'Suchen',
    'konten_leer' => 'Kein Konto gefunden.',
    'konto_ansehen' => 'Ansehen',
    'ohne_faehigkeit' => 'keine',
    // Name des waagerecht scrollbaren Bereichs um die Kontentabelle. Eine
    // role="region" ohne Namen steht namenlos in der Landmarkenliste und ist
    // damit wertlos.
    'tabelle_konten' => 'Kontenliste, waagerecht scrollbar',

    'konto_kicker' => 'Einzelnes Konto',
    'konto_guthaben' => 'Guthaben',
    'konto_einnahmen' => 'Einnahmen',
    'konto_angelegt' => 'Angelegt',
    'konto_zuletzt_aktiv' => 'Zuletzt aktiv',
    'konto_kennung' => 'Kennung',

    'faehigkeiten_titel' => 'Fähigkeiten',
    'faehigkeit_waehlen' => 'Fähigkeit',
    'faehigkeit_freischalten' => 'Freischalten',
    'faehigkeit_entziehen' => 'Entziehen',
    'faehigkeit_verwalten_hinweis' => 'Die Fähigkeit „verwalten“ wird ausschließlich über '
        . 'bin/verwalter vergeben. Entziehen lässt sie sich hier — damit ein übernommenes '
        . 'Konto sofort entrechtet werden kann.',

    'begruendung' => 'Begründung',
    // Der Satz stand hier vorher als „Art. 17 DSA verlangt eine Begründung …
    // ohne Protokoll lässt sich später nicht belegen, wer was entschieden hat“
    // und beschrieb damit den internen Nachweis als Erfüllung der Vorschrift.
    // Art. 17 Abs. 1 DSA verlangt aber, dass die Begründung die betroffene
    // Person ERREICHT. Der Hinweis sagt das jetzt und leitet die Verwalterin
    // an, den Text an sie zu richten — sie bekommt ihn zu lesen.
    'begruendung_hinweis' => 'Pflichtangabe. Art. 17 Abs. 1 DSA verlangt eine klare und '
        . 'spezifische Begründung gegenüber der betroffenen Person, nicht nur einen Vermerk '
        . 'im Protokoll. Schreibe den Text deshalb an sie gerichtet: was ab wann gilt und '
        . 'worauf sich die Entscheidung stützt.',

    'sperre_titel' => 'Zugang',
    'konto_sperren' => 'Konto sperren',
    'konto_entsperren' => 'Sperre aufheben',
    'sperre_wirkung' => 'Eine Sperre beendet laufende Sitzungen von selbst: Angemeldet bleibt '
        . 'nur, wessen Konto aktiv ist.',
    'eigenes_konto' => 'Über das eigene Konto entscheidet jemand anderes.',

    'pruefungen_titel' => 'Prüfungen',
    'pruefungen_leer' => 'Keine Prüfung vorhanden.',
    'bestellungen_titel' => 'Letzte Bestellungen',
    'bestellungen_leer' => 'Keine Bestellung vorhanden.',
    'meldungen_gegen_titel' => 'Offene Meldungen gegen dieses Konto',
    'meldungen_gegen_leer' => 'Keine offene Meldung.',

    // --- Angebote --------------------------------------------------------
    // Die Seite ist keine Prüfliste mehr. Es gibt keine Vorabprüfung: Ein
    // Angebot ist sofort sichtbar, die Verwaltung greift auf Meldung hin ein.
    // Die Texte sagen das, damit niemand hier auf Eingänge wartet, die nie
    // kommen.
    'angebote_kicker' => 'Nachmoderation auf Meldung',
    'angebote_titel' => 'Gemeldete Angebote',
    'angebote_erklaerung' => 'Angebote sind ohne Vorabprüfung sichtbar. Hier steht, was '
        . 'gemeldet wurde — die knappste zugesagte Frist zuerst.',
    'gemeldet_leer' => 'Zurzeit ist kein Angebot gemeldet.',
    'meldung_eine' => 'Eine Meldung',
    'meldungen_anzahl' => ':anzahl Meldungen',
    'alle_meldegruende' => 'Alle Gründe',
    // Zwei Platzhalter, von denen keiner Anfang des anderen ist: ':grund' und
    // ':anzahl' wären ungefährlich, ':grund' und ':gruende' nicht — Lang::t
    // ersetzt der Reihe nach per str_replace.
    'meldegrund_zaehler' => ':grund (:wieoft)',
    'angebot_ansehen' => 'Angebot ansehen',
    'angebot_nicht_oeffentlich' => 'Öffentlich nicht abrufbar: Im Katalog steht nur, was aktiv ist.',
    'angebot_ist_entfernt' => 'Dieses Angebot ist entfernt. Aus diesem Zustand führt kein Weg zurück.',

    'angebot_sperren' => 'Angebot sperren',
    'angebot_sperrgrund' => 'Begründung der Sperre',
    'angebot_sperrgrund_hinweis' => 'Pflichtangabe. Die Sperre ist eine Beschränkung, die '
        . 'Begründung geht deshalb an die Verkäuferin (Art. 17 Abs. 1 DSA) und ist die '
        . 'Grundlage ihrer Beschwerde. Schreibe den Text an sie gerichtet.',
    'angebot_sperren_ziel' => 'Eine Sperre nimmt das Angebot sofort vom Markt. Sie lässt sich '
        . 'wieder aufheben — Art. 20 DSA verlangt, dass eine Beschwerde etwas ändern kann.',
    'angebot_entsperren' => 'Sperre aufheben',
    'angebot_entsperrgrund' => 'Begründung der Aufhebung',
    'angebot_entsperrgrund_hinweis' => 'Pflichtangabe fürs Protokoll. Eine Aufhebung beschränkt '
        . 'niemanden und wird deshalb nicht zugestellt — festgehalten wird sie trotzdem: Wer '
        . 'eine Sperre aufhebt, muss so feststellbar sein wie wer sie verhängt hat.',

    'angebot_freigeben' => 'Freigeben',
    'angebot_ablehnen' => 'Ablehnen',
    'angebot_grund' => 'Grund der Ablehnung',
    'angebot_grund_hinweis' => 'Wird der einreichenden Person zugestellt (Art. 17 Abs. 1 DSA). '
        . 'Ohne Grund kann sie nur raten, was zu ändern ist.',
    'angebot_vier_augen' => 'Wer ein Angebot selbst eingereicht hat, darf nicht darüber entscheiden.',
    'angebot_abgelehnt_ziel' => 'Eine Ablehnung stellt das Angebot zurück auf Entwurf, sie löscht es nicht.',

    'altliste_titel' => 'Angebote aus der Vorabprüfung',
    'altliste_hinweis' => 'Diese Liste läuft aus. Sie enthält nur noch, was ausdrücklich zur '
        . 'Prüfung eingereicht wurde; neue Angebote gehen nicht mehr durch dieses Tor. Ist sie '
        . 'leer, verschwindet sie.',

    // --- Prüfbelege ------------------------------------------------------
    // Der Ton ist hier noch knapper als sonst: Auf dieser Seite liegt das
    // Gesicht einer Nutzerin. Jeder Satz, der nicht bei der Entscheidung
    // hilft, hält jemanden länger davor auf, als nötig ist.
    'belege_kicker' => 'Identität, von Hand geprüft',
    'belege_titel' => 'Prüfbelege',
    'belege_erklaerung' => 'Ein Selfie mit unserem Code und dem Datum auf einem '
        . 'handgeschriebenen Zettel. Älteste zuerst. Diese Liste hat als einzige eine Frist: '
        . 'Nach :tage Tagen ohne Bearbeitung wird der Beleg gelöscht, nach der Entscheidung '
        . 'nach :entscheidungstage Tagen — was zuerst eintritt.',
    'belege_kein_ausweis' => 'Wir nehmen keine Ausweisdokumente entgegen und speichern keine. '
        . 'Wer nach einem Ausweis gefragt wird, wird nicht von uns gefragt.',
    'belege_leer' => 'Zurzeit wartet kein Beleg auf eine Entscheidung.',
    'tabelle_belege' => 'Liste der Prüfbelege, waagerecht scrollbar',
    'beleg_ansehen' => 'Ansehen',

    'beleg_kicker' => 'Einzelner Prüfbeleg',
    'beleg_vergleich' => 'Zu vergleichen sind genau zwei Angaben: der Code auf dem Zettel '
        . 'gegen den Code hier, und das Datum auf dem Zettel gegen den Ausgabezeitpunkt. Nur '
        . 'diese beiden binden das Foto an diesen Vorgang und diesen Zeitraum.',
    'beleg_kein_alter' => 'Geprüft wird die Identität, nicht das Alter. Eine Freigabe sagt: '
        . 'Ein Mensch hat sich mit unserem Code gezeigt. Sie sagt nichts über ein '
        . 'Geburtsdatum, und sie schaltet keine Inhalte frei.',
    'beleg_bild_titel' => 'Der Beleg',
    'beleg_bild_alt' => 'Eingereichtes Foto mit handgeschriebenem Zettel',
    'beleg_ohne_bild' => 'Zu diesem Vorgang liegt noch kein Foto vor.',

    'beleg_entscheiden_titel' => 'Entscheiden',
    'beleg_schon_entschieden' => 'Über diesen Beleg ist bereits entschieden.',
    'beleg_frist_hinweis' => 'Mit der Entscheidung beginnt die kurze Frist: Das Foto ist '
        . ':entscheidungstage Tage später gelöscht. Danach gibt es nur noch das Ergebnis.',
    'beleg_grund_freigabe' => 'Vermerk zur Freigabe',
    'beleg_grund_freigabe_hinweis' => 'Pflichtangabe. Der Text geht an die betroffene Person '
        . 'und ist die einzige Spur, die das Foto überlebt. Halte fest, was du gesehen hast — '
        . 'Code und Datum stimmten, das Gesicht war erkennbar.',
    'beleg_grund_ablehnung' => 'Begründung der Ablehnung',
    'beleg_grund_ablehnung_hinweis' => 'Pflichtangabe. Ohne sie kann die Person nur raten, '
        . 'was am Foto nicht stimmte, und macht denselben Fehler noch einmal. Schreibe den '
        . 'Text an sie gerichtet.',
    'beleg_freigeben' => 'Freigeben',
    'beleg_ablehnen' => 'Ablehnen',
    'beleg_zurueck' => 'Zurück zur Liste',

    'beleg_status.offen' => 'Code vergeben',
    'beleg_status.eingereicht' => 'wartet auf Entscheidung',
    'beleg_status.freigegeben' => 'freigegeben',
    'beleg_status.abgelehnt' => 'abgelehnt',

    // --- Meldungen -------------------------------------------------------
    'meldungen_kicker' => 'Beschwerden nach Art. 16 DSA',
    'meldungen_titel' => 'Meldungen',
    'meldungen_leer' => 'Keine Meldung in dieser Auswahl.',
    'filter_status' => 'Status',
    'filter_alle' => 'Alle',
    'filter_anwenden' => 'Anzeigen',
    'frist_ueberschritten' => 'Frist überschritten',
    'meldung_entscheidung' => 'Entscheidung',
    'meldung_neuer_status' => 'Neuer Status',
    'meldung_uebernehmen' => 'Übernehmen',
    'meldung_entscheidung_hinweis' => 'Pflichtangabe. Sie wird protokolliert und ist die '
        . 'Begründung gegenüber der meldenden Person.',

    // --- Protokoll -------------------------------------------------------
    'protokoll_kicker' => 'Wer hat was entschieden',
    'protokoll_titel' => 'Protokoll',
    'protokoll_leer' => 'Noch keine Verwaltungshandlung protokolliert.',

    // --- Hauptbuch -------------------------------------------------------
    'hauptbuch_kicker' => 'Doppelte Buchführung',
    'hauptbuch_titel' => 'Hauptbuch',
    'hauptbuch_leer' => 'Noch kein Vorgang gebucht.',
    'hauptbuch_nur_lesend' => 'Diese Ansicht liest nur. Eine falsche Buchung wird mit einer '
        . 'Gegenbuchung berichtigt, nie durch Ändern einer bestehenden Zeile.',
    'vorgang_ausgeglichen' => 'ausgeglichen',
    'vorgang_unausgeglichen' => 'ABWEICHUNG',

    // --- Spaltenköpfe ----------------------------------------------------
    'spalte.kennung' => 'Nr.',
    'spalte.pseudonym' => 'Pseudonym',
    'spalte.email' => 'E-Mail',
    'spalte.status' => 'Status',
    'spalte.zustand' => 'Zustand',
    'spalte.anzahl' => 'Anzahl',
    'spalte.faehigkeiten' => 'Fähigkeiten',
    'spalte.guthaben' => 'Guthaben',
    'spalte.angelegt' => 'Angelegt',
    'spalte.zuletzt_aktiv' => 'Zuletzt aktiv',
    'spalte.aktion' => 'Aktion',
    'spalte.titel' => 'Titel',
    'spalte.verkaeufer' => 'Verkäuferin',
    'spalte.kategorie' => 'Kategorie',
    'spalte.preis' => 'Grundpreis',
    'spalte.eingereicht' => 'Eingereicht',
    'spalte.entscheidung' => 'Entscheidung',
    'spalte.art' => 'Art',
    'spalte.anbieter' => 'Anbieter',
    'spalte.geprueft' => 'Geprüft',
    'spalte.gueltig_bis' => 'Gültig bis',
    'spalte.volljaehrig' => 'Volljährig',
    'spalte.nummer' => 'Nummer',
    'spalte.rolle' => 'Rolle',
    'spalte.summe' => 'Summe',
    'spalte.grund' => 'Grund',
    'spalte.melder' => 'Gemeldet von',
    'spalte.gegenstand' => 'Gegenstand',
    'spalte.beschreibung' => 'Beschreibung',
    'spalte.frist' => 'Zugesagt bis',
    'spalte.eingegangen' => 'Eingegangen',
    'spalte.erste_meldung' => 'Erste Meldung',
    'spalte.zeit' => 'Zeitpunkt',
    'spalte.verwalter' => 'Entschieden von',
    'spalte.handlung' => 'Handlung',
    'spalte.begruendung' => 'Begründung',
    'spalte.code' => 'Code',
    'spalte.code_ausgegeben' => 'Code vergeben am',
    'spalte.loeschen_ab' => 'Löschung ab',
    'spalte.vorgang' => 'Vorgang',
    'spalte.bezug' => 'Bezug',
    'spalte.buchungen' => 'Buchungen',
    'spalte.ausgeglichen' => 'Ausgeglichen',

    // --- Werte -----------------------------------------------------------
    'konto_status.aktiv' => 'aktiv',
    'konto_status.gesperrt' => 'gesperrt',

    'faehigkeit.kaufen' => 'kaufen',
    'faehigkeit.verkaufen' => 'verkaufen',
    'faehigkeit.verwalten' => 'verwalten',

    'angebot_status.entwurf' => 'Entwurf',
    'angebot_status.in_pruefung' => 'in Prüfung',
    'angebot_status.aktiv' => 'aktiv',
    'angebot_status.pausiert' => 'pausiert',
    // Der Status der Nachmoderation. Er steht hier auch deshalb, weil die
    // Übersicht jeden in der Datenbank vorgefundenen Wert anzeigt, selbst
    // wenn Verwaltung::ANGEBOTSSTATUS ihn (noch) nicht führt.
    'angebot_status.gesperrt' => 'gesperrt',
    'angebot_status.entfernt' => 'entfernt',

    // Die Meldegründe aus MeinSlip\Domain\Trust\Meldungen::GRUENDE. Eigene
    // Texte statt der öffentlichen: Vorne wird erklärt, hier eingeordnet.
    // Reihenfolge wie in der Konstanten — das Dringendste zuerst.
    'meldegrund.minderjaehrig' => 'Minderjährige Person',
    'meldegrund.gestohlene_identitaet' => 'Gestohlene Identität',
    'meldegrund.verbotene_ware' => 'Verbotene Ware',
    'meldegrund.betrug' => 'Betrug',
    'meldegrund.belaestigung' => 'Belästigung',
    'meldegrund.urheberrecht' => 'Urheberrecht',
    'meldegrund.sonstiges' => 'Sonstiges',

    'bestellzustand.entwurf' => 'Entwurf',
    'bestellzustand.zahlung_offen' => 'Zahlung offen',
    'bestellzustand.treuhand_gebunden' => 'Treuhand gebunden',
    'bestellzustand.angenommen' => 'angenommen',
    'bestellzustand.in_vorbereitung' => 'in Vorbereitung',
    'bestellzustand.versendet' => 'versendet',
    'bestellzustand.uebergabe_geplant' => 'Übergabe geplant',
    'bestellzustand.uebergeben' => 'übergeben',
    'bestellzustand.zugestellt' => 'zugestellt',
    'bestellzustand.einspruchsfenster' => 'Einspruchsfenster',
    'bestellzustand.streitfall' => 'Streitfall',
    'bestellzustand.freigegeben' => 'freigegeben',
    'bestellzustand.erstattet' => 'erstattet',
    'bestellzustand.abgebrochen' => 'abgebrochen',

    'meldung_status.offen' => 'offen',
    'meldung_status.in_pruefung' => 'in Prüfung',
    'meldung_status.erledigt' => 'erledigt',
    'meldung_status.abgelehnt' => 'abgelehnt',

    'pruefung_status.offen' => 'offen',
    'pruefung_status.bestanden' => 'bestanden',
    'pruefung_status.abgelehnt' => 'abgelehnt',
    'pruefung_status.abgelaufen' => 'abgelaufen',

    'rolle.kaeufer' => 'Käuferin',
    'rolle.verkaeufer' => 'Verkäuferin',

    'handlung.faehigkeit_freigeschaltet' => 'Fähigkeit freigeschaltet',
    'handlung.faehigkeit_entzogen' => 'Fähigkeit entzogen',
    'handlung.konto_gesperrt' => 'Konto gesperrt',
    'handlung.konto_entsperrt' => 'Sperre aufgehoben',
    'handlung.meldung_bearbeitet' => 'Meldung bearbeitet',
    'handlung.angebot_freigegeben' => 'Angebot freigegeben',
    'handlung.angebot_abgelehnt' => 'Angebot abgelehnt',
    'handlung.angebot_gesperrt' => 'Angebot gesperrt',
    'handlung.angebot_entsperrt' => 'Angebotssperre aufgehoben',
    // Bewusst ohne das Wort „Alter": Freigegeben wird ein Identitätsbeleg.
    'handlung.beleg_freigegeben' => 'Identitätsbeleg anerkannt',
    'handlung.beleg_abgelehnt' => 'Identitätsbeleg abgelehnt',

    'gegenstand.benutzer' => 'Konto',
    'gegenstand.angebot' => 'Angebot',
    'gegenstand.bestellung' => 'Bestellung',
    'gegenstand.meldung' => 'Meldung',
    'gegenstand.faehigkeit_kaufen' => 'Fähigkeit kaufen',
    'gegenstand.faehigkeit_verkaufen' => 'Fähigkeit verkaufen',
    'gegenstand.faehigkeit_verwalten' => 'Fähigkeit verwalten',
    'gegenstand.pruefbeleg' => 'Prüfbeleg',

    // --- Rückmeldungen ---------------------------------------------------
    'erfolg.faehigkeit_freigeschaltet' => 'Die Fähigkeit ist freigeschaltet und protokolliert.',
    'erfolg.faehigkeit_entzogen' => 'Die Fähigkeit ist entzogen und protokolliert.',
    'erfolg.konto_gesperrt' => 'Das Konto ist gesperrt und protokolliert.',
    'erfolg.konto_entsperrt' => 'Die Sperre ist aufgehoben und protokolliert.',
    'erfolg.angebot_freigegeben' => 'Das Angebot ist freigegeben und protokolliert.',
    'erfolg.angebot_abgelehnt' => 'Das Angebot ist abgelehnt, die Begründung ist der '
        . 'einreichenden Person zugestellt und protokolliert.',
    'erfolg.angebot_gesperrt' => 'Das Angebot ist vom Markt, die Begründung ist der '
        . 'Verkäuferin zugestellt und protokolliert.',
    // Ohne "zugestellt": Die Aufhebung wird ausdrücklich nur protokolliert.
    // Der Text verspricht deshalb nichts, was nicht geschieht.
    'erfolg.angebot_entsperrt' => 'Die Sperre ist aufgehoben, das Angebot ist wieder aktiv '
        . 'und die Aufhebung ist protokolliert.',
    'erfolg.meldung_bearbeitet' => 'Die Meldung ist entschieden und protokolliert.',
    'erfolg.beleg_freigegeben' => 'Der Beleg ist anerkannt, das Ergebnis ist gespeichert und '
        . 'die Entscheidung zugestellt. Das Foto wird nach Frist gelöscht.',
    'erfolg.beleg_abgelehnt' => 'Der Beleg ist abgelehnt, die Begründung ist der betroffenen '
        . 'Person zugestellt und protokolliert. Das Foto wird nach Frist gelöscht.',

    // Schlüssel aus VerwaltungsFehler::schluessel()
    'fehler.begruendung_fehlt' => 'Ohne Begründung wird nichts entschieden.',
    'fehler.entscheidung_fehlt' => 'Ohne Entscheidungstext wird die Meldung nicht geändert.',
    'fehler.handlung_fehlt' => 'Die Handlung fehlt.',
    'fehler.handlung_zu_lang' => 'Die Handlung ist zu lang für das Protokoll.',
    'fehler.gegenstand_art_fehlt' => 'Die Art des Gegenstands fehlt.',
    'fehler.gegenstand_art_zu_lang' => 'Die Art des Gegenstands ist zu lang für das Protokoll.',
    'fehler.konto_unbekannt' => 'Dieses Konto gibt es nicht.',
    'fehler.meldung_unbekannt' => 'Diese Meldung gibt es nicht.',
    'fehler.meldungsstatus_unbekannt' => 'Diesen Meldungsstatus gibt es nicht.',
    'fehler.faehigkeit_unbekannt' => 'Diese Fähigkeit gibt es nicht.',
    'fehler.faehigkeit_nicht_vergebbar' => 'Die Fähigkeit „verwalten“ wird ausschließlich über '
        . 'bin/verwalter vergeben.',
    'fehler.selbstsperre_unzulaessig' => 'Über das eigene Konto entscheidet jemand anderes.',
    'fehler.verwalter_unbekannt' => 'Das entscheidende Konto gibt es nicht.',
    'fehler.kein_verwaltungsrecht' => 'Dem entscheidenden Konto fehlt das Verwaltungsrecht.',
    'fehler.zeitpunkt_ungueltig' => 'Der Zeitpunkt ist nicht lesbar.',

    // Schlüssel aus AngebotFehler::schluessel(), soweit hier erreichbar
    'fehler.angebot_unbekannt' => 'Dieses Angebot gibt es nicht.',
    'fehler.ablehnungsgrund_fehlt' => 'Eine Ablehnung ohne Grund hilft niemandem.',
    'fehler.sperrgrund_fehlt' => 'Eine Sperre ohne Begründung ist nach Art. 17 Abs. 1 DSA '
        . 'nicht zulässig.',
    'fehler.entsperrgrund_fehlt' => 'Auch die Aufhebung einer Sperre braucht einen Grund fürs '
        . 'Protokoll.',
    'fehler.eigenpruefung_unzulaessig' => 'Wer ein Angebot eingereicht hat, darf nicht darüber entscheiden.',
    'fehler.statuswechsel_unzulaessig' => 'Aus diesem Status ist der Schritt nicht erlaubt — '
        . 'vermutlich hat jemand anderes das Angebot bereits entschieden.',
    'fehler.status_unbekannt' => 'Diesen Status gibt es nicht.',

    // Schlüssel aus PruefbelegFehler, soweit hier erreichbar
    'fehler.beleg_unbekannt' => 'Diesen Prüfbeleg gibt es nicht — möglicherweise ist er '
        . 'inzwischen nach Frist gelöscht worden.',
    'fehler.beleg_nicht_offen' => 'Über diesen Prüfbeleg ist bereits entschieden — vermutlich '
        . 'von jemand anderem, während die Seite offen war.',

    'fehler.unbekannt' => 'Das hat nicht funktioniert.',
];
