FROM php:7.4.33-apache-buster 
WORKDIR "/var/www/html/"

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions xdebug
RUN install-php-extensions mysqli
RUN install-php-extensions mbstring
RUN install-php-extensions curl
RUN a2enmod rewrite
RUN service apache2 restart