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
    'begruendung_hinweis' => 'Pflichtangabe. Art. 17 DSA verlangt eine Begründung für jede '
        . 'Beschränkung; ohne Protokoll lässt sich später nicht belegen, wer was entschieden hat.',

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
    'angebote_kicker' => 'Warten auf Entscheidung',
    'angebote_titel' => 'Angebote in Prüfung',
    'angebote_leer' => 'Zurzeit wartet kein Angebot auf eine Entscheidung.',
    'angebot_freigeben' => 'Freigeben',
    'angebot_ablehnen' => 'Ablehnen',
    'angebot_grund' => 'Grund der Ablehnung',
    'angebot_grund_hinweis' => 'Wird der einreichenden Person gezeigt. Ohne Grund kann sie nur raten, '
        . 'was zu ändern ist.',
    'angebot_vier_augen' => 'Wer ein Angebot selbst eingereicht hat, darf nicht darüber entscheiden.',
    'angebot_abgelehnt_ziel' => 'Eine Ablehnung stellt das Angebot zurück auf Entwurf, sie löscht es nicht.',

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
    'spalte.zeit' => 'Zeitpunkt',
    'spalte.verwalter' => 'Entschieden von',
    'spalte.handlung' => 'Handlung',
    'spalte.begruendung' => 'Begründung',
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
    'angebot_status.entfernt' => 'entfernt',

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

    'gegenstand.benutzer' => 'Konto',
    'gegenstand.angebot' => 'Angebot',
    'gegenstand.bestellung' => 'Bestellung',
    'gegenstand.meldung' => 'Meldung',
    'gegenstand.faehigkeit_kaufen' => 'Fähigkeit kaufen',
    'gegenstand.faehigkeit_verkaufen' => 'Fähigkeit verkaufen',
    'gegenstand.faehigkeit_verwalten' => 'Fähigkeit verwalten',

    // --- Rückmeldungen ---------------------------------------------------
    'erfolg.faehigkeit_freigeschaltet' => 'Die Fähigkeit ist freigeschaltet und protokolliert.',
    'erfolg.faehigkeit_entzogen' => 'Die Fähigkeit ist entzogen und protokolliert.',
    'erfolg.konto_gesperrt' => 'Das Konto ist gesperrt und protokolliert.',
    'erfolg.konto_entsperrt' => 'Die Sperre ist aufgehoben und protokolliert.',
    'erfolg.angebot_freigegeben' => 'Das Angebot ist freigegeben und protokolliert.',
    'erfolg.angebot_abgelehnt' => 'Das Angebot ist abgelehnt und protokolliert.',
    'erfolg.meldung_bearbeitet' => 'Die Meldung ist entschieden und protokolliert.',

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
    'fehler.eigenpruefung_unzulaessig' => 'Wer ein Angebot eingereicht hat, darf nicht darüber entscheiden.',
    'fehler.statuswechsel_unzulaessig' => 'Aus diesem Status ist der Schritt nicht erlaubt — '
        . 'vermutlich hat jemand anderes das Angebot bereits entschieden.',
    'fehler.status_unbekannt' => 'Diesen Status gibt es nicht.',

    'fehler.unbekannt' => 'Das hat nicht funktioniert.',
];
