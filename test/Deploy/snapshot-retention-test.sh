#!/usr/bin/env bash
#
# The pre-migration snapshot retention policy, exercised on synthetic names.
#
# WHY THIS EXISTS. Until #297 the policy had one caller and lived inside the function that
# did the deleting, so the only way to test it was to build a real backup directory. Now it
# has two callers — the operator's disk and the production server — and a rule implemented
# twice is a rule that will disagree with itself. `prunable` is the single implementation,
# and it is pure: names in, names to delete out, no filesystem and no ssh. This is what
# that purchase was for.
#
# What the policy must guarantee, and what each case below pins:
#   - the most recent N runs survive whatever their age, so a quiet month cannot leave you
#     with nothing;
#   - anything younger than the day limit survives whatever its position, so a busy
#     afternoon of migrations cannot age out yesterday's;
#   - a run is a GROUP. Both snapshots of one apply share a timestamp and live or die
#     together, or a restore finds half of what it needs.
set -uo pipefail

cd "$(dirname "$0")/../.."

FAILED=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        printf '        expected: %s\n' "${3:-}"
        printf '        actual:   %s\n' "${4:-}"
        FAILED=$((FAILED + 1))
    fi
}

# `prunable` alone, without running the rest of migrate.sh: source the script with an
# action it exits on early would still need credentials, so the function is lifted out by
# text. Crude, and it keeps the test honest — it is the shipped implementation, not a copy.
eval "$(awk '/^prunable\(\) \{/,/^\}/' tools/migrate.sh)"
declare -f prunable >/dev/null || { echo "could not lift prunable() out of tools/migrate.sh" >&2; exit 1; }

run() { printf '%s\n' "$1" | prunable "$2" "$3" | sort | tr '\n' ' ' | sed 's/ $//'; }

OLD_A="20250101-010000-production-db1.0.sql.gz"
OLD_B="20250101-010000-production-db1.1.sql.gz"   # same run as OLD_A
MID="20250601-010000-production-db2.0.sql.gz"
NEW="20260901-010000-production-db3.0.sql.gz"
CUTOFF="20260101-000000"

# 1. Nothing to do on empty input — the first deploy after this lands has no snapshots yet,
#    and a policy that errors on nothing would abort it.
OUT=$(run "" 2 "$CUTOFF")
[ -z "$OUT" ] && check 0 "empty input prunes nothing" || check 1 "empty input prunes nothing" "(nothing)" "$OUT"

# 2. The last N runs survive regardless of age. keep_runs=2 covers NEW and MID, so only the
#    January-2025 run is eligible — and both its files go, together.
OUT=$(run "$OLD_A
$OLD_B
$MID
$NEW" 2 "$CUTOFF")
EXPECT="$OLD_A $OLD_B"
[ "$OUT" = "$EXPECT" ] && check 0 "keeps the last 2 runs, drops the older run whole" \
    || check 1 "keeps the last 2 runs, drops the older run whole" "$EXPECT" "$OUT"

# 3. The day limit overrides position. With keep_runs=1 only NEW is protected by position,
#    but MID is younger than a cutoff of 2025-01-02 and must survive anyway.
OUT=$(run "$OLD_A
$MID
$NEW" 1 "20250102-000000")
EXPECT="$OLD_A"
[ "$OUT" = "$EXPECT" ] && check 0 "the day limit protects a run the run-count would drop" \
    || check 1 "the day limit protects a run the run-count would drop" "$EXPECT" "$OUT"

# 4. Both floors together can protect everything, which is the safe direction to be wrong in.
OUT=$(run "$OLD_A
$MID
$NEW" 5 "$CUTOFF")
[ -z "$OUT" ] && check 0 "keep_runs above the run count prunes nothing" || check 1 "keep_runs above the run count prunes nothing" "(nothing)" "$OUT"

# 5. A name that is not a snapshot is ignored rather than deleted. The remote listing is a
#    plain `ls` of a directory an operator can also put things in.
OUT=$(run "README
notes.txt
$OLD_A
$NEW" 1 "$CUTOFF")
EXPECT="$OLD_A"
[ "$OUT" = "$EXPECT" ] && check 0 "names without a run stamp are left alone" \
    || check 1 "names without a run stamp are left alone" "$EXPECT" "$OUT"

# 6. The whole point, stated as a test: a run is never split. If either half of a two-file
#    run is prunable both must be, or a restore finds one table and not the other.
OUT=$(run "$OLD_A
$OLD_B
$NEW" 1 "$CUTOFF")
EXPECT="$OLD_A $OLD_B"
[ "$OUT" = "$EXPECT" ] && check 0 "a multi-file run is pruned as a unit, never split" \
    || check 1 "a multi-file run is pruned as a unit, never split" "$EXPECT" "$OUT"

# 7. And the cutoff helper produces something the string compare can actually use, in the
#    same shape the filenames carry. A cutoff of the wrong shape compares wrong silently.
eval "$(awk '/^prune_cutoff\(\) \{/,/^\}/' tools/migrate.sh)"
CUT=$(prune_cutoff 30)
case "$CUT" in
    [0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]) check 0 "prune_cutoff returns a YYYYMMDD-HHMMSS stamp ($CUT)" ;;
    *) check 1 "prune_cutoff returns a YYYYMMDD-HHMMSS stamp" "YYYYMMDD-HHMMSS" "$CUT" ;;
esac

if [ "$FAILED" = 0 ]; then
    echo "snapshot retention: all checks passed"
    exit 0
fi
echo "snapshot retention: $FAILED check(s) failed" >&2
exit 1
