#!/usr/bin/env sh
# Creates a locally trusted certificate for the project's own Traefik
# ("standalone" profile). Requires mkcert.
#
# Usage:  ./docker/traefik/make-cert.sh songwunsch.localhost

set -e

if ! command -v mkcert >/dev/null 2>&1; then
    echo "mkcert is missing -- install it via apt, brew or your package manager."
    exit 1
fi

DOMAIN="${1:-songwunsch.localhost}"
DIR="$(dirname "$0")/certs"

mkdir -p "$DIR"
mkcert -install
mkcert -cert-file "$DIR/$DOMAIN.crt" -key-file "$DIR/$DOMAIN.key" "$DOMAIN" "*.$DOMAIN"

cat > "$(dirname "$0")/dynamic/tls.yml" <<YML
tls:
  certificates:
    - certFile: /etc/traefik/certs/$DOMAIN.crt
      keyFile: /etc/traefik/certs/$DOMAIN.key
YML

echo "Certificate for $DOMAIN created. Traefik picks it up automatically."
