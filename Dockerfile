FROM php:7.2-cli-alpine

# install php-uv and zip
RUN apk add --no-cache git libuv-dev zlib-dev && \
git clone https://github.com/bwoebi/php-uv.git /tmp/php-uv --recursive && \
docker-php-ext-install /tmp/php-uv zip

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

CMD ["php", "/var/app/vendor/bin/cluster", "-d", "/var/app/public/index.php"]
ENV APP_PORT=80
EXPOSE 80
