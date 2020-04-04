FROM php:7.4-fpm-alpine3.11

# install packages
RUN apk add --no-cache openssl nginx runit && docker-php-ext-install pdo_mysql

# Install Composer
RUN curl https://getcomposer.org/composer.phar > /usr/sbin/composer

# Copy configs
COPY container/php.ini /etc/php7/php.ini
COPY container/nginx.conf /etc/nginx/nginx.conf
COPY container/fpm.conf /etc/php7/php-fpm.conf

# set up runit
COPY container/runsvinit /sbin/runsvinit
RUN mkdir /tmp/nginx && mkdir -p /etc/service/nginx && echo '#!/bin/sh' >> /etc/service/nginx/run && \
echo 'nginx' >> /etc/service/nginx/run && chmod +x /etc/service/nginx/run && \
mkdir -p /etc/service/fpm && echo '#!/bin/sh' >> /etc/service/fpm/run && \
echo 'php-fpm -FR' >> /etc/service/fpm/run && chmod +x /etc/service/fpm/run && \
chmod +x /sbin/runsvinit
ENTRYPOINT /sbin/runsvinit
WORKDIR /var/app
EXPOSE 80

# set up app; order of operations optimized for maximum layer reuse
COPY composer.lock /var/app/composer.lock
COPY composer.json /var/app/composer.json
RUN cd /var/app && php /usr/sbin/composer install --prefer-dist -o
COPY templates /var/app/templates
COPY public /var/app/public
COPY bootstrap /var/app/bootstrap
