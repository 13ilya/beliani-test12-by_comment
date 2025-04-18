FROM php:5.6.30-apache

# --- SOFT --- #

RUN apt-get update && apt-get install -y \
    libmcrypt-dev \
    curl \
    zip \
    git \
    # For soap
    libxml2-dev \
    # For gd
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng12-dev \
    libxpm-dev \
    libvpx-dev \
    sshpass \
    libssl-dev \
    libssh2-1-dev

# Configure GD package for JPEG support
#RUN docker-php-ext-configure gd \
#        --with-png-dir=/usr \ 
#        --with-jpeg-dir=/usr \
#        --with-freetype-dir=/usr \
RUN docker-php-ext-install gd

RUN pecl install ssh2-0.12

RUN docker-php-ext-install mysql && \
    docker-php-ext-install mcrypt && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-install mbstring && \
    docker-php-ext-install soap && \
    docker-php-ext-install ftp && \
    docker-php-ext-install pcntl && \
    docker-php-ext-enable ssh2

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

#xdebug
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
#RUN sed -i '1 a xdebug.profiler_enable_trigger=1' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.profiler_enable=0' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.profiler_output_dir="/var/www/html/App/tmp/xdebug"' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.auto_trace=0' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.trace_enable_trigger=1' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.trace_output_dir="/var/www/html/App/tmp/xdebug"' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.trace_format=0' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.remote_enable=1' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.remote_connect_back=1' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN sed -i '1 a xdebug.remote_port=9000' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# install phpredis extension
#ENV PHPREDIS_VERSION 2.2.7
#ENV PHPREDIS_VERSION 3.1.2
#
#RUN curl -L -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/$PHPREDIS_VERSION.tar.gz \
#RUN tar xfz /tmp/redis.tar.gz \
#RUN rm -r /tmp/redis.tar.gz \
#RUN mv phpredis-$PHPREDIS_VERSION /usr/src/php/ext/redis \
#RUN docker-php-ext-install redis \

RUN pecl install -o -f redis \
&&  rm -rf /tmp/pear \
&&  docker-php-ext-enable redis

# for mailcatcher
RUN apt-get install -y ssmtp  && \
    echo "sendmail_path = /usr/sbin/ssmtp -t" > /usr/local/etc/php/conf.d/sendmail.ini && \
    echo "mailhub=mailcatcher:1025\nUseTLS=NO\nFromLineOverride=YES" > /etc/ssmtp/ssmtp.conf

# --- PREPARE --- #

RUN a2enmod rewrite

EXPOSE 80
