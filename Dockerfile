FROM alpine:edge

# install packages
RUN echo "@testing http://dl-4.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories && \
apk add --update curl php7-common@testing php7-curl@testing php7-phar@testing php7-mbstring@testing \
php7-pcntl@testing php7-json@testing php7-opcache@testing php7-fpm@testing php7@testing php7-openssl@testing \
php7-pdo_mysql@testing openssl nginx runit@testing && rm -rf /var/cache/apk/*

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
echo 'php-fpm7 -FR' >> /etc/service/fpm/run && chmod +x /etc/service/fpm/run && \
chmod +x /sbin/runsvinit
ENTRYPOINT ["/sbin/runsvinit"]
EXPOSE 80

# For local dev, mount volume
VOLUME /var/app

# set up app; order of operations optimized for maximum layer reuse
# RUN mkdir /var/app
# COPY composer.lock /var/app/composer.lock
# COPY composer.json /var/app/composer.json
# RUN cd /var/app && php7 /usr/sbin/composer install --prefer-dist -o
# COPY templates /var/app/templates
# COPY public /var/app/public
# COPY bootstrap /var/app/bootstrap
