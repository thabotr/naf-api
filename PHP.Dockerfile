FROM php:7.4-fpm-alpine
WORKDIR "/app"

# Optional, force UTC as server time
RUN echo "UTC" > /etc/timezone

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions xdebug
RUN install-php-extensions mysqli
RUN install-php-extensions mbstring
RUN install-php-extensions curl

ENTRYPOINT ["php-fpm"]