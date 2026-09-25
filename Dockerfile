FROM dunglas/frankenphp:1-php8.5-alpine

RUN set -eux; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    { \
        echo 'expose_php = Off'; \
        echo 'variables_order = EGPCS'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'opcache.memory_consumption = 32'; \
        echo 'opcache.max_accelerated_files = 1000'; \
    } > "$PHP_INI_DIR/conf.d/zz-app.ini"

# TLS is terminated upstream (ingress): plain HTTP on an unprivileged port
ENV SERVER_NAME=:8080
EXPOSE 8080

ARG USER=app
RUN set -eux; \
    adduser -D "$USER"; \
    chown -R "$USER:$USER" /config/caddy /data/caddy

COPY --chown=root:root --chmod=444 index.php /app/public/index.php

USER ${USER}
