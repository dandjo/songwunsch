#!/bin/sh
# Deploy Songwunsch to the web host via rsync over SSH.
#
#   tools/deploy.sh            # sync, then raise 'version' in the server's config.php
#   tools/deploy.sh -n         # dry run: show what would change, touch nothing
#   tools/deploy.sh --no-bump  # sync without touching the version
#
# Copies everything the application needs to run and leaves out what belongs
# to development only: config.php (lives only on the server), the Docker
# stack, environment files, all git metadata, editor files, this script.
#
# --delete removes files on the server that no longer exist locally, so old
# templates or an index.htm in the target do not linger. config.php is
# excluded and therefore never deleted.
#
# After the sync the 'version' entry of the server's config.php is raised so
# browsers fetch the new style.css and app.js (cache buster, see README
# "Version und Cache"): a numeric version like 1.0.4 becomes 1.0.5, anything
# else becomes today's date with a counter (2026-09-03.1).
#
# Target host and directory come from the project's .env (DEPLOY_HOST, an SSH
# host or alias, and DEPLOY_DIR, default public_html) -- see sample.env. A
# variable set in the environment wins over the .env:
#
#   DEPLOY_HOST=other-host DEPLOY_DIR=www tools/deploy.sh
set -eu

SRC="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$SRC/.env"

# Value of KEY from the .env: last assignment wins, surrounding quotes are
# dropped. The file is read, not sourced, so nothing in it gets executed.
env_value() {
    [ -f "$ENV_FILE" ] || return 0
    sed -n "s/^[[:space:]]*$1=//p" "$ENV_FILE" | tail -n 1 \
        | sed -e "s/^'\(.*\)'\$/\1/" -e 's/^"\(.*\)"$/\1/'
}

HOST="${DEPLOY_HOST:-$(env_value DEPLOY_HOST)}"
DIR="${DEPLOY_DIR:-$(env_value DEPLOY_DIR)}"
DIR="${DIR:-public_html}"

if [ -z "$HOST" ]; then
    echo "DEPLOY_HOST is not set: add it to $ENV_FILE (see sample.env) or pass it in the environment." >&2
    exit 2
fi

DRY=""
BUMP=1
for arg in "$@"; do
    case "$arg" in
        -n|--dry-run) DRY="--dry-run" ;;
        --no-bump)    BUMP=0 ;;
        *) echo "unknown option: $arg" >&2; exit 2 ;;
    esac
done

command -v rsync >/dev/null || { echo "rsync is not installed" >&2; exit 1; }

echo "Syncing $SRC/ -> $HOST:$DIR/ ${DRY:+(dry run)}"

# shellcheck disable=SC2086  # $DRY is intentionally empty or one flag
rsync -rlptzv --delete $DRY \
    --chmod=D755,F644 \
    --exclude='/config.php' \
    --exclude='/.env' \
    --exclude='/sample.env' \
    --exclude='/compose.yml' \
    --exclude='/docker/' \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.gitmodules' \
    --exclude='.gitkeep' \
    --exclude='/.github/' \
    --exclude='/.idea/' \
    --exclude='/tools/deploy.sh' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    "$SRC/" "$HOST:$DIR/"

# --- Cache buster: raise 'version' in the server's config.php ---------------
if [ "$BUMP" -eq 1 ]; then
    CONFIG="$DIR/config.php"
    # The last quoted value on the 'version' line -- works for
    #   'version' => $env('APP_VERSION', '1.0.0'),   and   'version' => '1.0.0',
    CURRENT="$(ssh "$HOST" "test -f '$CONFIG' && sed -n \"/'version'/{s/.*'\\([^']*\\)'[^']*\$/\\1/p;q}\" '$CONFIG'" || true)"

    if [ -z "$CURRENT" ]; then
        echo "Note: no 'version' entry found in $HOST:$CONFIG -- nothing to raise." >&2
        echo "      Copy config.example.php there (it contains the entry) to get the cache buster." >&2
    else
        case "$CURRENT" in
            *[!0-9.]*|.*|*.|"")
                # Not purely numeric: date plus counter, e.g. 2026-09-03.1 -> 2026-09-03.2
                TODAY="$(date +%Y-%m-%d)"
                case "$CURRENT" in
                    "$TODAY".*) NEXT="$TODAY.$(( ${CURRENT##*.} + 1 ))" ;;
                    *)          NEXT="$TODAY.1" ;;
                esac
                ;;
            *)
                # Numeric like 1.0.4: raise the last component.
                LAST="${CURRENT##*.}"
                HEAD="${CURRENT%"$LAST"}"
                NEXT="$HEAD$(( LAST + 1 ))"
                ;;
        esac

        if [ -n "$DRY" ]; then
            echo "Version: $CURRENT -> $NEXT (dry run, not written)"
        else
            ssh "$HOST" "sed -i \"/'version'/s/'$CURRENT'\\([^']*\\)\$/'$NEXT'\\1/\" '$CONFIG'"
            echo "Version: $CURRENT -> $NEXT"
        fi
    fi
fi

if [ -z "$DRY" ]; then
    echo "Done. Remember: config.php is not deployed -- on a fresh host copy"
    echo "config.example.php there and set base_path to '' when the files sit"
    echo "directly in $DIR."
fi
