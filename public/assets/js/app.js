/*
 * Oberflächenlogik.
 *
 * Bewusst schlank und ohne Baukette: Die Anwendung läuft auf einfachem
 * Webhosting, und jede Zeile, die nicht geladen werden muss, macht sie auf
 * schlechten Mobilverbindungen schneller.
 */

(() => {
  "use strict";

  /* --- Schnellverbergen -------------------------------------------------
   *
   * Diskretion als Funktion, nicht als Werbeversprechen. Esc, ein Doppeltipp
   * auf die Schaltfläche oder der Wechsel in eine andere App legt sofort einen
   * unverfänglichen Bildschirm darüber.
   */
  const vorhang = document.querySelector("[data-vorhang]");

  function verbergen() {
    if (!vorhang) return;
    vorhang.hidden = false;
    vorhang.dataset.sichtbar = "ja";
    document.title = "Neuer Tab";
  }

  function zeigen() {
    if (!vorhang) return;
    vorhang.dataset.sichtbar = "nein";
    vorhang.hidden = true;
  }

  document.querySelectorAll("[data-verbergen]").forEach((knopf) => {
    knopf.addEventListener("click", verbergen);
  });

  vorhang?.addEventListener("click", zeigen);

  document.addEventListener("keydown", (ereignis) => {
    if (ereignis.key === "Escape") verbergen();
  });

  /* --- Service Worker ---------------------------------------------------- */
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("/sw.js").catch(() => {
        /* Ohne Service Worker funktioniert die Seite weiterhin. */
      });
    });
  }

  /* --- Installationshinweis ---------------------------------------------
   *
   * Auf iOS gibt es Push NUR, wenn die App auf dem Homescreen liegt. Der
   * Hinweis ist deshalb kein Beiwerk, sondern Voraussetzung dafür, dass
   * Benachrichtigungen überhaupt ankommen.
   */
  let installEreignis = null;
  const installKnopf = document.querySelector("[data-installieren]");

  window.addEventListener("beforeinstallprompt", (ereignis) => {
    ereignis.preventDefault();
    installEreignis = ereignis;
    if (installKnopf) installKnopf.hidden = false;
  });

  installKnopf?.addEventListener("click", async () => {
    if (!installEreignis) return;
    installEreignis.prompt();
    await installEreignis.userChoice;
    installEreignis = null;
    installKnopf.hidden = true;
  });

  /** Läuft die Seite als installierte App? */
  function istInstalliert() {
    return (
      window.matchMedia("(display-mode: standalone)").matches ||
      window.navigator.standalone === true
    );
  }

  function istApple() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
  }

  // Auf iOS gibt es kein beforeinstallprompt — dort braucht es eine Anleitung.
  const appleHinweis = document.querySelector("[data-apple-hinweis]");
  if (appleHinweis && istApple() && !istInstalliert()) {
    appleHinweis.hidden = false;
  }

  /* --- Konfigurator ------------------------------------------------------
   *
   * Rechnet den Preis live mit und setzt durch, dass mindestens eine
   * Spezifikation gewählt ist. Das ist keine Bequemlichkeit: Ohne
   * Kundenspezifikation trägt der Widerrufsausschluss nach
   * § 312g Abs. 2 Nr. 1 BGB nicht — der Server weist eine solche Bestellung
   * ohnehin ab, aber der Mensch soll es vorher sehen.
   */
  const konfigurator = document.querySelector("[data-konfigurator]");

  if (konfigurator) {
    const grundpreis = Number(konfigurator.dataset.grundpreis || 0);
    const anzeige = konfigurator.querySelector("[data-summe]");
    const absenden = konfigurator.querySelector("[data-absenden]");
    const warnung = konfigurator.querySelector("[data-spezifikation-warnung]");

    function neuBerechnen() {
      let summe = grundpreis;
      let spezifikationen = 0;

      konfigurator.querySelectorAll("[data-option]").forEach((feld) => {
        const gewaehlt = feld.type === "checkbox" ? feld.checked : feld.value !== "";
        if (!gewaehlt) return;

        summe += Number(feld.dataset.aufpreis || 0);
        if (feld.dataset.spezifikation === "ja") spezifikationen += 1;
      });

      if (anzeige) {
        anzeige.textContent = (summe / 100).toLocaleString("de-DE", {
          style: "currency",
          currency: "EUR",
        });
      }

      const gueltig = spezifikationen > 0;
      if (absenden) absenden.disabled = !gueltig;
      if (warnung) warnung.hidden = gueltig;
    }

    konfigurator.addEventListener("change", neuBerechnen);
    konfigurator.addEventListener("input", neuBerechnen);
    neuBerechnen();
  }
})();
