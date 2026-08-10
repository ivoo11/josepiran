"use strict";

document.documentElement.classList.add("js");

const prefersReducedMotion = window.matchMedia(
  "(prefers-reduced-motion: reduce)"
).matches;

const socialCards = document.querySelectorAll(".social-card");


/* =========================================================
   CONTADORES
   ========================================================= */

function formatNumber(value) {
  return Math.round(value).toLocaleString("es-AR");
}

function animateCounter(counter) {
  const target = Number(counter.dataset.count);

  if (!Number.isFinite(target)) {
    return;
  }

  /*
   * Evita que el mismo contador vuelva a animarse.
   */
  if (counter.dataset.animated === "true") {
    return;
  }

  counter.dataset.animated = "true";

  const duration = 2200;
  const startTime = performance.now();

  function updateCounter(currentTime) {
    const elapsed = currentTime - startTime;

    const progress = Math.min(
      elapsed / duration,
      1
    );

    /*
     * Ease-out cúbico.
     * Arranca rápido y frena suavemente al final.
     */
    const easedProgress =
      1 - Math.pow(1 - progress, 3);

    const currentValue =
      target * easedProgress;

    counter.textContent =
      formatNumber(currentValue);

    if (progress < 1) {
      requestAnimationFrame(updateCounter);
      return;
    }

    /*
     * Garantizamos el valor exacto al terminar.
     */
    counter.textContent =
      formatNumber(target);
  }

  requestAnimationFrame(updateCounter);
}


/* =========================================================
   ANIMAR CONTADORES DE UNA CARD
   ========================================================= */

function animateCardCounters(card) {
  const counters = card.querySelectorAll(
    ".social-stat__number"
  );

  counters.forEach((counter, index) => {

    /*
     * Pequeño delay entre métricas.
     */
    const delay = index * 110;

    setTimeout(() => {
      animateCounter(counter);
    }, delay);

  });
}


/* =========================================================
   REVELAR CARD
   ========================================================= */

function revealCard(card) {
  card.classList.add("is-visible");

  if (prefersReducedMotion) {
    const counters = card.querySelectorAll(
      ".social-stat__number"
    );

    counters.forEach((counter) => {
      const target = Number(counter.dataset.count);

      if (Number.isFinite(target)) {
        counter.textContent =
          formatNumber(target);

        counter.dataset.animated = "true";
      }
    });

    return;
  }

  animateCardCounters(card);
}


/* =========================================================
   FALLBACK
   ========================================================= */

function revealAllCards() {
  socialCards.forEach((card) => {
    revealCard(card);
  });
}


/* =========================================================
   INTERSECTION OBSERVER
   ========================================================= */

if (
  prefersReducedMotion ||
  !("IntersectionObserver" in window)
) {

  revealAllCards();

} else {

  const cardObserver = new IntersectionObserver(
    (entries, observer) => {

      entries.forEach((entry) => {

        if (!entry.isIntersecting) {
          return;
        }

        revealCard(entry.target);

        /*
         * Se observa una sola vez.
         * Si subís y volvés a bajar,
         * no vuelve a arrancar desde cero.
         */
        observer.unobserve(entry.target);

      });

    },
    {
      threshold: 0.22,
      rootMargin: "0px 0px -5% 0px"
    }
  );

  socialCards.forEach((card) => {
    cardObserver.observe(card);
  });

}


/* =========================================================
   MEDICIÓN DE CLICS
   Funciona automáticamente cuando se instale Google Analytics.
   ========================================================= */

socialCards.forEach((card) => {

  card.addEventListener("click", () => {

    const network = card.dataset.network;

    if (typeof window.gtag === "function") {

      window.gtag("event", "social_click", {
        social_network: network,
        link_url: card.href
      });

    }

  });

});


/* =========================================================
   PARALLAX MUY SUTIL DE LA FOTO
   Solo se ejecuta en dispositivos con mouse.
   ========================================================= */

const portrait = document.querySelector(
  ".hero__portrait img"
);

const supportsHover = window.matchMedia(
  "(hover: hover) and (pointer: fine)"
).matches;

if (
  portrait &&
  supportsHover &&
  !prefersReducedMotion
) {

  window.addEventListener(
    "pointermove",
    (event) => {

      const x =
        event.clientX / window.innerWidth - 0.5;

      const y =
        event.clientY / window.innerHeight - 0.5;

      portrait.style.transform = `
        translate3d(
          ${x * 5}px,
          ${y * 3}px,
          0
        )
      `;

    },
    { passive: true }
  );

}

/* =========================================================
   MANIFIESTO — PROGRESO CONTROLADO POR SCROLL
   ========================================================= */

const manifesto = document.querySelector(".manifesto");

if (manifesto) {

  const manifestoLines =
    manifesto.querySelectorAll(
      ".manifesto__text span"
    );

  let manifestoTicking = false;


  /* =======================================================
     ACTUALIZAR PROGRESO
     ======================================================= */

  function updateManifestoProgress() {

    const rect =
      manifesto.getBoundingClientRect();

    const sectionHeight =
      manifesto.offsetHeight;

    const viewportHeight =
      window.innerHeight;


    /*
     * Distancia real durante la cual
     * el sticky permanece clavado.
     */

    const scrollDistance =
      sectionHeight - viewportHeight;


    /*
     * rect.top:
     *
     *  0    → acaba de comenzar
     * -X    → estamos avanzando
     *
     * progress:
     *
     * 0 → inicio
     * 1 → final
     */

    let progress =
      -rect.top / scrollDistance;


    progress = Math.max(
      0,
      Math.min(progress, 1)
    );


    /*
     * Aplicamos exactamente el mismo progreso
     * a todas las líneas.
     */

    const fill =
      `${progress * 100}%`;


    manifestoLines.forEach((line) => {

      line.style.setProperty(
        "--fill",
        fill
      );

    });


    manifestoTicking = false;
  }


  /* =======================================================
     REQUEST ANIMATION FRAME
     ======================================================= */

  function requestManifestoUpdate() {

    if (manifestoTicking) {
      return;
    }

    manifestoTicking = true;

    requestAnimationFrame(
      updateManifestoProgress
    );

  }


  /* =======================================================
     REDUCED MOTION
     ======================================================= */

  if (prefersReducedMotion) {

    manifestoLines.forEach((line) => {

      line.style.setProperty(
        "--fill",
        "100%"
      );

    });

  } else {

    window.addEventListener(
      "scroll",
      requestManifestoUpdate,
      {
        passive: true
      }
    );


    window.addEventListener(
      "resize",
      requestManifestoUpdate,
      {
        passive: true
      }
    );


    /*
     * Estado inicial.
     */

    updateManifestoProgress();

  }

}