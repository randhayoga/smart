# ------------------------------------------------------------------------------
# Stage 1: Base Runtime with PHP Extensions & SQL Server ODBC
# ------------------------------------------------------------------------------
FROM dunglas/frankenphp:1.12.1-php8.4 AS base

WORKDIR /var/www/smart

# Install system dependencies & Microsoft ODBC Driver 18 for SQL Server
RUN apt-get update && apt-get install -y --no-install-recommends \
    libxml2-dev \
    libmcrypt-dev \
    gnupg \
    apt-transport-https \
    curl \
    unzip \
    libgmp-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd gmp zip opcache \
    && curl -sSL -O https://packages.microsoft.com/config/debian/$(grep VERSION_ID /etc/os-release | cut -d '"' -f 2 | cut -d '.' -f 1)/packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 unixodbc unixodbc-dev libgssapi-krb5-2 \
    && pecl install sqlsrv-5.13.0 pdo_sqlsrv-5.13.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apt-get purge -y unixodbc-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------------------------
# Stage 2: Development Stage (Preserves current hot-reload workflow)
# ------------------------------------------------------------------------------
FROM base AS dev

COPY docker/docker-php-size.ini /usr/local/etc/php/conf.d/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN curl -fsSL https://fnm.vercel.app/install | bash -s -- --install-dir /root/.fnm \
    && /root/.fnm/fnm install 24 \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/node /usr/local/bin/node \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/npm /usr/local/bin/npm \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/npx /usr/local/bin/npx

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 5173
ENTRYPOINT ["entrypoint.sh"]

# ------------------------------------------------------------------------------
# Stage 3: Node Builder (Compiles frontend assets)
# ------------------------------------------------------------------------------
FROM node:24-alpine AS node-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ ./resources/
COPY public/ ./public/
COPY tsconfig.json vite.config.js tailwind.config.js components.json ./
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 4: Composer Builder (Installs production PHP dependencies)
# ------------------------------------------------------------------------------
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# ------------------------------------------------------------------------------
# Stage 5: Production Runtime Stage
# ------------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false \
    OCTANE_SERVER=frankenphp

# Copy production PHP configuration
COPY docker/php.prod.ini /usr/local/etc/php/conf.d/php.prod.ini
COPY docker/docker-php-size.ini /usr/local/etc/php/conf.d/docker-php-size.ini

# Copy application source code
COPY . /var/www/smart

# Copy built vendor and frontend assets from builder stages
COPY --from=composer-builder /app/vendor /var/www/smart/vendor
COPY --from=node-builder /app/public/build /var/www/smart/public/build

# Setup entrypoint script
COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh

# Set correct permissions for storage and bootstrap cache
RUN chown -R www-data:www-data /var/www/smart/storage /var/www/smart/bootstrap/cache \
    && chmod -R 775 /var/www/smart/storage /var/www/smart/bootstrap/cache

# Production ports (HTTP, HTTPS, HTTP/3 QUIC)
EXPOSE 80 443 443/udp

ENTRYPOINT ["entrypoint.prod.sh"]