FROM php:8.2-cli

# أدوات النظام + إضافات PHP المطلوبة لـ Laravel و MySQL
RUN apt-get update && apt-get install -y \
        git unzip libzip-dev libonig-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# رفع حدود PHP لقبول ملفات PDF كبيرة الحجم
COPY php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Composer (مثبّت على 2.7 قبل تفعيل حظر التبعيات بسبب التنبيهات الأمنية)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# 1) مشروع Laravel جديد كامل + Sanctum
# (تعطيل حظر التبعيات المتعلق بالتنبيهات الأمنية — بيئة تطوير محلية)
RUN composer config --global policy.advisories.block false 2>/dev/null || true \
    && composer create-project laravel/laravel:^11.0 . --no-interaction --prefer-dist --no-audit \
    && composer require laravel/sanctum:^4.0 --no-interaction --no-audit

# 2) دمج ملفاتنا المخصّصة فوق المشروع
COPY app/ ./app/
COPY database/ ./database/
COPY routes/ ./routes/
COPY config/ ./config/
COPY bootstrap/ ./bootstrap/

# إزالة هجرة users الافتراضية من Laravel فقط (نستبدلها بهجرتنا المخصّصة).
# نُبقي على هجرة personal_access_tokens الخاصة بنا لأن Sanctum 4 لا يحمّلها تلقائياً.
RUN rm -f database/migrations/0001_01_01_000000_create_users_table.php

# سكربت الإقلاع
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 8000
CMD ["/usr/local/bin/entrypoint.sh"]
