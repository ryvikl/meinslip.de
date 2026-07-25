# Schmerzpunkte beider Marktseiten

> **Stand:** 25.07.2026 · **Belegstatus: UNBELEGT — bitte vollständig lesen.**
>
> Alle drei Erhebungsaufträge zu den Schmerzpunkten liefen, nachdem das Websuch-Budget der Recherchesitzung bereits
> aufgebraucht war und der Egress-Proxy jeden Seitenabruf blockierte. Dieses Dokument enthält **kein einziges
> verifiziertes Zitat und keine geprüfte Häufigkeitsangabe** — es ist Modellwissen und Strukturanalyse.
>
> Es steht trotzdem hier, weil es sich in weiten Teilen mit dem deckt, was die *belegte* Wettbewerbsanalyse in
> [`01-markt-wettbewerb.md`](01-markt-wettbewerb.md) unabhängig zeigt. Wo ein Schmerzpunkt dort eine Entsprechung hat,
> ist er belastbar; wo nicht, ist er eine Hypothese. Die Prüfliste für einen Folgelauf steht in
> [`quellen/02-pains.md`](quellen/02-pains.md).

Dieses Dokument sammelt, was Menschen im Markt tatsächlich beklagen — nicht, was ein Produkt gern lösen würde. Jeder Schmerzpunkt trägt eine Einschätzung zu Schwere und Häufigkeit, wer ihn heute löst, und welche Antwort MeinSlip darauf geben könnte. Die Antworten sind Vorschläge, keine Zusagen; welche davon gebaut werden, entscheidet [`10-backlog.md`](10-backlog.md).

---

## Verkäufer:innen

### Kritisch und sehr häufig

**Die Absenderadresse verrät die Identität.** Auf jedem Paket steht ein Absender. Rücksendungen, Nachbarn, die annehmen, und die Sendungsverfolgung mit Herkunftsort kommen dazu. Das ist der meistgenannte, existenziellste Schmerzpunkt der gesamten Verkäuferseite. *Heute gelöst durch:* Behelfe — Abgabe in einer fremden Filiale, Packstation, erfundene Absender. Alles unzuverlässig und teils AGB-widrig. → **Safe-Ship**, siehe [`04-features/safe-ship.md`](04-features/safe-ship.md)

**Die Impressumspflicht zwingt zur Veröffentlichung von Klarname und Wohnadresse.** Genau deshalb findet ein großer Teil des Handels auf Telegram und in Instagram-Direktnachrichten statt — dort gibt es kein Impressum, aber auch keinen Käuferschutz. *Heute gelöst durch:* niemanden. → **Kommissionsmodell**: Die GmbH ist Vertragspartnerin des Käufers und stellt Impressum, Rechnung und Rückadresse. Das ist der stärkste einzelne Akquisehebel gegenüber jeder Grau-Alternative. Siehe [`06-steuern-kommissionsmodell.md`](06-steuern-kommissionsmodell.md)

**Deplatforming durch Zahlungsdienstleister.** PayPal, Klarna und Stripe verbieten Adult-Umsätze per AGB; Konten werden ohne Vorwarnung gesperrt, Guthaben teils monatelang einbehalten. *Heute gelöst durch:* Ausweichen auf „PayPal Freunde & Familie" (AGB-Bruch ohne Käuferschutz), Amazon-Wunschlisten, paysafecard, Krypto. → MeinSlip tritt als **Merchant of Record** auf: Die Verkäuferin hat nie eine eigene Vertragsbeziehung zum Zahlungsdienstleister und kann deshalb auch nicht einzeln herausgeworfen werden. Zwei Anbieter parallel.

**Deplatforming der Marketingkanäle.** Instagram, TikTok und teilweise Reddit sperren Konten von Adult-Creatorinnen samt aufgebauter Reichweite. → Plattforminterne Reichweite, die nicht von Meta abhängt: eigener Feed, Abonnieren, Benachrichtigung an Bestandskäufer bei neuem Angebot.

**Klarname und erkennbarer Verwendungszweck auf dem Kontoauszug** — ein Doxxing-Vektor gegenüber Bank, Partner und Familie, und zwar in **beide** Richtungen: beim Käufer auf der Abbuchung, bei der Verkäuferin auf der Auszahlung. → Durchgängig neutrale, aber echte Firmierung. Zu den kartennetzwerkrechtlichen Grenzen siehe [`07-zahlungsverkehr.md`](07-zahlungsverkehr.md).

**Belästigung, Grenzüberschreitung und Stalking.** Käufer drängen auf Treffen, überschreiten Grenzen, verfolgen über verknüpfte Social-Media-Profile. *Heute gelöst durch:* Blockieren — wirkungslos, weil Zweitkonten trivial sind. → **Auch Käufer müssen verifiziert sein.** Allein die Nicht-Anonymität der Käuferseite wirkt stark abschreckend. Dazu geräteübergreifende Sperrlisten und ein Meldebutton mit zugesagter Reaktionszeit.

**Zeitverschwender, Fake-Käufer und No-Shows.** Unbezahlte Vorab-Kommunikation frisst den Großteil der Arbeitszeit. Die häufigste Beschwerde im Wäsche-Segment ist nicht die Gebühr, sondern: *„not one ACTUAL buyer"*, *„just scammers all day long"*. → Struktureller Zeitschutz: Sonderwünsche laufen über kostenpflichtige, verbindliche Bestelloptionen statt über Freitext-Chat; Käufer müssen verifiziert und zahlungsfähig sein, bevor sie schreiben können.

**Discovery.** OnlyFans hat bewusst keine Creator-Suche — Reichweite muss vollständig extern erkauft werden, auf Kanälen, die Adult-Creator sperren. → Discovery als Kernversprechen statt als Nebensache. Siehe [`04-features/discovery-taxonomie.md`](04-features/discovery-taxonomie.md)

**Content-Diebstahl.** Verkaufte Fotos und Videos landen auf Tube-Seiten, in Telegram-Gruppen und auf Reddit. Spezialisierte Takedown-Dienste sind für einzelne Creator zu teuer. → Forensisches Wasserzeichen mit Käufer-Kennung in jedem ausgelieferten Medium; Takedown als Plattformleistung statt als Zusatzprodukt.

**Misstrauen gegenüber US- und UK-Plattformen.** Englische AGB, unerreichbarer Support, unklarer Datenschutz, Währungsprobleme. Die deutschen Cam-Portale werben erfolgreich mit „deutscher Support, deutsche Auszahlung" — das Argument ist empirisch belegt wirksam. → Vollständig deutschsprachig, EUR, deutsche Rechnung, Hosting in der EU. Das ist zugleich der einzige Vorteil, den ein internationaler Marktführer nicht einfach kopieren kann.

### Hoch

**Auszahlungsfrust.** Lange Haltefristen, Rolling Reserves, hohe Mindestbeträge, ausländische Dienstleister, Währungs- und Gebührenverluste. Im Wäsche-Segment zahlt ein Anbieter nur montags von Hand aus, ein anderer erst ab 25 € Guthaben. → SEPA in Euro, öffentlich kommunizierte feste Frist, niedriger oder kein Mindestbetrag. In dieser Zielgruppe ein echtes Kaufargument, kein Hygienefaktor.

**Chargebacks gehen faktisch zulasten der Verkäuferin**, obwohl die Ware bereits versandt ist. Bei nicht rückgabefähiger Intimware ist der Schaden total. → Das Rückbuchungsrisiko trägt die Plattform und preist es in die Provision ein. Genau dafür existiert die Provision.

**Agentur-Knebelverträge.** 30–70 % Umsatzabgabe, Exklusivbindung, lange Laufzeiten, Herausgabe der Zugangsdaten. → Transparente, niedrige Provision ohne Exklusivität, jederzeit kündbar, vollständiger Datenexport inklusive Käuferliste. Ausdrücklich als Anti-Agentur-Position vermarkten.

**Steuerliche Unsicherheit und der DAC7-Schock.** Verkäuferinnen wissen oft nicht, dass Gewerbeanmeldung und Umsatzsteuer anfallen, und werden von der Meldung des Marktplatzes an das Bundeszentralamt für Steuern überrascht — verbunden mit der Angst vor unfreiwilligem Outing gegenüber dem Finanzamt. *Heute gelöst durch:* niemanden; Grau-Kanäle melden gar nicht, was kurzfristig bequem und mittelfristig gefährlich ist. → Die Meldepflicht von Anfang an offen erklären und in einen Vorteil drehen: Jahresübersicht, Export fürs Finanzamt, verständlicher Leitfaden. Wer ohnehin melden muss, sollte daraus ein Seriositätsmerkmal machen.

**Abhängigkeit von einer einzigen Plattform.** Eine Regeländerung über Nacht entwertet jahrelang aufgebaute Reichweite. → Portabilität aktiv anbieten: Datenexport inklusive Käuferliste, keine Exklusivklauseln. Kurzfristig scheinbar gegen das eigene Interesse, tatsächlich der stärkste Vertrauenshebel gegenüber einer Zielgruppe, die schon einmal abgeschaltet wurde.

**Angst, dass die Plattform selbst verschwindet** — durch ein KJM-Verfahren oder eine Netzsperre — und Reichweite plus offenes Guthaben mitnimmt. → Auslöser war in allen bekannten Fällen die fehlende Altersverifikation, nicht der Inhalt. Also von Anfang an eine echte, identitätsbasierte Altersprüfung, deutsche Rechtsform, deutscher Sitz, erreichbarer Jugendschutzbeauftragter.

**KI-generierte Fake-Profile und Fake-Shops**, die kassieren und nie liefern, zerstören das Vertrauen in die gesamte Kategorie — auch in seriöse Anbieterinnen.

> **Der strategisch wichtigste Nebenbefund der gesamten Recherche:** Das physische, versendete Produkt ist **KI-immun**. Während rein digitale Content-Plattformen 2026 von synthetischer Konkurrenz und KI-Begleiterinnen erodiert werden, wächst der Vorteil eines Produkts, das ein Mensch tatsächlich getragen und verpackt haben muss. Das spricht dafür, den physischen Teil nicht als Beiwerk zum Content zu behandeln, sondern als das haltbarere Fundament.

### Mittel

**Preisverfall und fehlende Sichtbarkeit.** Zu viele gleichartige Angebote, neue Verkäuferinnen gehen unter. Große Plattformen verkaufen Sichtbarkeit als Werbeprodukt und verschärfen damit den Preisverfall. → Ranking nach Verlässlichkeit (Lieferquote, Reaktionszeit, Bewertungen) statt nach bezahlter Platzierung. **Angebotswachstum bewusst hinter dem Nachfragewachstum halten** — ein Marktplatz mit zu vielen Verkäuferinnen und zu wenig Käufern verbrennt seine Angebotsseite.

**Der regulatorische Flickenteppich** (JMStV, UK Online Safety Act, US-Bundesstaatengesetze) ist für einzelne Creatorinnen nicht lösbar. → Compliance als Plattformleistung: Die Altersverifikation stellt MeinSlip zentral bereit, die Verkäuferin trägt kein eigenes Rechtsrisiko.

---

## Käufer

### Kritisch und sehr häufig

**Diskretion auf dem Kontoauszug** ist die Kaufhemmung Nummer eins im deutschen Markt. Die Angst, dass Partnerin, Bank, Buchhaltung oder Mitbewohner die Zahlung sehen. *Heute gelöst durch:* teilweise neutrale Kartendeskriptoren; Käufer behelfen sich mit Einweg-Kreditkarten. → Diskretions-Zusage als vertragliches Kernversprechen — inklusive Anzeige **vor** dem Kauf im Klartext: „So erscheint es auf Ihrem Auszug: …". Zu den Grenzen siehe [`07-zahlungsverkehr.md`](07-zahlungsverkehr.md).

**Vorkasse-Betrug bei getragener Wäsche: bezahlt und nie geliefert.** Weil Zahlung und Versand vollständig außerhalb jeder Plattform stattfinden — PayPal Freunde & Familie, Amazon-Gutschein, paysafecard-PIN. *Heute gelöst durch:* praktisch niemanden. Die großen Wäsche-Portale sind Kontaktbörsen und überlassen Zahlung und Versand den Nutzern; Bewertungssysteme sind manipulierbar. → **Treuhand.** Geld wird einbehalten und erst nach bestätigtem Empfang oder Fristablauf freigegeben. Das Versandlabel muss zwingend über die Plattform erzeugt werden, sonst gibt es keinen Nachweis.

**„Rede ich mit ihr oder mit einem Chatter?"** — der größte Vertrauensbruch der gesamten Branche. Käufer zahlen für parasoziale Nähe und bekommen ein Callcenter. *Heute gelöst durch:* niemanden. Im Gegenteil: Die großen Plattformen profitieren vom höheren Umsatz der Agentur-Konten und setzen ihre eigenen Regeln zur persönlichen Kontonutzung nicht durch. → **Der stärkste denkbare Differenzierungshebel:** Pflicht-Deklaration, wer schreibt — die Person selbst, ein autorisiertes Team oder KI-unterstützt —, sichtbar als dauerhaftes Label im Chat. Siehe [`04-features/chat-monetarisierung.md`](04-features/chat-monetarisierung.md)

**Keine Rückerstattung, kein Beschwerdeweg.** „Alle Zahlungen sind endgültig." Der einzige Ausweg — die Kartenrückbuchung — führt zur dauerhaften Kontosperrung. *Heute gelöst durch:* niemanden; die Marktführer sind hier bewusst käuferfeindlich, weil ihr Umsatz an der Creator-Seite hängt. Eine Ombudsstelle für diesen Markt existiert nicht. → Ein benannter, in den AGB definierter Käuferschutz mit klaren Fällen. Gemeinsam mit der Treuhand die eigentliche Daseinsberechtigung der Plattform.

**Zahlung wird abgelehnt.** Deutsche Banken, Kartenherausgeber und vor allem PayPal und Klarna verweigern Zahlungen an Erotikplattformen. Der Käufer *will* zahlen und kann nicht. → Zahlarten-Redundanz als Architekturprinzip: zwei Anbieter parallel mit automatischem Ausweichen, plus eine klare Fehlermeldung statt „Zahlung fehlgeschlagen".

**Die Sprach- und Rechtslücke.** Englische AGB, englischer Support, kein Impressum, kein deutscher Gerichtsstand, keine verwertbare Rechnung.

### Hoch

**Fake-Profile und Catfishing** — gestohlene Fotos, erfundene Personen. → Verifizierung als sichtbares, gestuftes Produktmerkmal statt als unsichtbarer Hintergrundprozess, inklusive periodischem Lebendnachweis.

**KI-generierte Models.** Der Käufer bezahlt für Bilder einer Person, die nie existiert hat. 2025/26 kein Randphänomen mehr. *Gegenläufiger Trend:* Fanvue macht KI ausdrücklich zum Feature. → „100 % echte Menschen" als scharf abgegrenzte Position — der glaubwürdigste Gegenpol zum KI-Trend, mit regulatorischem Rückenwind durch die Transparenzpflichten des AI Act.

**Recycelter Content und Fake-Customs.** Das teuer bezahlte „exklusive" Custom-Video ist altes Material, das an Dutzende verkauft wurde. → Auftragsnummer, die im gelieferten Material sichtbar sein muss — etwa als handgeschriebener Zettel mit Nummer und Datum im Bild. Macht Wiederverwendung nachweisbar statt bloß bestreitbar.

**Abo-Falle.** Automatische Verlängerung als Voreinstellung, versteckte Kündigung, ausländische Anbieter ignorieren den deutschen Kündigungsbutton. → Echter Kündigungsbutton nach § 312k BGB ohne Login-Zwang, und automatische Verlängerung **standardmäßig aus**. Das ist ein bewusster Umsatzverzicht und der stärkste Vertrauensbeweis gegenüber der Konkurrenz.

**Altersverifikation als Kaufabbruch.** Der Registrierungsabbruch ist der teuerste Punkt im Trichter, und die Grau-Kanäle umgehen die Prüfung komplett — das ist ihr Hauptvorteil und der Grund, warum sie nicht verschwinden. → Reibung minimieren statt vermeiden: mehrere Verfahren zur Auswahl, einmalige Verifizierung, danach ein langlebiger Zugang. Die konkrete Ausgestaltung ist rechtlich eng begrenzt, siehe [`04-features/verifizierung-altersstufen.md`](04-features/verifizierung-altersstufen.md).

**Anonyme Bezahlung fehlt.** Ein erheblicher Teil der Nachfrage will ohne Kartenspur zahlen. → Kartenlose, aber regelkonforme Wallet-Aufladung: Überweisung mit neutralem Empfängernamen, Barzahlung im Einzelhandel per Barcode.

**Gutscheine als Betrugsvektor.** Amazon-Codes und paysafecard-PINs sind im Segment faktische Währung — und die unumkehrbarste Zahlungsform, die es gibt. → Gutscheinzahlung **überflüssig machen** statt sie zu verbieten: Das Wallet bietet dieselbe Diskretion, aber mit Absicherung. Dazu übertragbares Geschenk-Guthaben für den emotionalen Anwendungsfall.

**Discovery.** Käufer finden Nischen nicht — man kann nur nach Namen suchen, nicht nach dem, was man sucht. Reddit war der faktische Suchkanal und bricht regulatorisch weg.

**Preisintransparenz und Ausgaben-Eskalation.** Das Abo ist der Köder, danach folgen endloses Pay-per-View und Trinkgeld-Druck. *Heute gelöst durch:* niemanden — Ausgabenlimits stehen dem Umsatz direkt entgegen. → Selbst gesetztes Monatslimit, jederzeit mit Sofortwirkung senkbar, Erhöhung erst nach 24 Stunden Bedenkzeit. Paradoxerweise umsatzstabilisierend, weil es Reue-Rückbuchungen und Abwanderung verhindert.

**Datenschutz beim Versand.** Auch der Käufer will seine Privatadresse nicht an eine fremde Verkäuferin geben. *Heute gelöst durch:* niemanden systematisch. → Genau deshalb muss Safe-Ship **beide** Richtungen anonymisieren, nicht nur eine. Das ist der Punkt, an dem der ursprüngliche Entwurf scheiterte.

**Anzahlungs-Betrug bei Treffen** und der „Verifizierungs-Scam", bei dem Käufer auf eine gefälschte Sicherheitsprüfungsseite gelockt werden. *Heute gelöst durch:* niemanden — diese Transaktionen finden bewusst in unmoderierten Kanälen statt.

> **Eine abweichende Empfehlung, die im Dokument bleiben soll:** Der Rechercheagent, der diesen Schmerzpunkt erhoben hat, empfiehlt ausdrücklich, die Treffen-Funktion **gar nicht** anzubieten — wegen der Erlaubnispflicht nach ProstSchG und des Risikos, den Zahlungsdienstleister zu verlieren. Die Projektentscheidung lautet anders: Treffen ja, aber strikt getrennt und ohne Provision. Wie diese Trennung im Produkt erzwungen wird, steht in [`04-features/safe-meet.md`](04-features/safe-meet.md) und [`05-recht-compliance.md`](05-recht-compliance.md).

---

## Was die beiden Listen gemeinsam sagen

Drei Beobachtungen, die die Produktstrategie tragen:

**Beide Seiten misstrauen einander — und das bremst den gesamten Markt.** Die Verkäuferin fürchtet Rückbuchung und Nichtzahlung, der Käufer fürchtet Vorkasse-Betrug. Die heutige Behelfslösung — Vorkasse per Freundschaftsüberweisung — löst das Problem der einen Seite, indem sie die andere schutzlos stellt. Treuhand löst beide gleichzeitig. Das ist der Grund, warum die Plattform existieren sollte.

**Die teuersten Schmerzpunkte sind keine Feature-Wünsche, sondern Angst.** Angst vor Doxxing, vor der Sperrung, vor dem Finanzamt, vor dem Kontoauszug. Ein Produkt, das Angst reduziert, hat in diesem Markt mehr Anziehungskraft als eines, das Funktionen hinzufügt.

**Fast jeder kritische Schmerzpunkt wird heute von niemandem gelöst.** Bei den meisten Einträgen steht unter „heute gelöst durch" entweder „niemand" oder ein Behelf, der gegen die AGB eines Dritten verstößt. Das ist ungewöhnlich und die eigentliche Chance.
