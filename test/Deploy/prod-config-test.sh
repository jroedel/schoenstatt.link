#!/usr/bin/env bash
#
# `make prod-config`, `-diff` and `-import` (tools/prod-config.sh and
# tools/prod-config-remote.php), against a stand-in server: `ssh` is a stub that runs the
# remote command in a scratch home with the real php, and `curl` a stub that answers the
# live-site check. Nothing leaves this machine.
#
# What must hold, because production's whole configuration goes through it:
#
#   - the diff names key paths and prints no value — not a password, not a key;
#   - no value is ever in a process's arguments (the ssh stub logs every argv);
#   - install keeps local.php.prev, leaves mode 0600, and the file reads back as rendered;
#   - a database move is refused without ALLOW_DB_CHANGE=1, and a placeholder always;
#   - install refuses without a terminal unless told, and names the restore when the live
#     site rejects the key afterwards;
#   - import gives back exactly the values install wrote.
#
# Needs php, which the Deploy job in CI has. On a host without it this reports SKIPPED;
# run it in the capsule: docker compose exec -T app bash test/Deploy/prod-config-test.sh
set -uo pipefail

cd "$(dirname "$0")/../.."
REPO=$(pwd)

if ! command -v php >/dev/null; then
    echo "prod-config: SKIPPED — no php here (run it in the capsule)"
    exit 0
fi

FAILED=0 PASSED=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"; PASSED=$((PASSED + 1))
    else
        printf '  FAIL  %s\n' "$2"
        [ $# -ge 3 ] && printf '%s\n' "$3" | sed 's/^/        /' | head -20
        FAILED=$((FAILED + 1))
    fi
}

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
export FAKE_HOME="$WORK/home" ARGV_LOG="$WORK/argv.log" CURL_STATUS_FILE="$WORK/curl-status"
mkdir -p "$FAKE_HOME/app/shared/config-autoload" "$FAKE_HOME/app/releases" "$WORK/bin"
TARGET="$FAKE_HOME/app/shared/config-autoload/local.php"
# The development template stands in for a hand-edited production file: the same shape,
# placeholder values, and a recaptcha block nothing reads.
cp config/autoload/local.php.dist "$TARGET"
echo 200 > "$CURL_STATUS_FILE"

cat > "$WORK/bin/ssh" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$ARGV_LOG"
case " $* " in *" -O exit "*) exit 0 ;; esac
cd "$FAKE_HOME" && exec bash -c "${@: -1}"
EOF
cat > "$WORK/bin/curl" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$ARGV_LOG"
cat > /dev/null
cat "$CURL_STATUS_FILE"
EOF
chmod +x "$WORK/bin/ssh" "$WORK/bin/curl"

# Values distinctive enough that finding one in output or argv is never a coincidence.
write_secrets() {   # extra NAME=value lines as arguments override the defaults
    (
        umask 077
        exec > "$WORK/secrets.env"
        cat <<EOF
DEPLOY_SSH_USER=stub
DEPLOY_SSH_HOST=stub.invalid
DEPLOY_APP_PATH=app
DEPLOY_BASE_URL=https://stub.invalid
DEPLOY_DB_PASS=ddl-pass-NEVER-SENT-77
DEPLOY_API_KEY=maint-key-SECRET-1111
APP_DB_HOST=localhost
APP_DB_NAME=xxxxxxxxxx
APP_DB_USER=appuser
APP_DB_PASSWORD=db-pass-SECRET-2222
APP_SMTP_HOST=mail.your-server.de
APP_SMTP_USER=webmaster@schoenstatt.link
APP_SMTP_PASSWORD=smtp-pass-SECRET-3333
APP_DRIVE_URL=https://script.google.com/macros/s/drive-SECRET-4444/exec
APP_DRIVE_KEY=drive-key-SECRET-5555
APP_LIBRARY_API_KEYS=lib-key-SECRET-6666, lib-key-SECRET-7777
APP_JWT_KEY=jwt-key-SECRET-8888
EOF
        printf '%s\n' "$@"
    )
}
SECRET_VALUES=(ddl-pass-NEVER-SENT-77 maint-key-SECRET-1111 db-pass-SECRET-2222 smtp-pass-SECRET-3333
               drive-SECRET-4444 drive-key-SECRET-5555 lib-key-SECRET-6666 lib-key-SECRET-7777 jwt-key-SECRET-8888)

run() {   # $1 mode; environment as given; stdin is never a terminal here
    PATH="$WORK/bin:$PATH" SECRETS_ENV="$WORK/secrets.env" PROD_CONFIG_REF=. PROD_CONFIG_SETTLE=0 \
        PROD_CONFIG_IMPORT="$WORK/import/secrets.imported.env" bash tools/prod-config.sh "$1" < /dev/null 2>&1
}
leaks() {   # $1 text; prints each secret value found in it
    local v
    for v in "${SECRET_VALUES[@]}"; do [[ $1 == *"$v"* ]] && printf '%s ' "$v"; done
}
config_json() {   # the installed file as PHP reads it
    php -r 'echo json_encode(require $argv[1]);' "$TARGET"
}

write_secrets
ORIGINAL=$(sha256sum < "$TARGET")

# 1. diff: key paths, statuses, no values.
OUT=$(run diff); RC=$?
[ "$RC" = 0 ] && check 0 "diff exits 0" || check 1 "diff exits 0" "$OUT"
for want in 'differs             db.password' 'only on the server  recaptcha.privKey' \
            'same                db.hostname' 'differs             schoenstatt.api_keys'; do
    [[ $OUT == *"$want"* ]] && check 0 "diff reports: $want" || check 1 "diff reports: $want" "$OUT"
done
L=$(leaks "$OUT"); [ -z "$L" ] && check 0 "diff prints no value" || check 1 "diff prints no value" "$L"
[ "$(sha256sum < "$TARGET")" = "$ORIGINAL" ] && check 0 "diff changes nothing" || check 1 "diff changes nothing"

# 2. install refuses without a terminal unless told.
OUT=$(run install); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'no terminal'* ]] && [ "$(sha256sum < "$TARGET")" = "$ORIGINAL" ] \
    && check 0 "install without a terminal is refused and changes nothing" \
    || check 1 "install without a terminal is refused and changes nothing" "$OUT"

# 3. install.
OUT=$(PROD_CONFIG_YES=1 run install); RC=$?
[ "$RC" = 0 ] && check 0 "install succeeds" || check 1 "install succeeds" "$OUT"
L=$(leaks "$OUT"); [ -z "$L" ] && check 0 "install prints no value" || check 1 "install prints no value" "$L"
[ "$(stat -c %a "$TARGET")" = 600 ] && check 0 "local.php is mode 0600" || check 1 "local.php is mode 0600" "$(stat -c %a "$TARGET")"
[ "$(sha256sum < "$TARGET.prev")" = "$ORIGINAL" ] && [ "$(stat -c %a "$TARGET.prev")" = 600 ] \
    && check 0 "local.php.prev is the replaced file, mode 0600" || check 1 "local.php.prev is the replaced file, mode 0600"
GOT=$(config_json)
EXPECT_PARTS=('"password":"db-pass-SECRET-2222"' '"api_keys":["maint-key-SECRET-1111"]'
              '"api_keys":["lib-key-SECRET-6666","lib-key-SECRET-7777"]' '"cypherKey":"jwt-key-SECRET-8888"'
              '"port":587' '"display_exceptions":false')
for part in "${EXPECT_PARTS[@]}"; do
    [[ $GOT == *"$part"* ]] && check 0 "the installed file holds $part" || check 1 "the installed file holds $part" "$GOT"
done
[[ $GOT != *recaptcha* ]] && check 0 "a key the template does not have is gone" || check 1 "a key the template does not have is gone"
[[ $GOT != *ddl-pass* ]] && check 0 "the DDL password is not in it" || check 1 "the DDL password is not in it"
L=$(leaks "$(cat "$WORK/argv.log")")
[ -z "$L" ] && check 0 "no value appeared in any ssh or curl argument" || check 1 "no value appeared in any ssh or curl argument" "$L"
grep -q 'cache-status' "$WORK/argv.log" && check 0 "the live site was asked about DEPLOY_API_KEY" \
    || check 1 "the live site was asked about DEPLOY_API_KEY"

# 4. Idempotent.
OUT=$(run diff)
[[ $OUT == *'0 key path(s) would change'* ]] && check 0 "a second diff finds nothing" || check 1 "a second diff finds nothing" "$OUT"
OUT=$(PROD_CONFIG_YES=1 run install)
[[ $OUT == *'already holds exactly'* ]] && check 0 "a second install does nothing" || check 1 "a second install does nothing" "$OUT"

# 5. A placeholder is refused, at diff already.
write_secrets APP_JWT_KEY=TODO
OUT=$(run diff); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'APP_JWT_KEY'*placeholder* ]] \
    && check 0 "a TODO value is refused, by name" || check 1 "a TODO value is refused, by name" "$OUT"
write_secrets APP_SMTP_PASSWORD=
OUT=$(run diff); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'does not set APP_SMTP_PASSWORD'* ]] \
    && check 0 "an empty value is refused, by name" || check 1 "an empty value is refused, by name" "$OUT"

# 6. Moving the database is refused without ALLOW_DB_CHANGE=1.
BEFORE=$(sha256sum < "$TARGET")
write_secrets APP_DB_NAME=another_db
OUT=$(PROD_CONFIG_YES=1 run install); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'db.database'* ]] && [ "$(sha256sum < "$TARGET")" = "$BEFORE" ] \
    && check 0 "a changed db.database is refused and nothing is written" \
    || check 1 "a changed db.database is refused and nothing is written" "$OUT"
OUT=$(ALLOW_DB_CHANGE=1 PROD_CONFIG_YES=1 run install); RC=$?
[ "$RC" = 0 ] && [[ $(config_json) == *'"database":"another_db"'* ]] \
    && check 0 "ALLOW_DB_CHANGE=1 lets it through" || check 1 "ALLOW_DB_CHANGE=1 lets it through" "$OUT"

# 7. A live site that rejects the key afterwards fails the run, naming the restore.
write_secrets APP_DB_NAME=another_db DEPLOY_API_KEY_PREVIOUS=maint-key-SECRET-old0
echo 401 > "$CURL_STATUS_FILE"
OUT=$(PROD_CONFIG_YES=1 run install); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'answers 401'* ]] && [[ $OUT == *"local.php.prev"* ]] \
    && check 0 "a 401 afterwards fails the run and names the restore" \
    || check 1 "a 401 afterwards fails the run and names the restore" "$OUT"
[[ $(config_json) == *'"api_keys":["maint-key-SECRET-1111","maint-key-SECRET-old0"]'* ]] \
    && check 0 "mid-rotation, the server accepts both keys" || check 1 "mid-rotation, the server accepts both keys" "$(config_json)"
echo 200 > "$CURL_STATUS_FILE"

# 8. import gives back what install wrote, and compares without printing. The list is
#    written without the space this time: import joins with a bare comma, and the
#    comparison is of the text.
write_secrets APP_DB_NAME=another_db APP_LIBRARY_API_KEYS=lib-key-SECRET-6666,lib-key-SECRET-7777
OUT=$(PROD_CONFIG_YES=1 run install); RC=$?
[ "$RC" = 0 ] || check 1 "the install before the import succeeds" "$OUT"
OUT=$(run import); RC=$?
IMPORTED="$WORK/import/secrets.imported.env"
[ "$RC" = 0 ] && [ -f "$IMPORTED" ] && [ "$(stat -c %a "$IMPORTED")" = 600 ] \
    && check 0 "import writes its file at mode 0600" || check 1 "import writes its file at mode 0600" "$OUT"
L=$(leaks "$OUT"); [ -z "$L" ] && check 0 "import prints no value" || check 1 "import prints no value" "$L"
[[ $OUT == *'same         APP_DB_PASSWORD'* ]] && [[ $OUT != *differs* ]] \
    && check 0 "every imported value matches secrets.env" || check 1 "every imported value matches secrets.env" "$OUT"
grep -qx "APP_LIBRARY_API_KEYS='lib-key-SECRET-6666,lib-key-SECRET-7777'" "$IMPORTED" \
    && check 0 "a list imports as one comma-separated value" || check 1 "a list imports as one comma-separated value" "$(cat "$IMPORTED")"
grep -qx "DEPLOY_API_KEY_PREVIOUS=''" "$IMPORTED" \
    && check 0 "an optional list the server does not fill imports empty" \
    || check 1 "an optional list the server does not fill imports empty" "$(cat "$IMPORTED")"

# 9. A file the server does not have, or a symlink where the real file should be.
mv "$TARGET" "$WORK/aside.php"
OUT=$(run diff); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'Check DEPLOY_APP_PATH'* ]] && check 0 "a missing local.php is refused" \
    || check 1 "a missing local.php is refused" "$OUT"
ln -s "$WORK/aside.php" "$TARGET"
OUT=$(PROD_CONFIG_YES=1 run install); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *symlink* ]] && check 0 "a symlinked local.php is refused" \
    || check 1 "a symlinked local.php is refused" "$OUT"

if [ "$FAILED" = 0 ]; then
    echo "prod-config: $PASSED check(s) passed"
    exit 0
fi
echo "prod-config: $FAILED check(s) failed" >&2
exit 1
