FROM php:7.4-cli

RUN docker-php-ext-install pdo_mysql \
    && { \
        echo 'file_uploads=On'; \
        echo 'upload_max_filesize=5M'; \
        echo 'post_max_size=8M'; \
        echo 'memory_limit=256M'; \
        echo 'max_file_uploads=20'; \
    } > /usr/local/etc/php/conf.d/zz-upload-limits.ini

WORKDIR /app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "/app"]
