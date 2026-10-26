#!/usr/bin/env bash
#
# `make gh-secrets` (tools/gh-secrets.sh): the deploy section of secrets.env, as the ONE
# GitHub secret the deploy workflow reads. `gh` is a stub that keeps what it is sent; the
# server check's `ssh` is a stub that refuses. Nothing leaves this machine.
#
# What must hold:
#
#   - the bundle carries the deploy section and the two keys, and NEVER an APP_* name,
#     DEPLOY_API_KEY_PREVIOUS or a retention knob;
#   - the runner's parser reads it back to exactly the values in secrets.env, quotes and all;
#   - no value is in any argument to gh;
#   - a placeholder, a missing required name or a key with a passphrase sends nothing;
#   - --delete-legacy refuses until the bundle exists, then deletes only what is there;
#   - deploy.yml reads the secret this writes, and decodes the names this bundles.
#
# Plain bash and ssh-keygen; no php, no network.
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
    echo "gh-secrets: SKIPPED — no ssh-keygen here"
    exit 0
fi

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/bin" "$WORK/gh"
export GH_DIR="$WORK/gh"

cat > "$WORK/bin/gh" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$GH_DIR/argv"
case "$1 $2" in
    'auth status') exit 0 ;;
    'secret set')  cat > "$GH_DIR/set-$3" ;;
    'secret list') cat "$GH_DIR/list" 2>/dev/null ;;
    'secret delete') printf '%s\n' "$3" >> "$GH_DIR/deleted" ;;
esac
EOF
printf '#!/usr/bin/env bash\nexit 255\n' > "$WORK/bin/ssh"
chmod +x "$WORK/bin/gh" "$WORK/bin/ssh"

ssh-keygen -q -t ed25519 -N '' -C test -f "$WORK/ci-key"
ssh-keygen -q -t ed25519 -N 'a passphrase' -C test -f "$WORK/locked-key"
ssh-keygen -q -t ed25519 -N '' -C host -f "$WORK/host-key"
printf '[stub.invalid]:222 %s\n' "$(cut -d' ' -f1,2 "$WORK/host-key.pub")" > "$WORK/known_hosts"
CI_B64=$(base64 < "$WORK/ci-key" | tr -d '\n')
KH_B64=$(base64 < "$WORK/known_hosts" | tr -d '\n')

write_secrets() {   # extra lines as arguments, appended (a later line wins)
    (
        umask 077
        exec > "$WORK/secrets.env"
        cat <<EOF
DEPLOY_SSH_USER=stub
DEPLOY_SSH_HOST=stub.invalid
DEPLOY_SSH_PORT=222
DEPLOY_APP_PATH=app
DEPLOY_BASE_URL=https://stub.invalid
DEPLOY_API_KEY=maint-key-SECRET-1111
DEPLOY_API_KEY_PREVIOUS=maint-key-OLD-0000
DEPLOY_CANARY_COOKIE=
DEPLOY_DB_NAME=ourlink_db1
DEPLOY_DB_USER=ddl
DEPLOY_DB_PASS="pa'ss-SECRET-2222"
DEPLOY_DB_TUNNEL_PORT=13306
DEPLOY_KEEP_RELEASES=5
DEPLOY_CI_SSH_KEY_B64=$CI_B64
DEPLOY_KNOWN_HOSTS_B64=$KH_B64
APP_DB_PASSWORD=app-db-SECRET-3333
APP_JWT_KEY=jwt-SECRET-4444
EOF
        printf '%s\n' "$@"
    )
}
run() {
    rm -f "$GH_DIR"/set-* "$GH_DIR/argv"
    PATH="$WORK/bin:$PATH" SECRETS_ENV="$WORK/secrets.env" bash tools/gh-secrets.sh "$@" < /dev/null 2>&1
}

# 1. The bundle.
write_secrets
OUT=$(run); RC=$?
BUNDLE="$GH_DIR/set-DEPLOY_SECRETS_ENV"
[ "$RC" = 0 ] && [ -s "$BUNDLE" ] && check 0 "one secret, DEPLOY_SECRETS_ENV, is set" || check 1 "one secret, DEPLOY_SECRETS_ENV, is set" "$OUT"
[ "$(ls "$GH_DIR" | grep -c '^set-')" = 1 ] && check 0 "and no other" || check 1 "and no other" "$(ls "$GH_DIR")"
NAMES=$(grep -oE '^[A-Z][A-Z0-9_]*=' "$BUNDLE" | tr -d '=' | sort | tr '\n' ' ')
for n in DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_SSH_PORT DEPLOY_APP_PATH DEPLOY_BASE_URL DEPLOY_API_KEY \
         DEPLOY_DB_NAME DEPLOY_DB_USER DEPLOY_DB_PASS DEPLOY_DB_TUNNEL_PORT \
         DEPLOY_CI_SSH_KEY_B64 DEPLOY_KNOWN_HOSTS_B64; do
    [[ " $NAMES" == *" $n "* ]] && check 0 "the bundle has $n" || check 1 "the bundle has $n" "$NAMES"
done
for n in APP_DB_PASSWORD APP_JWT_KEY DEPLOY_API_KEY_PREVIOUS DEPLOY_KEEP_RELEASES DEPLOY_CANARY_COOKIE; do
    [[ " $NAMES" != *" $n "* ]] && check 0 "the bundle leaves out $n" || check 1 "the bundle leaves out $n" "$NAMES"
done
grep -qE 'SECRET-3333|SECRET-4444|OLD-0000' "$BUNDLE" \
    && check 1 "no APP_* or previous-key VALUE is in it either" || check 0 "no APP_* or previous-key VALUE is in it either"

# 2. What the runner's parser makes of it.
chmod 600 "$BUNDLE"
GOT=$(env -i PATH="$PATH" bash -c '. tools/secrets-env.sh; secrets_env_load "$1" &&
    printf "%s|%s|%s" "$DEPLOY_DB_PASS" "$DEPLOY_API_KEY" "$DEPLOY_SSH_PORT"' _ "$BUNDLE")
[ "$GOT" = "pa'ss-SECRET-2222|maint-key-SECRET-1111|222" ] \
    && check 0 "the runner's parser reads the values back exactly" \
    || check 1 "the runner's parser reads the values back exactly" "$GOT"
DECODED=$(env -i PATH="$PATH" bash -c '. tools/secrets-env.sh; secrets_env_load "$1" &&
    printf "%s" "$DEPLOY_CI_SSH_KEY_B64" | base64 -d' _ "$BUNDLE")
[ "$DECODED" = "$(cat "$WORK/ci-key")" ] && check 0 "the CI key decodes to the key" || check 1 "the CI key decodes to the key"

# 3. No value in any gh argument.
LEAK=$(grep -E 'SECRET-[0-9]|OLD-0000' "$GH_DIR/argv")
[ -z "$LEAK" ] && check 0 "no value is in gh's arguments" || check 1 "no value is in gh's arguments" "$LEAK"
[[ $OUT != *SECRET-[0-9]* ]] && check 0 "no value is printed" || check 1 "no value is printed" "$OUT"

# 4. Refusals that send nothing.
refused() {   # $1 name of the check, $2 expected text, rest: extra secrets lines
    local name=$1 want=$2; shift 2
    write_secrets "$@"
    OUT=$(run); RC=$?
    if [ "$RC" != 0 ] && [[ $OUT == *"$want"* ]] && [ ! -e "$BUNDLE" ]; then
        check 0 "$name"
    else
        check 1 "$name" "$OUT"
    fi
}
refused "a TODO is refused and nothing is sent" "DEPLOY_DB_USER still contains a TODO" DEPLOY_DB_USER=TODO
refused "a missing required name is refused" "DEPLOY_API_KEY is not set" DEPLOY_API_KEY=
refused "a CI key with a passphrase is refused" "passphrase-less" \
    "DEPLOY_CI_SSH_KEY_B64=$(base64 < "$WORK/locked-key" | tr -d '\n')"
refused "a host key that is not known_hosts lines is refused" "known_hosts" \
    "DEPLOY_KNOWN_HOSTS_B64=$(printf 'not a host key' | base64)"
refused "a value with both quote characters is refused" "both quote characters" \
    "DEPLOY_DB_PASS=\"it's \"both\"\""

# 5. --delete-legacy.
: > "$GH_DIR/list"
printf 'DEPLOY_SSH_USER\nDEPLOY_KNOWN_HOSTS\n' > "$GH_DIR/list"
rm -f "$GH_DIR/deleted"
OUT=$(run --delete-legacy); RC=$?
[ "$RC" != 0 ] && [ ! -e "$GH_DIR/deleted" ] \
    && check 0 "--delete-legacy refuses while DEPLOY_SECRETS_ENV is not set" \
    || check 1 "--delete-legacy refuses while DEPLOY_SECRETS_ENV is not set" "$OUT"
printf 'DEPLOY_SECRETS_ENV\nDEPLOY_SSH_USER\nDEPLOY_KNOWN_HOSTS\nSOMETHING_ELSE\n' > "$GH_DIR/list"
OUT=$(run --delete-legacy); RC=$?
[ "$RC" = 0 ] && [ "$(sort "$GH_DIR/deleted" | tr '\n' ' ')" = "DEPLOY_KNOWN_HOSTS DEPLOY_SSH_USER " ] \
    && check 0 "then it deletes exactly the per-field secrets that are there" \
    || check 1 "then it deletes exactly the per-field secrets that are there" "$OUT $(cat "$GH_DIR/deleted" 2>/dev/null)"

# 6. The workflow reads what this writes.
DEPLOY=.github/workflows/deploy.yml
grep -q 'secrets.DEPLOY_SECRETS_ENV' "$DEPLOY" \
    && grep -qx 'BUNDLE=DEPLOY_SECRETS_ENV' tools/gh-secrets.sh \
    && check 0 "deploy.yml reads the secret gh-secrets.sh writes" \
    || check 1 "deploy.yml reads the secret gh-secrets.sh writes"
for n in DEPLOY_KNOWN_HOSTS_B64 DEPLOY_CI_SSH_KEY_B64; do
    grep -q "$n" "$DEPLOY" && check 0 "deploy.yml decodes $n" || check 1 "deploy.yml decodes $n"
done
grep -nE 'secrets\.DEPLOY_[A-Z_]+' "$DEPLOY" | grep -v 'secrets.DEPLOY_SECRETS_ENV' > "$WORK/stray"
[ ! -s "$WORK/stray" ] && check 0 "deploy.yml reads no per-field secret" \
    || check 1 "deploy.yml reads no per-field secret" "$(cat "$WORK/stray")"

# 7. The workflow's own step, run on the bundle: the step that writes the configuration and
#    the SSH trust on a runner, extracted from deploy.yml so the test runs what CI runs.
#    HOME and RUNNER_TEMP are scratch directories here.
STEP=$(awk '
    /- name: Write the deploy configuration/ { found = 1 }
    found && /^ *run: \|/ { indent = index($0, "run:"); inrun = 1; next }
    inrun { if ($0 ~ /^ *$/) { print ""; next }
            match($0, /^ */); if (RLENGTH < indent) exit; print substr($0, indent + 2) }
' "$DEPLOY")
[ -n "$STEP" ] || check 1 "the configuration step was found in deploy.yml"
mkdir -p "$WORK/runner-home" "$WORK/runner-temp"
write_secrets
run > /dev/null
OUT=$(HOME="$WORK/runner-home" RUNNER_TEMP="$WORK/runner-temp" DEPLOY_SECRETS_ENV="$(cat "$BUNDLE")" \
      bash -e -o pipefail -c "$STEP" 2>&1); RC=$?
[ "$RC" = 0 ] && check 0 "deploy.yml's step runs on the bundle" || check 1 "deploy.yml's step runs on the bundle" "$OUT"
cmp -s "$WORK/runner-home/.ssh/id_deploy" "$WORK/ci-key" && check 0 "it installs the CI key" \
    || check 1 "it installs the CI key"
cmp -s "$WORK/runner-home/.ssh/known_hosts" "$WORK/known_hosts" && check 0 "it pins the host key" \
    || check 1 "it pins the host key"
[ "$(stat -c %a "$WORK/runner-temp/secrets.env")" = 600 ] && check 0 "the runner's secrets.env is mode 0600" \
    || check 1 "the runner's secrets.env is mode 0600"
[[ $OUT == *DEPLOY_DB_PASS* ]] && [[ $OUT != *SECRET-[0-9]* ]] \
    && check 0 "its log names the fields and prints no value" || check 1 "its log names the fields and prints no value" "$OUT"
OUT=$(HOME="$WORK/runner-home" RUNNER_TEMP="$WORK/runner-temp" DEPLOY_SECRETS_ENV='' \
      bash -e -o pipefail -c "$STEP" 2>&1); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'make gh-secrets'* ]] && check 0 "an empty secret fails the step, saying what to run" \
    || check 1 "an empty secret fails the step, saying what to run" "$OUT"

if [ "$FAILED" = 0 ]; then
    echo "gh-secrets: $PASSED check(s) passed"
    exit 0
fi
echo "gh-secrets: $FAILED check(s) failed" >&2
exit 1
