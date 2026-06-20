FROM php:8.5-cli@sha256:1954ff5cd21f222c992b79d25e403b2600cec829678d5bb7076883f3a44c0d6e AS builder

# hadolint ignore=DL3008
RUN apt-get update && \
    apt-get install --no-install-recommends -y libzip-dev && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install zip

# Install composer.
# @see https://getcomposer.org/download
# renovate: datasource=github-releases depName=composer/composer extractVersion=^(?<version>.*)$
ENV COMPOSER_ALLOW_SUPERUSER=1
# hadolint ignore=DL4006
RUN version=2.8.10 && \
    curl -sS https://getcomposer.org/download/${version}/composer.phar.sha256sum | awk '{ print $1, "composer.phar" }' > composer.phar.sha256sum && \
    curl -sS -o composer.phar https://getcomposer.org/download/${version}/composer.phar && \
    sha256sum -c composer.phar.sha256sum && \
    chmod +x composer.phar && \
    mv composer.phar /usr/local/bin/composer && \
    rm composer.phar.sha256sum && \
    composer --version && \
    composer clear-cache

WORKDIR /app

COPY composer.json composer.lock /app/

RUN COMPOSER_MEMORY_LIMIT=-1 composer install -n --ansi --prefer-dist --optimize-autoloader

COPY . /app

RUN composer build

FROM php:8.5-cli@sha256:1954ff5cd21f222c992b79d25e403b2600cec829678d5bb7076883f3a44c0d6e

# git is required because the tool shells out to the git binary; openssh-client
# enables pushing to SSH remotes such as git@github.com:org/repo.git.
# hadolint ignore=DL3008
RUN apt-get update && \
    apt-get install --no-install-recommends -y git openssh-client && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# The image operates on a repository bind-mounted from the host, which is owned
# by an arbitrary host user while the container runs as root, so git must be
# told to trust it regardless of ownership.
RUN git config --system --add safe.directory '*'

WORKDIR /app

COPY --from=builder /app/.build/git-artifact /usr/local/bin/git-artifact

RUN chmod +x /usr/local/bin/git-artifact

ENTRYPOINT ["/usr/local/bin/git-artifact"]
