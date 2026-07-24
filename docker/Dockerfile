# WordPress + Xdebug for local step-debugging of the plugin's PHP.
#
# Extends the pinned official image (keep this base in sync with the "Tested up
# to" header and the wpcli image in docker-compose.yml). Xdebug connects out to
# the IDE on port 9003; see docker/xdebug.ini and .vscode/launch.json.
FROM wordpress:7.0.2-php8.5-apache

# Build Xdebug from PECL, then drop the compile toolchain to keep the image lean.
# PHP 8.5 needs Xdebug >= 3.5; pecl pulls the latest stable. Pin the exact
# version here (e.g. xdebug-3.5.0) once confirmed, to match the repo's other pins.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
	pecl install xdebug; \
	docker-php-ext-enable xdebug; \
	apt-get purge -y --auto-remove $PHPIZE_DEPS; \
	rm -rf /var/lib/apt/lists/*

COPY docker/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
