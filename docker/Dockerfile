FROM php:8.3-apache

# mod_rewrite and AllowOverride so the .htaccess files take effect here just
# as on an ordinary host: everything through index.php, the rest hidden.
RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod headers rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/songwunsch.ini
COPY docker/entrypoint.sh /usr/local/bin/songwunsch-entrypoint
RUN chmod +x /usr/local/bin/songwunsch-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["songwunsch-entrypoint"]
CMD ["apache2-foreground"]
