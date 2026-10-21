# 鲜花商城外卖系统 v2 —— Render 单容器版（PHP 8.2 + 内置服务器）
FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY frontend/  /app/frontend/
COPY backend/   /app/backend/
COPY router.php /app/router.php

ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 10000

# Render 会注入 PORT 环境变量，必须监听它
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /app/frontend /app/router.php"]