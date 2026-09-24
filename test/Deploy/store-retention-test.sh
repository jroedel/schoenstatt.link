#!/usr/bin/env bash
#
# The two server-side stores the deploy prunes, run here against synthetic directories.
#
# WHY THIS EXISTS. Until 2026-09-24 neither store had any bound at all: one .htaccess copy
# per deploy and one directory per distinct failure, both kept forever. Bounding them means
# a deploy now runs `rm -rf` on the server, unattended, on a path built from configuration
# — and the exception store's rule is subtle enough to get wrong in a way that deletes the
# wrong thing silently. So the shipped programs take their inputs as arguments and are
# lifted out by their markers here, rather than being reasoned about.
#
# What must hold:
#   - the .htaccess prune keeps the N newest and removes only the rest;
#   - the exception prune ages on <fp>/last.txt, NOT on the fingerprint directory. On Linux
#     rewriting a file inside a directory does not move the directory's mtime, so ageing on
#     the directory would delete fingerprints that are still firing every day;
#   - .emails/ and .overflow are bookkeeping and are never touched;
#   - a missing store is not an error. The first deploy after this lands has neither
#     directory, and a deploy must not fail over that.
set -uo pipefail

cd "$(dirname "$0")/../.."

FAILED=0 PASSED=0
check() {
    if [ "$1" = 0 ]; then
        PASSED=$((PASSED + 1)); printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        printf '        expected: %s\n' "${3:-}"
        printf '        actual:   %s\n' "${4:-}"
        FAILED=$((FAILED + 1))
    fi
}

for marker in 'prune htaccess backups' 'prune exception store'; do
    eval "$(awk "/^# >>> $marker\$/,/^# <<< $marker\$/" tools/deploy.sh)"
done
[ -n "${PRUNE_HTACCESS_PROG:-}" ] && [ -n "${PRUNE_EXCEPTIONS_PROG:-}" ] \
    || { echo "could not lift the prune programs out of tools/deploy.sh" >&2; exit 1; }
eval "$PRUNE_HTACCESS_PROG"
eval "$PRUNE_EXCEPTIONS_PROG"

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

# ================================================================ .htaccess =====

HT=$WORK/htaccess-backups
mkdir -p "$HT"
# Distinct mtimes, oldest first, so `ls -1t` has a real order to sort by.
for i in 1 2 3 4 5; do
    printf 'copy %s\n' "$i" > "$HT/htaccess-2026090$i-120000"
    touch -d "2026-09-0$i 12:00:00" "$HT/htaccess-2026090$i-120000"
done

REMOVED=$( (prune_htaccess "$HT" 3) | sort | tr '\n' ' ' | sed 's/ $//')
EXPECT="htaccess-20260901-120000 htaccess-20260902-120000"
[ "$REMOVED" = "$EXPECT" ] \
    && check 0 "keeps the 3 newest .htaccess copies and names the 2 it removed" \
    || check 1 "keeps the 3 newest .htaccess copies and names the 2 it removed" "$EXPECT" "$REMOVED"

LEFT=$(ls -1 "$HT" | sort | tr '\n' ' ' | sed 's/ $//')
EXPECT="htaccess-20260903-120000 htaccess-20260904-120000 htaccess-20260905-120000"
[ "$LEFT" = "$EXPECT" ] \
    && check 0 "and the 3 it kept are the newest 3, not any 3" \
    || check 1 "and the 3 it kept are the newest 3, not any 3" "$EXPECT" "$LEFT"

# Fewer files than the limit is the normal case for a long time after this lands.
REMOVED=$( (prune_htaccess "$HT" 10) | tr '\n' ' ')
[ -z "$REMOVED" ] \
    && check 0 "a store smaller than the limit loses nothing" \
    || check 1 "a store smaller than the limit loses nothing" "(nothing)" "$REMOVED"

# ========================================================== exception store =====

EX=$WORK/exceptions
mkdir -p "$EX/.emails"
fingerprint() {  # $1 name, $2 how many days ago it last fired
    mkdir -p "$EX/$1/recent"
    printf 'first\n' > "$EX/$1/first.txt"
    printf '{"count":3}\n' > "$EX/$1/meta.json"
    printf 'last\n' > "$EX/$1/last.txt"
    touch -d "$2 days ago" "$EX/$1/last.txt"
}
fingerprint deadbeef 200      # long gone
fingerprint cafebabe 91       # just past a 90-day limit
fingerprint 0badf00d 89       # just inside it
fingerprint feedface 0        # fired today

# THE TRAP THIS PINS. A fingerprint still firing daily has a fresh last.txt but a
# directory mtime frozen at the moment it was created, because rewriting last.txt does
# not touch the directory. Age it on the directory and this one is deleted.
touch -d "200 days ago" "$EX/feedface"

printf '9\n' > "$EX/.emails/2026090112"
touch -d "200 days ago" "$EX/.emails/2026090112"
printf 'dropped-fp\n' > "$EX/.overflow"
touch -d "200 days ago" "$EX/.overflow"

REMOVED=$( (prune_exceptions "$EX" 90) | sort | tr '\n' ' ' | sed 's/ $//')
EXPECT="cafebabe deadbeef"
[ "$REMOVED" = "$EXPECT" ] \
    && check 0 "ages off only fingerprints silent longer than the limit" \
    || check 1 "ages off only fingerprints silent longer than the limit" "$EXPECT" "$REMOVED"

[ -d "$EX/feedface" ] \
    && check 0 "a fingerprint still firing survives an ancient directory mtime" \
    || check 1 "a fingerprint still firing survives an ancient directory mtime" "feedface kept" "deleted"

[ -d "$EX/0badf00d" ] \
    && check 0 "one day inside the limit is kept" \
    || check 1 "one day inside the limit is kept" "0badf00d kept" "deleted"

[ -f "$EX/.emails/2026090112" ] && [ -f "$EX/.overflow" ] \
    && check 0 ".emails/ and .overflow are bookkeeping and are never aged off" \
    || check 1 ".emails/ and .overflow are bookkeeping and are never aged off" "both present" "one is gone"

# The whole fingerprint goes, not just the file that dated it — a directory left holding
# first.txt and meta.json would keep the notification throttle armed against a failure we
# have decided to forget.
[ ! -e "$EX/deadbeef" ] \
    && check 0 "an aged-off fingerprint is removed whole, not just its last.txt" \
    || check 1 "an aged-off fingerprint is removed whole, not just its last.txt" "gone" "$(ls "$EX/deadbeef" 2>/dev/null | tr '\n' ' ')"

# ================================================================== absent =====
#
# The first deploy after this lands has neither directory. Both must be a no-op, not a
# failure: this runs in the housekeeping step, after the swap, where an error would report
# a successful release as a failed deploy.
RC=0; OUT=$(prune_htaccess "$WORK/not-there" 5) || RC=$?
[ "$RC" = 0 ] && [ -z "$OUT" ] \
    && check 0 "a missing .htaccess store is a no-op, not an error" \
    || check 1 "a missing .htaccess store is a no-op, not an error" "rc=0, no output" "rc=$RC out=$OUT"

RC=0; OUT=$(prune_exceptions "$WORK/not-there" 90) || RC=$?
[ "$RC" = 0 ] && [ -z "$OUT" ] \
    && check 0 "a missing exception store is a no-op, not an error" \
    || check 1 "a missing exception store is a no-op, not an error" "rc=0, no output" "rc=$RC out=$OUT"

# ========================================= the defaults are the written policy =====
#
# The deploy workflow sends no retention knobs — tools/gh-secrets.sh leaves them out on
# purpose, because they are policy rather than credentials — so an unattended deploy runs
# on whatever the scripts default to. If a default drifts from .deploy.local.dist, then the
# file everyone reads to learn the policy stops describing the policy that actually runs,
# and nothing else would say so.
default_of() {  # $1 var name -> the default the scripts would use
    grep -hoE "\\$\\{$1:[-=][0-9]+\\}" tools/migrate.sh tools/deploy.sh 2>/dev/null \
        | head -1 | grep -oE '[0-9]+'
}
dist_of() { grep -oE "^$1=[0-9]+" .deploy.local.dist | cut -d= -f2; }

for VAR in DEPLOY_KEEP_BACKUP_RUNS DEPLOY_KEEP_BACKUP_DAYS DEPLOY_MAX_BACKUP_DAYS \
           DEPLOY_KEEP_HTACCESS DEPLOY_KEEP_EXCEPTION_DAYS; do
    D=$(default_of "$VAR"); E=$(dist_of "$VAR")
    if [ -n "$D" ] && [ "$D" = "$E" ]; then
        check 0 "$VAR: the script default and .deploy.local.dist agree ($D)"
    else
        check 1 "$VAR: the script default and .deploy.local.dist agree" "${E:-absent from the dist}" "${D:-no default in any script}"
    fi
done

# And the ceiling must sit above the soft floor, or it would delete things the floor was
# still protecting and the two numbers would be describing opposite policies.
SOFT=$(dist_of DEPLOY_KEEP_BACKUP_DAYS); HARD=$(dist_of DEPLOY_MAX_BACKUP_DAYS)
[ -n "$SOFT" ] && [ -n "$HARD" ] && [ "$HARD" -gt "$SOFT" ] \
    && check 0 "the ceiling ($HARD days) is above the day floor ($SOFT days)" \
    || check 1 "the ceiling is above the day floor" "MAX > KEEP_DAYS" "MAX=$HARD KEEP_DAYS=$SOFT"

if [ "$FAILED" = 0 ]; then
    printf 'store retention: %d check(s) passed\n' "$PASSED"
    exit 0
fi
printf 'store retention: %d of %d check(s) FAILED\n' "$FAILED" "$((FAILED + PASSED))" >&2
exit 1
