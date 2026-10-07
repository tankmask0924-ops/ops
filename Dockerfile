# 本地开发用：官方 PHP 镜像 + pdo_mysql + composer；服务器部署不用 Docker，见 README
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli-alpine

RUN docker-php-ext-install pdo_mysql \
    && echo 'date.timezone=Asia/Shanghai' > /usr/local/etc/php/conf.d/ops.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /opt/www
EXPOSE 9501

ENV PHP_CLI_SERVER_WORKERS=4
CMD ["php", "-S", "0.0.0.0:9501", "-t", "public", "public/index.php"]
