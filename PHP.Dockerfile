FROM php:7.4-fpm-alpine
WORKDIR "/app"

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions xdebug
RUN install-php-extensions mysqli
RUN install-php-extensions mbstring
RUN install-php-extensions curl

ENTRYPOINT ["php-fpm"]