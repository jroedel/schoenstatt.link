#!/usr/bin/env bash
#
# `make secrets-init` (tools/secrets-init.sh): the one-time conversion of .deploy.local into
# secrets.env. Run once per machine, which is exactly why it is tested — a conversion that
# drops a value is found at the next deploy, not when it runs.
#
# What must hold: every value .deploy.local set arrives intact (quotes included); the CI
# key arrives as base64 of the key; the host key is pinned only where it matches what
# known_hosts already trusts, and a scan that matches nothing is REFUSED; the result is
# mode 0600 and parses; an existing secrets.env is never overwritten.
#
# ssh-keyscan is a stub that answers with a key of the test's choosing. Plain bash and
# ssh-keygen; no network.
set -uo pipefail

cd "$(dirname "$0")/../.."
# shellcheck source=tools/secrets-env.sh
. tools/secrets-env.sh

FAILED=0 PASSED=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"; PASSED=$((PASSED + 1))
    else
        printf '  FAIL  %s\n' "$2"
        [ $# -ge 3 ] && printf '%s\n' "$3" | sed 's/^/        /' | head -15
        FAILED=$((FAILED + 1))
    fi
}

if ! command -v ssh-keygen >/dev/null; then
    echo "secrets-init: SKIPPED — no ssh-keygen here"
    exit 0
fi

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/bin"
export SCAN_REPLY="$WORK/scan"

ssh-keygen -q -t ed25519 -N '' -C ci -f "$WORK/ci-key"
ssh-keygen -q -t ed25519 -N '' -C host -f "$WORK/host-key"
ssh-keygen -q -t ed25519 -N '' -C impostor -f "$WORK/impostor-key"
HOST_LINE="[stub.invalid]:222 $(cut -d' ' -f1,2 "$WORK/host-key.pub")"
printf '%s\n' "$HOST_LINE" > "$WORK/known_hosts"
printf '#!/usr/bin/env bash\ncat "$SCAN_REPLY"\n' > "$WORK/bin/ssh-keyscan"
chmod +x "$WORK/bin/ssh-keyscan"

(
    umask 077
    cat > "$WORK/deploy.local" <<'EOF'
DEPLOY_SSH_USER=stubuser
DEPLOY_SSH_HOST=stub.invalid
DEPLOY_SSH_PORT=222
DEPLOY_APP_PATH=public_html/stub
DEPLOY_BASE_URL=https://stub.invalid
DEPLOY_API_KEY=maint-SECRET-1111
DEPLOY_DB_USER=ddl
DEPLOY_DB_PASS='pa"ss-SECRET-2222'
DEPLOY_KEEP_RELEASES=7
DEPLOY_RETIRED_NAME=gone
EOF
)

run() {   # $1 the secrets.env to write
    PATH="$WORK/bin:$PATH" SECRETS_ENV="$1" DEPLOY_CI_KEY="$WORK/ci-key" \
        SECRETS_INIT_KNOWN_HOSTS="$WORK/known_hosts" bash tools/secrets-init.sh "$WORK/deploy.local" < /dev/null 2>&1
}
value() {   # $1 file, $2 name
    env -i PATH="$PATH" bash -c '. tools/secrets-env.sh; secrets_env_load "$1" && printf "%s" "${!2}"' _ "$1" "$2"
}

# 1. The conversion.
printf '%s\n' "$HOST_LINE" > "$SCAN_REPLY"
OUT=$(run "$WORK/s1.env"); RC=$?
[ "$RC" = 0 ] && [ -f "$WORK/s1.env" ] && check 0 "secrets.env is written" || check 1 "secrets.env is written" "$OUT"
[ "$(stat -c %a "$WORK/s1.env")" = 600 ] && check 0 "at mode 0600" || check 1 "at mode 0600"
[ "$(value "$WORK/s1.env" DEPLOY_DB_PASS)" = 'pa"ss-SECRET-2222' ] && [ "$(value "$WORK/s1.env" DEPLOY_SSH_USER)" = stubuser ] \
    && [ "$(value "$WORK/s1.env" DEPLOY_KEEP_RELEASES)" = 7 ] \
    && check 0 "every value arrives intact" || check 1 "every value arrives intact" "$(value "$WORK/s1.env" DEPLOY_DB_PASS)"
[ "$(value "$WORK/s1.env" DEPLOY_CI_SSH_KEY_B64 | base64 -d)" = "$(cat "$WORK/ci-key")" ] \
    && check 0 "DEPLOY_CI_SSH_KEY_B64 decodes to the CI key" || check 1 "DEPLOY_CI_SSH_KEY_B64 decodes to the CI key"
[ "$(value "$WORK/s1.env" DEPLOY_KNOWN_HOSTS_B64 | base64 -d)" = "$HOST_LINE" ] \
    && check 0 "DEPLOY_KNOWN_HOSTS_B64 is the trusted host key" || check 1 "DEPLOY_KNOWN_HOSTS_B64 is the trusted host key"
[ "$(value "$WORK/s1.env" APP_JWT_KEY)" = TODO ] && check 0 "the APP_* section stays as the template's TODOs" \
    || check 1 "the APP_* section stays as the template's TODOs"
[[ $OUT == *'DEPLOY_RETIRED_NAME is in'* ]] && check 0 "a name the template lacks is named, not silently dropped" \
    || check 1 "a name the template lacks is named, not silently dropped" "$OUT"
[[ $OUT != *SECRET-* ]] && check 0 "no value is printed" || check 1 "no value is printed" "$OUT"
grep -q '^# --- the CI runner' "$WORK/s1.env" && check 0 "the template's comments are kept" \
    || check 1 "the template's comments are kept"

# 2. Never over an existing file.
BEFORE=$(sha256sum < "$WORK/s1.env")
OUT=$(run "$WORK/s1.env"); RC=$?
[ "$RC" != 0 ] && [ "$(sha256sum < "$WORK/s1.env")" = "$BEFORE" ] && check 0 "an existing secrets.env is never overwritten" \
    || check 1 "an existing secrets.env is never overwritten" "$OUT"

# 3. A scan that matches nothing trusted is refused, and nothing is written.
printf '[stub.invalid]:222 %s\n' "$(cut -d' ' -f1,2 "$WORK/impostor-key.pub")" > "$SCAN_REPLY"
OUT=$(run "$WORK/s3.env"); RC=$?
[ "$RC" != 0 ] && [ ! -e "$WORK/s3.env" ] && [[ $OUT == *'matches'* ]] \
    && check 0 "an unrecognised host key is refused and nothing is written" \
    || check 1 "an unrecognised host key is refused and nothing is written" "$OUT"

# 4. Mixed: only the trusted line is pinned.
{ printf '%s\n' "$HOST_LINE"; printf '[stub.invalid]:222 %s\n' "$(cut -d' ' -f1,2 "$WORK/impostor-key.pub")"; } > "$SCAN_REPLY"
OUT=$(run "$WORK/s4.env"); RC=$?
[ "$RC" = 0 ] && [ "$(value "$WORK/s4.env" DEPLOY_KNOWN_HOSTS_B64 | base64 -d)" = "$HOST_LINE" ] \
    && check 0 "of two scanned keys, only the trusted one is pinned" \
    || check 1 "of two scanned keys, only the trusted one is pinned" "$OUT"

if [ "$FAILED" = 0 ]; then
    echo "secrets-init: $PASSED check(s) passed"
    exit 0
fi
echo "secrets-init: $FAILED check(s) failed" >&2
exit 1
