// Stadion · Agón: interruptor de modo noche.

(function () {
  'use strict';

  var CLAVE = 'stadion-tema';

  function esNoche() {
    return document.documentElement.getAttribute('data-theme') === 'noche';
  }

  // El botón y la barra del navegador, al día con el modo puesto.
  function reflejar() {
    var boton = document.getElementById('interruptor-tema');
    if (boton) {
      boton.setAttribute('aria-pressed', esNoche() ? 'true' : 'false');
    }
    var barra = document.querySelector('meta[name="theme-color"]');
    if (barra) {
      var fondo = window.getComputedStyle(document.documentElement).getPropertyValue('--pent').trim();
      if (fondo !== '') {
        barra.setAttribute('content', fondo);
      }
    }
  }

  // 1. Antes de que la página se dibuje: si hay preferencia guardada, se aplica.
  try {
    if (window.localStorage.getItem(CLAVE) === 'noche') {
      document.documentElement.setAttribute('data-theme', 'noche');
    }
  } catch (error) {
  }
  reflejar();

  // 2. Cuando el HTML terminó de cargar, se engancha el botón.
  document.addEventListener('DOMContentLoaded', function () {
    reflejar();
    var boton = document.getElementById('interruptor-tema');
    if (!boton) {
      return;
    }
    boton.addEventListener('click', function () {
      var eraNoche = esNoche();
      if (eraNoche) {
        document.documentElement.removeAttribute('data-theme');
      } else {
        document.documentElement.setAttribute('data-theme', 'noche');
      }
      reflejar();
      try {
        window.localStorage.setItem(CLAVE, eraNoche ? 'dia' : 'noche');
      } catch (error) {
      }
    });
  });
})();
