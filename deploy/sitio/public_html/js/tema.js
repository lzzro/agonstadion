/* Stadion · Agón — interruptor de modo noche.
   -------------------------------------------------------------------
   Es el único JavaScript del proyecto. Lee la preferencia guardada,
   pone o saca data-theme="noche" en <html> y guarda la elección.

   Todo lo visual lo resuelve el CSS a partir de ese atributo: los
   colores salen del bloque [data-theme="noche"] de style.css y los dos
   discos del botón ya están los dos en el HTML, así que el CSS muestra
   el que corresponde. Este archivo no dibuja ni escribe texto.

   Además, cada vez que el modo cambia, deja al día dos atributos que
   el CSS no puede tocar, y nada más:
     - aria-pressed del botón ("true" con el modo noche puesto): el
       nombre del botón es fijo, "Modo noche", y el lector de pantalla
       dice si está apretado o no;
     - el color de la barra del navegador (<meta name="theme-color">),
       que toma el fondo del modo: el valor de --pent que el CSS ya
       resolvió, sin escribir ningún color acá.

   El archivo se carga en el <head> a propósito, sin defer: si esperara
   al final del body, la página se vería un instante en modo día antes
   de cambiar. */

(function () {
  'use strict';

  var CLAVE = 'stadion-tema';

  function esNoche() {
    return document.documentElement.getAttribute('data-theme') === 'noche';
  }

  /* El botón y la barra del navegador, al día con el modo puesto. */
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

  /* 1. Antes de que la página se dibuje: si hay preferencia guardada,
        se aplica. Va dentro de try porque en modo privado leer
        localStorage puede fallar; si falla, queda el modo día. */
  try {
    if (window.localStorage.getItem(CLAVE) === 'noche') {
      document.documentElement.setAttribute('data-theme', 'noche');
    }
  } catch (error) {
    /* sin almacenamiento disponible: se sigue en modo día */
  }
  reflejar();

  /* 2. Cuando el HTML terminó de cargar, se engancha el botón. */
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
        /* no se pudo guardar: el cambio vale igual para esta página */
      }
    });
  });
})();
