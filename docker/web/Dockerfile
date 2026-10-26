# El sitio de Stadion: Apache con PHP 8.3 (lo mismo que la VM)

FROM php:8.3-apache

RUN docker-php-ext-install mysqli

ENV TZ=America/Montevideo

# Lo que se publica, y la aplicacion al lado.
COPY deploy/sitio/public_html/ /var/www/html/
COPY deploy/sitio/stadion_app/ /var/www/stadion_app/

COPY docker/web/database.docker.php /var/www/stadion_app/config/database.local.php

# El script que carga los datos de prueba (se corre a mano).
COPY scripts/datos-de-prueba.php /var/www/scripts/datos-de-prueba.php

COPY docker/web/stadion.conf /etc/apache2/conf-available/stadion.conf
RUN a2enconf stadion \
    && a2enmod headers \
    && printf 'date.timezone = America/Montevideo\nexpose_php = Off\n' > /usr/local/etc/php/conf.d/stadion.ini \
    && mkdir -p /var/www/html/subidas \
    && chown -R www-data:www-data /var/www/html/subidas
