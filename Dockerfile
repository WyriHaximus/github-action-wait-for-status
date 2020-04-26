FROM wyrihaximusnet/php:7.4-zts-alpine3.11-dev-root

RUN mkdir /workdir
COPY ./composer.json /workdir
COPY ./composer.lock /workdir
WORKDIR /workdir

RUN composer install --ansi --no-progress --no-interaction --prefer-dist
COPY ./src /workdir/src
COPY ./wait.php /workdir

ENTRYPOINT ["php", "/workdir/wait.php"]
