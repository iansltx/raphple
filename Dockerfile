FROM php:7.4-cli-alpine3.11

# install php-uv and zip
RUN apk add --no-cache git libuv-dev libzip-dev && \
git clone https://github.com/bwoebi/php-uv.git /tmp/php-uv --recursive && \
docker-php-ext-install /tmp/php-uv zip pcntl

# Install Composer
RUN curl https://getcomposer.org/composer.phar > /usr/sbin/composer

# Copy configs
COPY container/php.ini /usr/local/etc/php

# set up app; order of operations optimized for maximum layer reuse
RUN mkdir /var/app
COPY composer.lock /var/app/composer.lock
COPY composer.json /var/app/composer.json
RUN cd /var/app && php /usr/sbin/composer install --prefer-dist -o
COPY templates /var/app/templates
COPY public /var/app/public
COPY bootstrap /var/app/bootstrap

WORKDIR /var/app
CMD ["vendor/bin/cluster", "public/index.php"]
ENV APP_PORT=80
EXPOSE 80
