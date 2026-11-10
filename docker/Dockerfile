FROM dunglas/frankenphp:1-php8.4

# Set the working directory in the container
WORKDIR /var/www/smart

# Install minimal system dependencies & PHP extensions via install-php-extensions
# install-php-extensions automatically downloads Microsoft ODBC Driver 18 for SQL Server,
# compiles sqlsrv & pdo_sqlsrv extensions, and purges build-time dev packages.
RUN apt-get update && apt-get install -y --no-install-recommends \
    gnupg2 \
    curl \
    ca-certificates \
    unzip \
    && install-php-extensions \
    pdo_sqlsrv \
    sqlsrv \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    gmp \
    zip \
    intl \
    opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP settings and install Composer
COPY docker/docker-php-size.ini /usr/local/etc/php/conf.d
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install fnm and Node.js for frontend asset tooling
RUN curl -fsSL https://fnm.vercel.app/install | bash -s -- --install-dir /root/.fnm \
    && /root/.fnm/fnm install 24 \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/node /usr/local/bin/node \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/npm /usr/local/bin/npm \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/npx /usr/local/bin/npx

# Copy and set the entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose ports: 80 (FrankenPHP / HTTP), 5173 (Vite dev server)
EXPOSE 80 5173

# Container startup entrypoint
ENTRYPOINT ["entrypoint.sh"]