FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-client \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html
COPY docker/start.sh /usr/local/bin/start-harl
RUN chmod +x /usr/local/bin/start-harl

WORKDIR /var/www/html
EXPOSE 8080

CMD ["/usr/local/bin/start-harl"]
