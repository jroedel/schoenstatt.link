#!/usr/bin/env bash
#
# Move the cover images of two publications onto the id they were merged into.
#
# The companion to database/db8.2.sql, which does the same repair for the three
# database references. It is a separate script rather than part of the migration
# because covers are not in the database and not in the repository: public/covers is
# gitignored, lives in shared/public/covers on the server, and is symlinked into every
# release. Nothing a deploy carries can touch it.
#
# WHAT IS WRONG
# -------------
# A cover is resolved by filename convention, not by a column:
# App\Controller\PublicationController and App\Controller\LiteratureController both test
# file_exists('public/covers/<publicationId>-400px.jpg') (and -80px.jpg for the index).
# So when a data-sourced publication was merged into a new first-class row, its cover
# stayed behind under the OLD id and the surviving publication silently lost its picture.
#
# Measured in the capsule on 2026-08-17, four publications are in that state. Two of them
# are repaired here and two are deliberately left alone:
#
#   1426 -> 10249   10249 has no cover at all. Pure gain.       REPAIRED
#   1655 -> 10186   10186 has no cover at all. Pure gain.       REPAIRED
#   1437 -> 10203   10203 already has a cover, a DIFFERENT      LEFT ALONE
#   1672 -> 10185   image. Ditto.                               LEFT ALONE
#
# The retired /admin/literature-maintenance/update-cover-images sweep did all four with
# rename(), which overwrites its destination without a word — so it would have replaced
# two publications' current covers with the older imported image, an editorial decision
# nobody took. (Verified they are different pictures, not re-encodings: 1672-2000px.jpg
# is 484,373 bytes against 10185-2000px.jpg's 326,012.) Deciding between those two pairs
# is a job for someone who can look at them; see docs/BACKLOG.md.
#
# ONE-OFF. Delete this script once it has run against production. It is in tools/ rather
# than pasted into a pull request body so that what ran is reviewable and so that running
# it twice is harmless.
#
# Usage:
#   tools/fix-merged-covers.sh [--apply] [covers-dir]
#
#   Default is a dry run; --apply performs the moves. covers-dir defaults to
#   public/covers, which is right in the capsule and in a release directory on the server.
#
set -euo pipefail

APPLY=0
COVERS_DIR=""

for arg in "$@"; do
    case "$arg" in
        --apply) APPLY=1 ;;
        -*) echo "unknown option: $arg" >&2; exit 2 ;;
        *) COVERS_DIR="$arg" ;;
    esac
done

COVERS_DIR="${COVERS_DIR:-public/covers}"

if [ ! -d "$COVERS_DIR" ]; then
    echo "no such directory: $COVERS_DIR" >&2
    exit 1
fi

# old:new. Only pairs where the destination has no cover of its own.
PAIRS="1426:10249 1655:10186"

# The five names the thumbnail pipeline produces per publication.
SUFFIXES=".jpg -80px.jpg -200px.jpg -400px.jpg -2000px.jpg"

moved=0
skipped=0
absent=0
blocked=0

for pair in $PAIRS; do
    old="${pair%%:*}"
    new="${pair##*:}"

    for suffix in $SUFFIXES; do
        src="$COVERS_DIR/${old}${suffix}"
        dst="$COVERS_DIR/${new}${suffix}"

        if [ ! -f "$src" ]; then
            if [ -f "$dst" ]; then
                skipped=$((skipped + 1))
                echo "  done already: ${new}${suffix}"
            else
                absent=$((absent + 1))
                echo "  not present:  ${old}${suffix}"
            fi
            continue
        fi

        # Never overwrite. If both exist the situation is not the one described above
        # and wants a human, not a --apply.
        if [ -f "$dst" ]; then
            blocked=$((blocked + 1))
            echo "  REFUSED:      ${old}${suffix} -> ${new}${suffix} (destination exists)" >&2
            continue
        fi

        moved=$((moved + 1))
        if [ "$APPLY" -eq 1 ]; then
            mv -n "$src" "$dst"
            echo "  moved:        ${old}${suffix} -> ${new}${suffix}"
        else
            echo "  would move:   ${old}${suffix} -> ${new}${suffix}"
        fi
    done
done

echo
if [ "$APPLY" -eq 1 ]; then
    echo "moved $moved, already done $skipped, source absent $absent, refused $blocked"
else
    echo "would move $moved, already done $skipped, source absent $absent, refused $blocked"
    echo "(dry run — pass --apply to perform the moves)"
fi

if [ "$blocked" -gt 0 ]; then
    exit 1
fi
