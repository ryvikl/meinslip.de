<?php

declare(strict_types=1);

return [
    // Zustaende des Bestellautomaten. Die Schluessel entsprechen den Werten
    // in MeinSlip\Domain\Order\Bestellzustand.
    'zustand.entwurf' => 'Entwurf',
    'zustand.zahlung_offen' => 'Zahlung offen',
    'zustand.treuhand_gebunden' => 'Betrag hinterlegt',
    'zustand.angenommen' => 'Angenommen',
    'zustand.in_vorbereitung' => 'In Vorbereitung',
    'zustand.versendet' => 'Versendet',
    'zustand.uebergabe_geplant' => 'Übergabe geplant',
    'zustand.uebergeben' => 'Übergeben',
    'zustand.zugestellt' => 'Zugestellt',
    'zustand.einspruchsfenster' => 'Prüfzeit läuft',
    'zustand.streitfall' => 'In Klärung',
    'zustand.freigegeben' => 'Abgeschlossen',
    'zustand.erstattet' => 'Erstattet',
    'zustand.abgebrochen' => 'Abgebrochen',

    // Erlaeuterungen, die im Produkt neben dem Zustand stehen. Sie erklaeren
    // die Treuhandmechanik in einfacher Sprache — das ist der eigentliche
    // Verkaufsgrund und darf nicht im Kleingedruckten verschwinden.
    'erklaerung.treuhand_gebunden' => 'Dein Geld liegt sicher hinterlegt. Es geht erst weiter, wenn alles geklappt hat.',
    'erklaerung.einspruchsfenster' => 'Du hast jetzt :stunden Stunden Zeit zu prüfen. Erst danach wird ausgezahlt.',
    'erklaerung.streitfall' => 'Wir prüfen den Fall. Das Geld bleibt so lange hinterlegt.',
    'erklaerung.freigegeben' => 'Abgeschlossen. Der Betrag wurde ausgezahlt.',
    'erklaerung.erstattet' => 'Der volle Betrag ist zurück auf deinem Guthaben.',

    // Konfigurator
    'konfigurator_titel' => 'Nach deinen Wünschen',
    'konfigurator_hinweis' => 'Jede Bestellung wird eigens für dich angefertigt. Deshalb ist sie vom Widerruf ausgenommen.',
    'konfigurator_pflichtfeld' => 'Bitte triff hier eine Auswahl.',
    'konfigurator_ohne_spezifikation' => 'Bitte wähle mindestens eine Option aus, damit wir dein Produkt individuell anfertigen können.',

    // Lieferarten
    'lieferart.versand' => 'Anonymer Versand',
    'lieferart.versand_text' => 'Wir erzeugen das Versandetikett. Weder du noch die Verkäuferin seht die Adresse der anderen Seite.',
    'lieferart.uebergabe' => 'Persönliche Übergabe',
    'lieferart.uebergabe_text' => 'Betrag hinterlegt, Übergabe bestätigt, danach läuft die Prüfzeit. Kein Geld fließt vorher.',

    // Aktionen
    'aktion_bestellen' => 'Sicher bestellen',
    'aktion_annehmen' => 'Auftrag annehmen',
    'aktion_ablehnen' => 'Ablehnen',
    'aktion_versand_vorbereiten' => 'Versand vorbereiten',
    'aktion_uebergabe_planen' => 'Übergabe planen',
    'aktion_uebergabe_bestaetigen' => 'Übergabe bestätigen',
    'aktion_problem_melden' => 'Problem melden',
    'aktion_bewerten' => 'Bewerten',

    // Fehlermeldungen in Nutzersprache
    'fehler.guthaben' => 'Dein Guthaben reicht nicht aus. Lade bitte auf.',
    'fehler.selbstkauf' => 'Du kannst nicht bei dir selbst bestellen.',
    'fehler.verbundene_konten' => 'Diese Bestellung wurde zur Prüfung angehalten. Wir melden uns.',
    'fehler.zustand' => 'Dieser Schritt ist gerade nicht möglich.',

    // Zusammenfassung
    'summe_gesamt' => 'Gesamtbetrag',
    'summe_enthaltene_ust' => 'davon :satz Umsatzsteuer',
    'summe_hinweis_treuhand' => 'Der Betrag wird hinterlegt und erst nach deiner Prüfzeit ausgezahlt.',
];
