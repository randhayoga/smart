FROM dunglas/frankenphp:1.12.1-php8.4

# Set the working directory in the container
WORKDIR /var/www/smart

# Install any dependencies needed by your PHP application
# You can add additional packages as required
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libxml2-dev \
    libmcrypt-dev \
    gnupg \
    apt-transport-https \
    curl \
    unzip \
    build-essential \
    libgmp-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd gmp zip

RUN curl -sSL -O https://packages.microsoft.com/config/debian/$(grep VERSION_ID /etc/os-release | cut -d '"' -f 2 | cut -d '.' -f 1)/packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && apt-get install -y unixodbc unixodbc-dev \
    && apt-get install -y libgssapi-krb5-2 \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_pgsql \
    && pecl install sqlsrv-5.13.0 \
    && pecl install pdo_sqlsrv-5.13.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

COPY docker/docker-php-size.ini /usr/local/etc/php/conf.d
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install fnm and Node.js
RUN curl -fsSL https://fnm.vercel.app/install | bash -s -- --install-dir /root/.fnm \
    && /root/.fnm/fnm install 24 \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/node /usr/local/bin/node \
    && ln -s /root/.fnm/node-versions/v24.*/installation/bin/npm /usr/local/bin/npm

# Copy and set the entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose the port on which Laravel and Vite will run
EXPOSE 8000

# Use entrypoint to install deps before starting Octane
ENTRYPOINT ["entrypoint.sh"]