#!/bin/sh
#
# Check (and optionally fix) whether the web server can write JTranslate's compiled
# catalogs. Run it locally before a deploy and on the production server after one.
#
# WHY THIS IS A DIRECTORY PROBLEM, NOT A FILE PROBLEM
#
# Since JTranslate 2.0 a catalog is written to a temporary file in the target
# directory and moved into place with rename(). rename() takes its permission from
# the *directory*, not from the file being replaced, so a catalog left behind by a
# deploy running as a different user is still replaceable. What matters is that the
# directory is writable by the PHP user, and that new directories stay that way —
# hence the setgid check, without which mkdir()'s mode is masked by umask and a new
# text domain's directory lands 0755.
#
# WHERE CATALOGS GO
#
#   module/<TextDomain>/language/     when the text domain names a loaded module
#   language/<TextDomain>/            for every other domain
#
# The `language/` parent must be writable too, because a domain that is not a module
# creates its directory there the first time it is seen.
#
# WHAT --fix WILL NOT TOUCH
#
# A module's own directory. A text domain naming a module writes to
# module/<M>/language/, so that directory has to exist and be writable — but making
# module/<M>/ itself group-writable would hand the web server write access to source
# code to save a mkdir. --fix creates the missing language/ directory instead, with
# the right group and mode, and leaves every source directory alone. Nothing outside
# `language/` and `module/*/language/` is ever chgrp'd or chmod'd.
#
# This matters most on production, where granting the PHP user write inside deployed
# source is the arrangement that produced the problem in the first place. The real fix
# is moving the write target out of the source tree entirely; this script makes the
# current arrangement work without making it worse.
#
# USAGE
#
#   tools/check-translation-permissions.sh [--user USER] [--group GROUP] [--fix]
#
#   --user   the user PHP runs as. Auto-detected when possible; ALWAYS confirm it.
#            On production do not assume it equals your SSH user — the deploy
#            arrangement here uses separate identities for SFTP, shell and PHP.
#   --group  group to grant write to with --fix. Defaults to the user's own group.
#   --fix    repair ownership and modes. Needs root, or ownership of the paths.
#
# THE SAME THING WITHOUT THIS SCRIPT
#
# What --fix does to modes is exactly these two, which is where they came from:
#
#   find language module/*/language -type d -exec chmod 2775 {} +
#   find language module/*/language -type f -name '*.lang.php' -exec chmod 0664 {} +
#
# They belong together: the first grants the group write and makes it survive into new
# subdirectories, the second undoes the exec bit the pre-2.0 code set on generated data.
# Run them as a pair or not at all.
#
# --fix additionally chgrps to the PHP user's group, which those two cannot do and which
# matters wherever the directories are not already owned by the right group — on a
# deploy target they usually are not.
#
# RUN IT WHERE PHP RUNS
#
# On a containerised local setup, run this *inside* the container:
#
#   docker compose exec -w /var/www/schoenstatt.link app \
#       sh tools/check-translation-permissions.sh --user www-data
#
# because `www-data` is a different account in each place. The capsule maps the
# container's www-data to HOST_UID from .env — uid 1003 here — while the host has its
# own www-data at uid 33. Run from the host, this script resolves the wrong one and
# reports every directory as unwritable when nothing is wrong. It prints a hint when
# the results look like that has happened.
#
# Exit status is 0 only when every required path is writable by that user.
# Never run the exporter itself as root: it creates catalogs the web server then
# cannot replace, which is how this breaks in the first place.

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
TARGET_USER=""
TARGET_GROUP=""
FIX=0

while [ $# -gt 0 ]; do
    case "$1" in
        --user)  TARGET_USER="${2:?--user needs a value}"; shift 2 ;;
        --group) TARGET_GROUP="${2:?--group needs a value}"; shift 2 ;;
        --fix)   FIX=1; shift ;;
        -h|--help) sed -n '2,68p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "unknown argument: $1" >&2; exit 2 ;;
    esac
done

# ---------------------------------------------------------------- identify the user
if [ -z "$TARGET_USER" ]; then
    # the owner of a running web worker is the best available guess; the master
    # process is usually root, so skip root when picking
    for candidate in $(ps -eo user=,comm= 2>/dev/null \
        | awk '$2 ~ /^(php-fpm|apache2|httpd|php)/ && $1 != "root" {print $1}' \
        | sort -u); do
        TARGET_USER="$candidate"
        break
    done
fi
[ -n "$TARGET_USER" ] || TARGET_USER="www-data"

if ! id "$TARGET_USER" >/dev/null 2>&1; then
    echo "No such user: $TARGET_USER. Pass --user with the account PHP runs as." >&2
    exit 2
fi

TARGET_UID=$(id -u "$TARGET_USER")
TARGET_GIDS=$(id -G "$TARGET_USER")
[ -n "$TARGET_GROUP" ] || TARGET_GROUP=$(id -gn "$TARGET_USER")

echo "app root:    $ROOT"
echo "PHP user:    $TARGET_USER (uid $TARGET_UID, groups: $TARGET_GIDS)"
echo "running as:  $(id -un) (uid $(id -u))"
[ "$FIX" -eq 1 ] && echo "mode:        FIX" || echo "mode:        check only"
echo

# ------------------------------------------------------------------- the check
# Writable for TARGET_USER, decided from ownership and mode rather than test -w,
# because this script is usually run by a different account than PHP uses.
is_writable_by_target() {
    path=$1
    mode=$(stat -c '%a' "$path" 2>/dev/null) || return 1
    owner=$(stat -c '%u' "$path" 2>/dev/null) || return 1
    group=$(stat -c '%g' "$path" 2>/dev/null) || return 1
    mode=$(printf '%03d' "$((mode % 1000))")
    u=$(printf '%s' "$mode" | cut -c1)
    g=$(printf '%s' "$mode" | cut -c2)
    o=$(printf '%s' "$mode" | cut -c3)

    [ "$owner" = "$TARGET_UID" ] && [ $((u & 2)) -ne 0 ] && return 0
    for gid in $TARGET_GIDS; do
        if [ "$group" = "$gid" ] && [ $((g & 2)) -ne 0 ]; then return 0; fi
    done
    [ $((o & 2)) -ne 0 ] && return 0
    return 1
}

has_setgid() {
    perms=$(stat -c '%A' "$1" 2>/dev/null) || return 1
    case "$perms" in *s*|*S*) return 0 ;; *) return 1 ;; esac
}

describe() {
    stat -c '%A %U:%G' "$1" 2>/dev/null || echo '??? ?:?'
}

FAILURES=0
WARNINGS=0

report() {
    status=$1; path=$2; detail=$3
    rel=$(printf '%s' "$path" | sed "s|^$ROOT/||")
    case "$status" in
        OK)   printf '  ok    %-42s %s\n' "$rel" "$detail" ;;
        WARN) printf '  warn  %-42s %s\n' "$rel" "$detail"; WARNINGS=$((WARNINGS + 1)) ;;
        FAIL) printf '  FAIL  %-42s %s\n' "$rel" "$detail"; FAILURES=$((FAILURES + 1)) ;;
    esac
}

# Only ever called on a catalog directory — language/, language/<Domain>/ or
# module/<M>/language/. Never on a module's source directory; see the header.
fix_dir() {
    dir=$1
    chgrp "$TARGET_GROUP" "$dir" 2>/dev/null || true
    # 2775 absolute rather than g+w,g+s additive: the same end state whatever the
    # directory started as, so re-running cannot leave a half-fixed mode. 2 is the
    # setgid bit, which is the part people leave out and the part that makes it stick.
    chmod 2775 "$dir" 2>/dev/null || true
}

check_dir() {
    dir=$1
    required=$2   # 1 = must exist and be writable, 0 = only if present

    if [ ! -d "$dir" ]; then
        [ "$required" -eq 1 ] && report FAIL "$dir" "missing" || :
        return
    fi

    [ "$FIX" -eq 1 ] && fix_dir "$dir"

    if is_writable_by_target "$dir"; then
        if has_setgid "$dir"; then
            report OK "$dir" "$(describe "$dir")"
        else
            report WARN "$dir" "$(describe "$dir") — no setgid; new subdirectories will lose group write"
        fi
    else
        report FAIL "$dir" "$(describe "$dir") — not writable by $TARGET_USER"
    fi
}

echo "Parent for non-module text domains:"
check_dir "$ROOT/language" 1

# A module with no language/ directory is normal — most modules have no text domain.
# It only matters if one ever appears, and the answer then is to create the directory,
# not to make the module's source writable. --fix does that now so the situation never
# arises; without --fix it is reported as a note and costs nothing.
echo
echo "Modules with no catalog directory yet:"
MISSING=0
for m in "$ROOT"/module/*/; do
    [ -d "$m" ] || continue
    [ -d "$m/language" ] && continue
    MISSING=$((MISSING + 1))
    rel=$(printf '%s' "${m%/}" | sed "s|^$ROOT/||")
    if [ "$FIX" -eq 1 ]; then
        if mkdir "${m}language" 2>/dev/null; then
            fix_dir "${m%/}/language"
            printf '  fixed %-42s created language/ (%s)\n' "$rel" "$(describe "${m%/}/language")"
        else
            printf '  FAIL  %-42s could not create language/ — need write on the module directory\n' "$rel"
            FAILURES=$((FAILURES + 1))
        fi
    else
        printf '  note  %-42s no language/ yet; --fix would create it\n' "$rel"
    fi
done
[ "$MISSING" -eq 0 ] && echo "  ok    every module already has one"

echo
echo "Existing catalog directories:"
for d in "$ROOT"/language/*/ "$ROOT"/module/*/language/; do
    [ -d "$d" ] || continue
    check_dir "${d%/}" 0
done

# Catalog files themselves do not need to be writable — rename() replaces them —
# but an exec bit on generated data is wrong and worth flagging.
echo
echo "Catalog files (mode only; rename() does not need them writable):"
# 0664 is what the library writes, so anything else is drift: an exec bit from the
# pre-2.0 code, or a tighter mode from a deploy. --fix normalises every catalog rather
# than only the executable ones, so one pass leaves them uniform.
ODD_COUNT=0
for f in $(find "$ROOT/language" "$ROOT"/module/*/language -name '*.lang.php' -type f 2>/dev/null); do
    mode=$(stat -c '%a' "$f" 2>/dev/null) || continue
    [ "$mode" = "664" ] && continue
    ODD_COUNT=$((ODD_COUNT + 1))
    [ "$FIX" -eq 1 ] && chmod 0664 "$f" 2>/dev/null || true
done
if [ "$ODD_COUNT" -eq 0 ]; then
    echo "  ok    every catalog is 0664"
elif [ "$FIX" -eq 1 ]; then
    echo "  fixed $ODD_COUNT catalog(s) were not 0664; normalised"
else
    echo "  warn  $ODD_COUNT catalog(s) are not 0664 (generated data files)"
    WARNINGS=$((WARNINGS + 1))
fi

echo
if [ "$FAILURES" -gt 0 ]; then
    echo "$FAILURES path(s) block the export, $WARNINGS warning(s)."
    # Everything failing at once is far more often the wrong account than a genuinely
    # broken tree, and the usual cause is running this on the host of a containerised
    # app where the same user name maps to a different uid inside and outside.
    if [ "$FAILURES" -ge 5 ] && [ -f "$ROOT/docker-compose.yml" ]; then
        echo
        echo "Every path failed, and this looks like a container setup. '$TARGET_USER'"
        echo "resolves to uid $(id -u "$TARGET_USER" 2>/dev/null || echo '?') here; inside the container it may be another"
        echo "uid entirely (the capsule maps it to HOST_UID from .env). Check there first:"
        echo "  docker compose exec -w /var/www/schoenstatt.link app \\"
        echo "      sh tools/check-translation-permissions.sh --user $TARGET_USER"
    fi
    if [ "$FIX" -eq 0 ]; then
        echo "Re-run with --fix (as root, or as the owner of those paths):"
        echo "  $0 --user $TARGET_USER --group $TARGET_GROUP --fix"
    else
        echo "--fix could not repair them; you likely need root."
    fi
    exit 1
fi

echo "All required paths are writable by $TARGET_USER. $WARNINGS warning(s)."
echo
echo "Confirm for real by rebuilding the catalogs as that user:"
echo "  php bin/console jtranslate:export-catalogs --dry-run"
echo "  php bin/console jtranslate:export-catalogs"
exit 0
