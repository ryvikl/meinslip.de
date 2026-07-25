/*
 * Service Worker für MeinSlip.
 *
 * Zweck: Installierbarkeit auf dem Homescreen, ein brauchbarer Offline-Zustand
 * und die Grundlage für Push.
 *
 * Bewusst NICHT zwischengespeichert werden Inhalte, Medien und alles unter
 * /api/. Auf einer Plattform, die mit Diskretion wirbt, darf nichts
 * Persönliches im Browsercache eines womöglich geteilten Geräts liegen
 * bleiben. Zwischengespeichert wird nur die Hülle.
 */

const VERSION = "v1";
const HUELLE_CACHE = `meinslip-huelle-${VERSION}`;

const HUELLE = [
  "/offline",
  "/assets/css/app.css",
  "/assets/css/tokens.css",
  "/assets/js/app.js",
];

self.addEventListener("install", (ereignis) => {
  ereignis.waitUntil(
    caches
      .open(HUELLE_CACHE)
      .then((cache) => cache.addAll(HUELLE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (ereignis) => {
  ereignis.waitUntil(
    caches
      .keys()
      .then((namen) =>
        Promise.all(
          namen
            .filter((name) => name.startsWith("meinslip-") && name !== HUELLE_CACHE)
            .map((name) => caches.delete(name))
        )
      )
      .then(() => self.clients.claim())
  );
});

/** Pfade, die niemals im Cache landen dürfen. */
function istVertraulich(url) {
  return (
    url.pathname.startsWith("/api/") ||
    url.pathname.startsWith("/medien/") ||
    url.pathname.startsWith("/nachrichten") ||
    url.pathname.startsWith("/guthaben") ||
    url.pathname.startsWith("/bestellungen")
  );
}

self.addEventListener("fetch", (ereignis) => {
  const anfrage = ereignis.request;

  if (anfrage.method !== "GET") return;

  const url = new URL(anfrage.url);

  if (url.origin !== self.location.origin) return;

  if (istVertraulich(url)) {
    // Direkt ans Netz, kein Cache, keine Ablage.
    ereignis.respondWith(fetch(anfrage));
    return;
  }

  // Statische Hülle: erst Cache, dann Netz.
  if (HUELLE.includes(url.pathname)) {
    ereignis.respondWith(
      caches.match(anfrage).then((treffer) => treffer || fetch(anfrage))
    );
    return;
  }

  // Seitenaufrufe: erst Netz, bei Ausfall die Offline-Seite.
  if (anfrage.mode === "navigate") {
    ereignis.respondWith(fetch(anfrage).catch(() => caches.match("/offline")));
  }
});

/*
 * Push.
 *
 * Der Inhalt kommt bewusst NEUTRAL vom Server — nie ein Absendername, nie ein
 * Produktname, nie ein Vorschaubild. Siehe docs/09-design-system.md.
 *
 * Wichtig: Auf iOS funktioniert Push nur, wenn die App auf dem Homescreen
 * installiert ist. Und weil Push generell nicht zustellgarantiert ist, darf
 * keine Sicherheitsfunktion allein daran hängen.
 */
self.addEventListener("push", (ereignis) => {
  let daten = { titel: "MeinSlip", text: "Du hast eine neue Nachricht.", url: "/" };

  try {
    if (ereignis.data) daten = { ...daten, ...ereignis.data.json() };
  } catch (fehler) {
    // Kaputte Nutzlast darf die Benachrichtigung nicht verhindern.
  }

  ereignis.waitUntil(
    self.registration.showNotification(daten.titel, {
      body: daten.text,
      icon: "/assets/symbole/icon-192.png",
      badge: "/assets/symbole/badge.png",
      tag: daten.tag || "meinslip",
      renotify: false,
      data: { url: daten.url },
    })
  );
});

self.addEventListener("notificationclick", (ereignis) => {
  ereignis.notification.close();
  const ziel = ereignis.notification.data?.url || "/";

  ereignis.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((fenster) => {
      for (const f of fenster) {
        if (f.url.includes(ziel) && "focus" in f) return f.focus();
      }
      return self.clients.openWindow(ziel);
    })
  );
});
