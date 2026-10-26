#!/usr/bin/env bash
#
# tools/secrets-env.sh: the one reader of secrets.env.
#
# Every deploy tool, the runner's deploy step and `make gh-secrets` read credentials through
# it, so a parsing mistake here is a wrong password everywhere at once — and the reason it
# exists is that the file is DATA. Sourced, `.deploy.local` ran whatever was in it. The
# checks that earn this file are 4 and 5: a value that would have executed stays literal,
# and an unreadable-by-others mode is refused, not warned about.
#
# Plain bash, no server, no php.
set -uo pipefail

cd "$(dirname "$0")/../.."
# shellcheck source=tools/secrets-env.sh
. tools/secrets-env.sh

FAILED=0 PASSED=0
check() {   # $1 0|1, $2 name, [$3 expected, $4 got]
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"; PASSED=$((PASSED + 1))
    else
        printf '  FAIL  %s\n' "$2"
        [ $# -ge 4 ] && printf '        expected: %s\n        got:      %s\n' "$3" "$4"
        FAILED=$((FAILED + 1))
    fi
}
same() { [ "$2" = "$3" ] && check 0 "$1" || check 1 "$1" "$2" "$3"; }

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

write() {   # $1 file; the content on stdin, mode 0600
    (umask 077; cat > "$1")
}

# 1. The three spellings, and what an unquoted value loses.
write "$WORK/a.env" <<'EOF'
# a comment
PLAIN=abc
SPACED=   padded value   # a trailing comment
SINGLE='  kept  # not a comment '
DOUBLE="it's kept"
HASHED=abc#def
EMPTY=
QUOTED_EMPTY=''

EOF
(
    secrets_env_load "$WORK/a.env" || exit 1
    printf '%s|%s|%s|%s|%s|%s|%s\n' "$PLAIN" "$SPACED" "$SINGLE" "$DOUBLE" "$HASHED" "$EMPTY" "$QUOTED_EMPTY"
) > "$WORK/out" 2>&1
same "unquoted, padded, quoted and empty values" \
    "abc|padded value|  kept  # not a comment |it's kept|abc#def||" "$(cat "$WORK/out")"

# 2. CRLF, as a password manager writes it.
printf 'CRLF=value\r\nNEXT=two\r\n' | write "$WORK/crlf.env"
same "a CRLF file reads as if it were LF" "value|two" \
    "$(secrets_env_load "$WORK/crlf.env" && printf '%s|%s' "$CRLF" "$NEXT")"

# 3. A last line without a newline is still read.
printf 'LAST=final' | write "$WORK/nonl.env"
same "a final line without a newline" "final" "$(secrets_env_load "$WORK/nonl.env" && printf '%s' "$LAST")"

# 4. Nothing executes. Each of these would have run under `.`.
write "$WORK/exec.env" <<EOF
SUBST=\$(touch $WORK/pwned-subst)
TICKS=\`touch $WORK/pwned-ticks\`
QUOTED="\$(touch $WORK/pwned-quoted)"
VAR=\$HOME
EOF
(secrets_env_load "$WORK/exec.env" && printf '%s\n%s\n%s\n' "$SUBST" "$QUOTED" "$VAR") > "$WORK/out"
if ls "$WORK"/pwned-* >/dev/null 2>&1; then
    check 1 "a command substitution in a value is never run"
else
    check 0 "a command substitution in a value is never run"
fi
same "and the value is kept as written" "\$(touch $WORK/pwned-subst)|\$(touch $WORK/pwned-quoted)|\$HOME" \
    "$(paste -sd'|' "$WORK/out")"

# 5. The mode: anything readable by group or others is refused.
printf 'X=1\n' > "$WORK/open.env"; chmod 644 "$WORK/open.env"
OUT=$(secrets_env_load "$WORK/open.env" 2>&1); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *'mode 644'* ]] \
    && check 0 "a 0644 file is refused, naming its mode" \
    || check 1 "a 0644 file is refused, naming its mode" "rc≠0, 'mode 644'" "rc=$RC $OUT"
chmod 640 "$WORK/open.env"
secrets_env_load "$WORK/open.env" 2>/dev/null && check 1 "a 0640 file is refused too" || check 0 "a 0640 file is refused too"
chmod 400 "$WORK/open.env"
secrets_env_load "$WORK/open.env" 2>/dev/null && check 0 "a 0400 file is read" || check 1 "a 0400 file is read"

# 6. Through a symlink the TARGET's mode counts — prod-deploy's checkout used to link it.
ln -s "$WORK/a.env" "$WORK/link.env"
secrets_env_load "$WORK/link.env" 2>/dev/null && check 0 "a link to a 0600 file is read" \
    || check 1 "a link to a 0600 file is read"

# 7. A malformed line is an error naming its number, and never echoing the line.
write "$WORK/bad.env" <<'EOF'
GOOD=1
lower=hunter2-secret
EOF
OUT=$(secrets_env_load "$WORK/bad.env" 2>&1); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *':2 '* ]] && [[ $OUT != *hunter2* ]] \
    && check 0 "a lower-case name is an error at its line number, value unprinted" \
    || check 1 "a lower-case name is an error at its line number, value unprinted" "rc≠0, ':2', no value" "rc=$RC $OUT"

# 8. A missing file names the path it was given.
OUT=$(secrets_env_load "$WORK/absent.env" 2>&1); RC=$?
[ "$RC" != 0 ] && [[ $OUT == *"$WORK/absent.env"* ]] \
    && check 0 "a missing file is named" || check 1 "a missing file is named" "$WORK/absent.env" "$OUT"

# 9. Not exported: a child process does not inherit a credential.
write "$WORK/x.env" <<< 'SECRET_FOR_CHILD=leak'
same "loaded names are not exported to children" "" \
    "$(secrets_env_load "$WORK/x.env" && bash -c 'printf %s "${SECRET_FOR_CHILD:-}"')"

# 10. secrets_env_line writes what the loader reads back exactly.
for v in "plain" "it's" 'say "hi"' "  spaced # hash  " ""; do
    secrets_env_line ROUND "$v" | write "$WORK/round.env"
    same "secrets_env_line round-trips [$v]" "$v" "$(secrets_env_load "$WORK/round.env" && printf '%s' "$ROUND")"
done
secrets_env_line BOTH "it's \"both\"" >/dev/null 2>&1 \
    && check 1 "a value with both quote characters is refused" \
    || check 0 "a value with both quote characters is refused"

# 11. SECRETS_ENV moves the default path, for every script at once.
same "SECRETS_ENV sets SECRETS_ENV_FILE" "/elsewhere/s.env" \
    "$(SECRETS_ENV=/elsewhere/s.env bash -c '. tools/secrets-env.sh; printf %s "$SECRETS_ENV_FILE"')"
same "unset, it is secrets.env at the repository root" "$(pwd)/secrets.env" \
    "$(env -u SECRETS_ENV bash -c '. tools/secrets-env.sh; printf %s "$SECRETS_ENV_FILE"')"

# 12. The template parses: every line is a comment, blank or NAME=value.
cp secrets.env.example "$WORK/example.env" && chmod 600 "$WORK/example.env"
secrets_env_load "$WORK/example.env" 2>/dev/null \
    && check 0 "secrets.env.example parses" || check 1 "secrets.env.example parses"

if [ "$FAILED" = 0 ]; then
    echo "secrets env: $PASSED check(s) passed"
    exit 0
fi
echo "secrets env: $FAILED check(s) failed" >&2
exit 1
