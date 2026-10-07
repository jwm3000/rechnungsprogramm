#!/usr/bin/env bash
# Lokaler Testserver: http://localhost:8090/rechnungen/  (PHP 8.3 über Docker)
DIR="$(cd "$(dirname "$0")/.." && pwd)"
exec docker run --rm -it --name rechnungen-dev --user "$(id -u):$(id -g)" -p 8090:8090 -v "$DIR":/app -w /app php:8.3-cli \
	php -S 0.0.0.0:8090 -t /app bin/router.php
