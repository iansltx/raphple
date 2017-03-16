FROM php:7.1-cli

# install php-uv
RUN apt-get update && apt-get install -y git automake libtool gcc zlib1g-dev && \
git clone https://github.com/bwoebi/php-uv.git /var/php-uv --recursive && cd /var/php-uv && \
mkdir libuv && curl -L https://github.com/libuv/libuv/archive/v1.11.0.tar.gz | tar xzf - && \
cd /var/php-uv/libuv-1.11.0 && ./autogen.sh && ./configure --prefix=$(readlink -f `pwd`/../libuv) && \
make CFLAGS=-fPIC && make install && cd .. && mv libuv-1.11.0 libuv && cd /var/php-uv && phpize && \
./configure --with-uv=$(readlink -f `pwd`/libuv) && make && make install && pecl install zip && \
docker-php-ext-enable zip

# Install Composer
RUN curl https://getcomposer.org/composer.phar > /usr/sbin/composer

# Copy configs
COPY container/php.ini /usr/local/etc/php

ENTRYPOINT ["php", "/var/app/vendor/bin/aerys", "-d", "-c", "/var/app/public/index.php"]
EXPOSE 80

# set up app; order of operations optimized for maximum layer reuse
RUN mkdir /var/app
COPY composer.lock /var/app/composer.lock
COPY composer.json /var/app/composer.json
RUN cd /var/app && php /usr/sbin/composer install --prefer-dist -o
COPY templates /var/app/templates
COPY public /var/app/public
