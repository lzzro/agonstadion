# =====================================================================
# El sitio de Stadion: Apache con PHP 8.3 (lo mismo que la VM)
# ---------------------------------------------------------------------
# Usa la copia de publicacion (deploy/sitio, la que arma
# scripts/armar-deploy.sh): public_html es lo unico que Apache publica y
# stadion_app queda al lado, fuera de su alcance.
# =====================================================================

FROM php:8.3-apache

# mysqli: la unica extension que necesita el sitio.
RUN docker-php-ext-install mysqli

ENV TZ=America/Montevideo

# Lo que se publica, y la aplicacion al lado.
COPY deploy/sitio/public_html/ /var/www/html/
COPY deploy/sitio/stadion_app/ /var/www/stadion_app/

# La configuracion de la base: lee la clave de la variable de entorno
# (docker-compose.yml, que la toma del .env). No tiene nada secreto.
COPY docker/web/database.docker.php /var/www/stadion_app/config/database.local.php

# El script que carga los datos de prueba (se corre a mano, ver
# docs/docker.md).
COPY scripts/datos-de-prueba.php /var/www/scripts/datos-de-prueba.php

# Apache: que lea los .htaccess del sitio, y la zona horaria de PHP.
# mod_headers no viene prendido en esta imagen: sin el, el .htaccess
# no manda las cabeceras de seguridad (las saltea, no da error).
COPY docker/web/stadion.conf /etc/apache2/conf-available/stadion.conf
RUN a2enconf stadion \
    && a2enmod headers \
    && printf 'date.timezone = America/Montevideo\nexpose_php = Off\n' > /usr/local/etc/php/conf.d/stadion.ini \
    && mkdir -p /var/www/html/subidas \
    && chown -R www-data:www-data /var/www/html/subidas
