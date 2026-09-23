#!/usr/bin/env bash
# One-time conversion of the server from the flat phploy layout to release
# directories. Run once, ever; it is idempotent, so a re-run after a partial
# failure is safe.
#
#   ./tools/deploy-bootstrap.sh --check    # report what would move, change nothing
#   ./tools/deploy-bootstrap.sh --yes      # do it
#
# What it does NOT do is swap anything. After this runs the site is still served
# from the flat tree exactly as before — every piece of shared state has simply
# moved into shared/ and been symlinked back where it was. That is deliberate:
# each move is independently verifiable (a broken image or a 500 shows up
# immediately), and the risky part — replacing public/ with a symlink — happens
# later, once, under ./tools/deploy.sh --bootstrap.
#
# See docs/DEPLOY.md § Cutover.
set -euo pipefail
cd "$(dirname "$0")/.."

CHECK=1
case ${1:-} in
    --yes)   CHECK=0 ;;
    --check) CHECK=1 ;;
    *)       printf 'usage: %s --check | --yes\n' "$0" >&2; exit 1 ;;
esac

CONFIG=${DEPLOY_CONFIG:-.deploy.local}
[ -f "$CONFIG" ] || { echo "ABORT: $CONFIG is missing. Run ./config.sh first." >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONFIG"
: "${DEPLOY_SSH_USER:?}" "${DEPLOY_SSH_HOST:?}" "${DEPLOY_SSH_PORT:=222}" "${DEPLOY_APP_PATH:?}"

APP=$DEPLOY_APP_PATH
SSH=(ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST")

# Everything under data/ that holds state a release must not own. Three
# absences are deliberate:
#   data/config, data/cache — per-release. A merged-config cache shared between
#     releases is what made 'A plugin by the name "requestUri" was not found'
#     arrive on every deploy.
#   data/publications — tracked repo content (the 150-preguntas source), not
#     state. Sharing it would shadow the release's own copy, and because the
#     release directory already exists, `ln -sfn` would quietly create the link
#     *inside* it rather than replacing it.
SHARED_DATA="logs exceptions htaccess-backups fonts musicas texts scans import"

# Uploaded media (1.2 GB) plus the files that exist only on the server. The
# latter are the trap: phploy left unknown docroot files alone, but a release
# swap replaces the docroot with what is in git, so anything not moved here is
# deleted on the first swap. Search-engine verification files are the ones that
# would be missed quietly.
SHARED_PUBLIC="covers associations dh BingSiteAuth.xml google0e1110cae0fbf177.html"

# Top-level names the repository owns inside public/. Computed from git rather
# than listed, so it cannot drift: a release is built from `git ls-files`, so
# anything on the server that is not in this set and not in SHARED_PUBLIC is
# exactly what the first swap would delete.
TRACKED_PUBLIC=$(git ls-files public | cut -d/ -f2 | sort -u | tr '\n' ' ')

printf 'Bootstrapping %s@%s:%s\n' "$DEPLOY_SSH_USER" "$DEPLOY_SSH_HOST" "$APP"
if [ "$CHECK" = 1 ]; then printf '(--check: reporting only, nothing will be modified)\n'; fi
printf '\n'

"${SSH[@]}" "
set -eu
cd \$HOME/$APP
CHECK=$CHECK

say() { printf '  %s\n' \"\$1\"; }

# move_aside <path-under-app> <shared-destination> <depth-to-app>
# Moves a real file or directory into shared/ and leaves a relative symlink in
# its place, so the flat tree keeps working unchanged.
move_aside() {
    src=\$1; dest=\$2; up=\$3
    if [ -L \"\$src\" ]; then say \"already linked: \$src\"; return 0; fi
    if [ ! -e \"\$src\" ]; then say \"absent, skipped: \$src\"; return 0; fi
    if [ -e \"\$dest\" ]; then say \"CONFLICT: \$dest exists and \$src is still real — resolve by hand\"; return 1; fi
    if [ \"\$CHECK\" = 1 ]; then say \"would move: \$src -> \$dest\"; return 0; fi
    mkdir -p \"\$(dirname \"\$dest\")\"
    mv \"\$src\" \"\$dest\"
    ln -sfn \"\$up\$dest\" \"\$src\"
    say \"moved: \$src -> \$dest\"
}

if [ \"\$CHECK\" = 0 ]; then
    mkdir -p releases shared/data shared/public shared/config-autoload
fi

echo 'shared/data/ — state a release must not own:'
for d in $SHARED_DATA; do
    move_aside \"data/\$d\" \"shared/data/\$d\" '../'
done

echo
echo 'shared/public/ — uploaded media and server-only docroot files:'
for d in $SHARED_PUBLIC; do
    move_aside \"public/\$d\" \"shared/public/\$d\" '../'
done

echo
echo 'shared/config-autoload/ — untracked machine config:'
# Both patterns: '*.local.php' does not match 'local.php', which is the one
# holding the database credentials. laminas' own autoload glob is {,*.}local.php.
for f in config/autoload/local.php config/autoload/*.local.php; do
    [ -e \"\$f\" ] || continue
    b=\$(basename \"\$f\")
    move_aside \"config/autoload/\$b\" \"shared/config-autoload/\$b\" '../../'
done

echo
echo 'left alone deliberately:'
if [ -e data/config ]; then say 'data/config — per-release; a new tree must never meet an old merged-config cache'; fi
if [ -e data/cache ]; then say 'data/cache — per-release (Twig compile output)'; fi
if [ -e data/sitemap ]; then say 'data/sitemap — the pre-2026-08 sitemap directory; docs/DEPLOY.md says to delete it'; fi
if [ -e public/sitemap.xml ]; then say 'public/sitemap*.xml — build output, rebuilt into each release before it goes live'; fi

echo
echo 'server-only docroot files NOT claimed above (the first swap deletes these):'
found_orphan=0
for f in \$(ls -A public 2>/dev/null); do
    case \" $SHARED_PUBLIC \" in *\" \$f \"*) continue ;; esac
    case \" $TRACKED_PUBLIC \" in *\" \$f \"*) continue ;; esac
    case \"\$f\" in sitemap.xml|sitemap-*.xml) continue ;; esac
    say \"\$f\"
    found_orphan=1
done
if [ \"\$found_orphan\" = 0 ]; then say '(none)'; fi
" 2>&1 | sed 's/^/  /' || {
    printf '\nBootstrap reported a problem. Nothing is swapped and the site is unaffected.\n' >&2
    exit 1
}

printf '\n'
if [ "$CHECK" = 1 ]; then
    cat <<'MSG'
Nothing was modified. Re-run with --yes to perform the moves.

The "server-only docroot files NOT claimed" list above is the one to read
carefully: every entry on it is deleted by the first release swap. Add the ones
worth keeping to SHARED_PUBLIC at the top of this script, then re-run --check
until the list holds only things you are content to lose.
MSG
else
    cat <<'MSG'
Done. The site is still served from the flat tree and should be working exactly
as before — verify that now, because every one of these moves is visible:

  curl -sI https://schoenstatt.link/ | head -1
  curl -sI https://schoenstatt.link/covers/ -o /dev/null -w '%{http_code}\n'
  open an association page and confirm its images load

Then build the first release and perform the initial swap:

  ./tools/deploy.sh --bootstrap

That replaces public/ with a symlink into releases/. The old directory is kept
as public.pre-atomic — the way back is a single mv.
MSG
fi
