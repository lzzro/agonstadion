/* Stadion · Agón — interruptor de modo noche.
   -------------------------------------------------------------------
   Es el único JavaScript del proyecto. Hace tres cosas y ninguna más:
   leer la preferencia guardada, poner o sacar data-theme="noche" en
   <html>, y guardar la elección.

   Todo lo visual lo resuelve el CSS a partir de ese atributo: los
   colores salen del bloque [data-theme="noche"] de style.css y los dos
   discos del botón ya están los dos en el HTML, así que el CSS muestra
   el que corresponde. Este archivo no dibuja, no escribe texto y no
   toca ningún otro elemento de la página.

   El archivo se carga en el <head> a propósito, sin defer: si esperara
   al final del body, la página se vería un instante en modo día antes
   de cambiar. */

(function () {
  'use strict';

  var CLAVE = 'stadion-tema';

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

  /* 2. Cuando el HTML terminó de cargar, se engancha el botón. */
  document.addEventListener('DOMContentLoaded', function () {
    var boton = document.getElementById('interruptor-tema');
    if (!boton) {
      return;
    }
    boton.addEventListener('click', function () {
      var esNoche = document.documentElement.getAttribute('data-theme') === 'noche';
      if (esNoche) {
        document.documentElement.removeAttribute('data-theme');
      } else {
        document.documentElement.setAttribute('data-theme', 'noche');
      }
      try {
        window.localStorage.setItem(CLAVE, esNoche ? 'dia' : 'noche');
      } catch (error) {
        /* no se pudo guardar: el cambio vale igual para esta página */
      }
    });
  });
})();
