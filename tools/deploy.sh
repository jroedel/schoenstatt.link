#!/usr/bin/env bash
# Atomic deploy for schoenstatt.link — one command, from a clean master.
#
# Replaces phploy. The difference that matters is not the transport but the
# *shape*: phploy wrote file-by-file into the live docroot, so every deploy had
# a window in which visitors met a half-uploaded tree (17 of 21 real exception
# fingerprints on 2026-08-14 were deploy artifacts, in nine bursts). Here a
# release is built, composer-installed and warmed in a directory nothing is
# serving, and goes live with a single rename(2) of one symlink.
#
#   ./tools/deploy.sh              # the whole thing
#   ./tools/deploy.sh --dry-run    # preflight + plan, server untouched
#   ./tools/deploy.sh --rollback   # re-point the symlink at the previous release
#   ./tools/deploy.sh --releases   # what is on the server, and what is live
#   ./tools/deploy.sh --migrations # what is applied to the database, and what is pending
#
#   --stash        deploy HEAD with local changes stashed, restored afterwards
#   --skip-tests   skip tools/ci-local.sh in the preflight
#   --ref REF      deploy something other than master (says so, loudly)
#   --to RELEASE   with --rollback, a specific release rather than the previous
#   --allow-incompatible   with --rollback, proceed even when the target predates
#                  a destructive migration (it will almost certainly be broken)
#   --bootstrap    first swap only, converting a flat tree to the release layout
#   -y, --yes      no confirmation prompt
#
# Configuration is .deploy.local (gitignored, mode 0600), seeded from
# .deploy.local.dist by config.sh. Credentials never reach the server.
#
# Requires locally: git, rsync, ssh, curl. No PHP — the host CLI's missing
# extensions were why phploy needed php8.0, and that wart goes away with it.
#
# See docs/DEPLOY.md for the layout, the cutover and the rollback story.
set -euo pipefail

cd "$(dirname "$0")/.."
REPO_ROOT=$(pwd)

# ---------------------------------------------------------------- output ----

if [ -t 1 ]; then
    C_STEP=$'\033[1;36m'; C_OK=$'\033[32m'; C_WARN=$'\033[33m'
    C_ERR=$'\033[1;31m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
else
    C_STEP=''; C_OK=''; C_WARN=''; C_ERR=''; C_DIM=''; C_OFF=''
fi

STEP_N=0
step() { STEP_N=$((STEP_N + 1)); printf '%s[%d] %s%s\n' "$C_STEP" "$STEP_N" "$1" "$C_OFF"; }
info() { printf '    %s\n' "$1"; }
dim()  { printf '    %s%s%s\n' "$C_DIM" "$1" "$C_OFF"; }
ok()   { printf '    %s✓ %s%s\n' "$C_OK" "$1" "$C_OFF"; }
warn() { printf '    %s! %s%s\n' "$C_WARN" "$1" "$C_OFF" >&2; }
fail() { printf '\n%sABORT: %s%s\n' "$C_ERR" "$1" "$C_OFF" >&2; exit 1; }

# ----------------------------------------------------------------- flags ----

DRY_RUN=0 SKIP_TESTS=0 DO_STASH=0 ACTION=deploy ROLLBACK_TO='' GIT_REF='' ASSUME_YES=0
ALLOW_INCOMPATIBLE=0
BOOTSTRAP=0 INITIAL_SWAP=0 POST_MIGRATIONS_FAILED=0

usage() {
    # The comment header above is the help text; it ends at the first non-comment line.
    sed -n '2,/^[^#]/p' "$0" | sed 's/^#\{1,\} \{0,1\}//; $d'
    exit "${1:-0}"
}

while [ $# -gt 0 ]; do
    case $1 in
        --dry-run)    DRY_RUN=1 ;;
        --skip-tests) SKIP_TESTS=1 ;;
        --stash)      DO_STASH=1 ;;
        --rollback)   ACTION=rollback ;;
        --bootstrap)  BOOTSTRAP=1 ;;
        --releases)   ACTION=releases ;;
        --migrations) exec bash tools/migrate.sh status ;;
        --to)         ROLLBACK_TO=${2:-}; shift ;;
        --allow-incompatible) ALLOW_INCOMPATIBLE=1 ;;
        --ref)        GIT_REF=${2:-}; shift ;;
        -y|--yes)     ASSUME_YES=1 ;;
        -h|--help)    usage 0 ;;
        *)            printf 'unknown option: %s\n\n' "$1" >&2; usage 1 ;;
    esac
    shift
done

# ---------------------------------------------------------------- config ----

CONFIG=$REPO_ROOT/.deploy.local
[ -f "$CONFIG" ] || fail ".deploy.local is missing. Run ./config.sh, then fill in the TODOs."

# The file is sourced, so it can hold nothing but assignments. It carries the
# full-DDL database password (used only through an SSH tunnel, see PR B), which
# is why the mode is checked rather than assumed.
CONFIG_MODE=$(stat -c '%a' "$CONFIG" 2>/dev/null || stat -f '%Lp' "$CONFIG")
case $CONFIG_MODE in
    600|400) ;;
    *) warn ".deploy.local is mode $CONFIG_MODE; it holds credentials. chmod 600 it." ;;
esac

# shellcheck disable=SC1090
. "$CONFIG"

: "${DEPLOY_SSH_USER:?not set in .deploy.local}"
: "${DEPLOY_SSH_HOST:?not set in .deploy.local}"
: "${DEPLOY_SSH_PORT:=222}"
: "${DEPLOY_APP_PATH:?not set in .deploy.local}"
: "${DEPLOY_BASE_URL:?not set in .deploy.local}"
: "${DEPLOY_KEEP_RELEASES:=5}"
: "${DEPLOY_API_KEY:=}"
: "${DEPLOY_CANARY_COOKIE:=}"

# An unreplaced placeholder is worse than a missing value here. A bogus
# DEPLOY_API_KEY does not fail loudly: /en/sm/cache-status rejects it, the smoke
# run fails, and a perfectly good release is rolled back automatically — with
# the deploy reporting a site problem that does not exist.
for v in DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_APP_PATH DEPLOY_BASE_URL DEPLOY_API_KEY; do
    case ${!v} in
        *TODO*) fail "$v in .deploy.local is still the template placeholder (${!v}). Fill it in — a placeholder API key fails the post-deploy smoke run and triggers an automatic rollback of a release that was fine." ;;
    esac
done

APP=$DEPLOY_APP_PATH
RELEASES=$APP/releases
SHARED=$APP/shared

# ------------------------------------------------------------------- ssh ----

for t in git rsync ssh curl; do
    command -v "$t" >/dev/null 2>&1 || fail "$t is not installed locally."
done

# One multiplexed connection for the whole run: a deploy makes a dozen ssh
# calls and the key needs its passphrase entered at most once.
CTRL_PATH=$(mktemp -u "${TMPDIR:-/tmp}/deploy-ssh-XXXXXX")
SSH=(ssh -o ControlMaster=auto -o ControlPath="$CTRL_PATH" -o ControlPersist=120
     -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST")

# One exit handler for everything, installed once. Both halves are conditional
# on state set later, so re-arming the trap mid-script (and getting the order
# wrong on the failure path) is not necessary.
FILE_LIST=''
STASHED=0
CHECKED_OUT_REF=0
ORIG_BRANCH=''
on_exit() {
    local status=$?
    if [ -n "$FILE_LIST" ]; then rm -f "$FILE_LIST"; fi
    # A reset helper left on the server is a publicly reachable cache flush, so it
    # goes even if the deploy died mid-step. RESET_FILE is cleared by the happy
    # path, so this only fires when something went wrong.
    if [ -n "${RESET_FILE:-}" ]; then
        rsh "rm -f $RESET_FILE" || warn "could not remove $RESET_FILE — delete it by hand."
    fi
    # --ref checks something else out; put the branch back rather than leaving
    # the user on a detached HEAD they did not ask to be on.
    if [ "$CHECKED_OUT_REF" = 1 ] && [ -n "$ORIG_BRANCH" ] && [ "$ORIG_BRANCH" != HEAD ]; then
        git checkout --quiet "$ORIG_BRANCH" || warn "could not return to '$ORIG_BRANCH'; you are on $(git rev-parse --abbrev-ref HEAD)."
    fi
    if [ "$STASHED" = 1 ]; then
        printf '\n%srestoring stashed changes%s\n' "$C_DIM" "$C_OFF"
        git stash pop --quiet || warn "git stash pop failed — your changes are safe in 'git stash list'."
    fi
    if [ -S "$CTRL_PATH" ]; then "${SSH[@]}" -O exit >/dev/null 2>&1 || true; fi
    return $status
}
trap on_exit EXIT

rsh() { "${SSH[@]}" "$1"; }

SMOKE_ENV=()
build_smoke_env() {
    SMOKE_ENV=(SMOKE_PROD_BASE_URL="$DEPLOY_BASE_URL")
    if [ -n "$DEPLOY_API_KEY" ]; then
        SMOKE_ENV+=(SMOKE_PROD_CACHE_KEY="$DEPLOY_API_KEY")
    fi
    if [ -n "$DEPLOY_CANARY_COOKIE" ]; then
        SMOKE_ENV+=(SMOKE_PROD_CANARY_COOKIE="$DEPLOY_CANARY_COOKIE")
    fi
}

# ------------------------------------------------- making a swap visible ----
#
# Repointing the release symlink does not change what PHP executes. OPcache keys
# its compiled scripts on the path it resolved when it first saw them, and
# `opcache.revalidate_path` defaults to 0, so it never re-resolves the symlink;
# `validate_timestamps` then checks the OLD target's mtime, which never changes.
# The old release therefore keeps serving, indefinitely, with nothing in the
# response to say so.
#
# Worse, there is more than one cache. Polling /en/sm/cache-status on 2026-08-17
# returned three distinct uptimes, so this host runs at least three PHP pools,
# each with its own OPcache segment, and a request resets only the segment that
# served it. That is why this loops instead of firing once — and why "some pages
# work and some don't" was the symptom rather than a clean outage.
#
# The reset itself is a single-use PHP file with a random name written into the
# new release. A new path has no cache entry anywhere, so it always compiles from
# disk whichever segment picks it up, and its opcache_reset() clears that segment.
# An endpoint in the application cannot do this job: a pool serving the PREVIOUS
# release resolves the route against that release's code, which need not have the
# endpoint at all.
RESET_FILE=''
reset_opcode_caches() {
    local release_abs=$1 rel=$2 token i fresh=0 out
    token=$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')
    RESET_FILE="$release_abs/public/zz-opcache-$token.php"

    # The token is in the filename AND checked inside, so a guessed path is not
    # enough. It lives for the length of this step; on_exit removes it even if the
    # deploy dies in between, because a forgotten reset endpoint is a free
    # cache-flush for anyone who finds it.
    rsh "cat > $RESET_FILE" <<PHPEOF || { warn "could not write the opcache reset helper; skipping"; RESET_FILE=''; return 1; }
<?php
if (!hash_equals('$token', \$_GET['t'] ?? '')) { http_response_code(404); exit; }
header('Content-Type: text/plain');
\$s = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
echo 'age=', is_array(\$s) ? time() - \$s['opcache_statistics']['start_time'] : -1, "\n";
echo 'reset=', var_export(function_exists('opcache_reset') ? opcache_reset() : null, true), "\n";
PHPEOF

    # Stop once several consecutive hits all land on a just-reset segment; that is
    # the observable that every pool has been through this, without needing to know
    # how many pools there are. Capped so a host that reports no age cannot spin.
    for i in $(seq 1 40); do
        out=$(curl --silent --show-error --max-time 30 \
            "$DEPLOY_BASE_URL/zz-opcache-$token.php?t=$token" 2>/dev/null) || out=''
        case $out in
            *"age=-1"*) dim "OPcache is not enabled on this host; nothing to reset"; break ;;
            *reset=true*) ;;
            *) warn "opcache reset helper did not answer as expected on attempt $i"; break ;;
        esac
        # age is measured BEFORE the reset, so a small one means this segment was
        # already reset by an earlier iteration of this same loop.
        if [ "$(printf '%s' "$out" | sed -n 's/^age=//p')" -lt 30 ] 2>/dev/null; then
            fresh=$((fresh + 1))
            [ "$fresh" -ge 6 ] && break
        else
            fresh=0
        fi
    done

    rsh "rm -f $RESET_FILE" || warn "could not remove $RESET_FILE — delete it by hand."
    RESET_FILE=''
    ok "opcode caches reset (release $rel)"
}

# The check that would have stopped 2026-08-17 before it became an outage: ask the
# live site which release it is actually running, and do it enough times to sample
# more than one pool. /_health reports .revision when given the maintenance key,
# and it is served by the Symfony kernel without booting laminas — so it answers
# even when the legacy bootstrap is fatalling, which is exactly the state this is
# meant to detect.
assert_live_revision() {
    local want=$1 i got mismatched=0
    if [ -z "$DEPLOY_API_KEY" ]; then
        warn "DEPLOY_API_KEY is empty, so the live revision cannot be read."
        warn "Proceeding blind: a stale pool would not be detected. Set the key."
        return 0
    fi
    for i in $(seq 1 12); do
        got=$(curl --silent --show-error --max-time 30 \
            --header "X-Api-Key: $DEPLOY_API_KEY" "$DEPLOY_BASE_URL/_health" 2>/dev/null \
            | sed -n 's/.*"revision" *: *"\([0-9a-f]*\)".*/\1/p')
        if [ -z "$got" ]; then
            warn "/_health did not report a revision (attempt $i). Is the key right?"
            return 1
        fi
        if [ "$got" != "$want" ]; then
            warn "/_health reports $got, expected $want"
            mismatched=1
        fi
    done
    [ "$mismatched" = 0 ] || return 1
    ok "all 12 probes report $want"
}

confirm() {
    if [ "$ASSUME_YES" = 1 ]; then return 0; fi
    [ -t 0 ] || fail "not a tty and --yes was not given; refusing to $1 unattended."
    printf '\n%s? %s [y/N] %s' "$C_WARN" "$1" "$C_OFF"
    local answer
    read -r answer
    case $answer in [yY]*) return 0 ;; *) fail "cancelled." ;; esac
}

# --------------------------------------------------------- server layout ----

# Absolute paths, resolved once — rsync --link-dest needs one and the shell
# account's home is the only thing the relative paths above hang off.
remote_home() { rsh 'printf %s "$HOME"'; }

current_release() {
    # The live release id, read from the one symlink that constitutes the swap.
    rsh "readlink $APP/public 2>/dev/null || true" | sed -n 's#^releases/\([^/]*\)/public$#\1#p'
}

list_releases() {
    rsh "ls -1 $RELEASES 2>/dev/null || true" | sort
}

assert_bootstrapped() {
    local kind
    kind=$(rsh "if [ -L $APP/public ]; then echo symlink; elif [ -d $APP/public ]; then echo dir; else echo missing; fi")
    case $kind in
        symlink)
            if [ "$BOOTSTRAP" = 1 ]; then
                fail "--bootstrap was given but $APP/public is already a symlink; the server is converted. Deploy normally."
            fi
            ;;
        dir)
            [ "$BOOTSTRAP" = 1 ] || fail "$APP/public is still a real directory. The server has not been converted to the release layout yet — run tools/deploy-bootstrap.sh, then ./tools/deploy.sh --bootstrap (docs/DEPLOY.md § Cutover)."
            INITIAL_SWAP=1
            ;;
        *)  fail "$APP/public does not exist on the server. Something is very wrong; do not deploy." ;;
    esac
}

# ============================================================ --releases ====

if [ "$ACTION" = releases ]; then
    CURRENT=$(current_release)
    printf '%s\n' "releases on $DEPLOY_SSH_HOST:$APP"
    while read -r rel; do
        [ -n "$rel" ] || continue
        if [ "$rel" = "$CURRENT" ]; then
            printf '  %s%s  <- live%s\n' "$C_OK" "$rel" "$C_OFF"
        else
            printf '  %s\n' "$rel"
        fi
    done < <(list_releases)
    exit 0
fi

# ============================================================ --rollback ====

if [ "$ACTION" = rollback ]; then
    assert_bootstrapped
    CURRENT=$(current_release)
    if [ -n "$ROLLBACK_TO" ]; then
        TARGET=$ROLLBACK_TO
    else
        # The newest release *older* than the live one. Not simply "the newest
        # that is not live": after one rollback that would be the release you
        # just rolled back from, so a second --rollback would roll forward.
        TARGET=$(list_releases | awk -v cur="$CURRENT" '$0 < cur' | tail -1)
    fi
    [ -n "$TARGET" ] || fail "no other release on the server to roll back to."
    case $TARGET in
        *[!a-zA-Z0-9.-]*|'') fail "release id '$TARGET' has characters a release id cannot have." ;;
    esac
    rsh "test -d $RELEASES/$TARGET/public" || fail "release '$TARGET' does not exist on the server."

    info "live now: ${CURRENT:-<unknown>}"
    info "rolling back to: $TARGET"
    warn "This re-points code only — a database migration is NOT undone by it."
    warn "See docs/DEPLOY.md § Rollback."

    # "A migration is not undone" is not merely a caveat: for a migration that
    # DROPS something, it means the target release cannot run at all. On
    # 2026-08-17 a smoke failure triggered an automatic rollback into exactly that
    # state — the restored release went on SELECTing five columns db8.1 had just
    # removed, and every request that built navigation died as an empty 200. The
    # rollback was offered as the safe response and was the thing that made a
    # transient fault permanent.
    #
    # A migration declaring '@destructive: yes' says older code cannot survive it.
    # The test is file presence rather than the ledger: a migration ships in the
    # same commit as the code that copes with it, so a release without the file is
    # a release from before the change. That over-refuses when a destructive
    # migration has been shipped but not yet applied — deliberately, because the
    # cost of over-refusing is one flag and the cost of under-refusing is an outage.
    if [ -n "$CURRENT" ]; then
        BLOCKERS=$(rsh "cd $RELEASES/$CURRENT/database 2>/dev/null && for f in \$(grep -l '@destructive: *yes' *.sql 2>/dev/null); do [ -f $RELEASES/$TARGET/database/\$f ] || echo \$f; done" || true)
        if [ -n "$BLOCKERS" ]; then
            warn "$TARGET predates these destructive migration(s):"
            printf '        %s\n' $BLOCKERS >&2
            if [ "$ALLOW_INCOMPATIBLE" = 1 ]; then
                warn "--allow-incompatible given: rolling back anyway. Expect fatals."
            else
                fail "refusing to roll back into a schema that release cannot run against.

  The database still has those migrations applied, and $TARGET does not ship them,
  so it is from before the code that copes with the change. Rolling back would not
  restore service — it would replace one broken state with a differently broken one,
  and the last time that happened it read as 'the rollback did not help'.

  What to do instead, in order of preference:
    1. Roll FORWARD to a release that ships them:
         ./tools/deploy.sh --rollback --to <newer-release>
       (--releases lists them; the newest is usually the one that just failed, and
       its failure may have been the swap not being visible rather than the code.)
    2. Fix forward with a new deploy.
    3. Only if you have decided the data loss is acceptable and understand what
       breaks: ./tools/deploy.sh --rollback --to $TARGET --allow-incompatible"
            fi
        fi
    fi

    confirm "roll back to $TARGET"

    step "Swapping the symlink"
    rsh "cd $APP && ln -sfn releases/$TARGET/public public.swap && mv -Tf public.swap public"
    ok "live release is now $TARGET"

    step "Flushing the persistent cache"
    rsh "cd $RELEASES/$TARGET && php bin/console cache:flush-persistent" || warn "flush failed; the site is rolled back but APCu may hold stale entries."

    step "Making the swap visible to PHP"
    # A rollback is a symlink swap, so it is invisible to OPcache for exactly the
    # same reason a deploy is. Without this the rollback appears to do nothing.
    # home-relative, like the rest of this block: HOME_ABS is not resolved until the
    # deploy path, well below here, and rsh lands in the home directory anyway.
    reset_opcode_caches "$RELEASES/$TARGET" "$TARGET" || true

    step "Smoke checks"
    build_smoke_env
    env "${SMOKE_ENV[@]}" bash tools/smoke-prod.sh
    exit $?
fi

# ============================================================== preflight ===

START_TS=$SECONDS

step "Preflight"

ORIG_BRANCH=$(git rev-parse --abbrev-ref HEAD)

# Dirty-tree handling comes first: checking out --ref with a dirty tree either
# fails or drags the changes along, and neither is a state to deploy from.
# Submodule *dirtiness* is reported separately below with a better message;
# --ignore-submodules=dirty still surfaces an uncommitted pointer bump, which
# is a real superproject change and must block a deploy.
DIRTY=$(git status --porcelain --ignore-submodules=dirty)
if [ -n "$DIRTY" ]; then
    if [ "$DO_STASH" = 1 ]; then
        info "working tree is dirty; stashing:"
        printf '%s\n' "$DIRTY" | sed 's/^/      /'
        git stash push --include-untracked --quiet \
            --message "deploy.sh autostash $(date +%Y-%m-%dT%H:%M:%S)"
        # on_exit pops it on any exit, success or failure — a stash left parked
        # is how a stash gets forgotten and later lost.
        STASHED=1
        ok "stashed"
    else
        printf '%s\n' "$DIRTY" | sed 's/^/      /'
        fail "working tree is dirty. Commit it, or re-run with --stash."
    fi
fi

if [ -n "$GIT_REF" ]; then
    warn "deploying '$GIT_REF' rather than master — this is not a normal deploy."
    git rev-parse --verify "$GIT_REF" >/dev/null 2>&1 || fail "ref '$GIT_REF' does not exist."
    git checkout --quiet "$GIT_REF"
    CHECKED_OUT_REF=1   # on_exit puts the branch back
elif [ "$ORIG_BRANCH" != master ]; then
    fail "on branch '$ORIG_BRANCH', not master. Use --ref '$ORIG_BRANCH' if you really mean to deploy it."
fi

if [ -z "$GIT_REF" ]; then
    git fetch --quiet origin master
    BEHIND=$(git rev-list --count HEAD..origin/master)
    AHEAD=$(git rev-list --count origin/master..HEAD)
    if [ "$AHEAD" != 0 ]; then
        fail "local master is $AHEAD commit(s) ahead of origin/master. Push before deploying, or the deployed code exists nowhere but this machine."
    fi
    if [ "$BEHIND" != 0 ]; then
        info "fast-forwarding master ($BEHIND commit(s) behind origin)"
        git pull --ff-only --quiet origin master
    fi
fi

# Submodules, in the order the failures matter.
#
# The pointer check is the one that would ship the wrong code: the release is
# built from `git ls-files --recurse-submodules`, which reads the submodule
# *working trees*, so a submodule sitting at a different commit than the
# superproject records means the release contains code no commit describes. A
# `git pull` that moves a pointer produces exactly that state.
#
# This deliberately does NOT run `git submodule update` to repair it. That
# detaches HEAD, and the convention here is that the submodules stay checked
# out on `modernization` (CLAUDE.md § Submodule workflow) — silently changing
# the user's local state to make a deploy proceed is the wrong trade. Say what
# is wrong and let them fix it the documented way.
while read -r flag sha path _rest; do
    [ -n "$path" ] || continue
    case $flag in
        ahead)    fail "$path is checked out at $(git -C "$path" rev-parse --short HEAD) but the superproject pins ${sha:0:7}. The release is built from the submodule working tree, so this would ship code no commit describes. Fix it on 'modernization' (see CLAUDE.md § Submodule workflow) — do not run 'git submodule update', which detaches." ;;
        uninit)   fail "$path is not initialized. Run: git submodule update --init --recursive" ;;
        conflict) fail "$path has merge conflicts." ;;
    esac
    [ -z "$(git -C "$path" status --porcelain)" ] \
        || fail "$path has uncommitted changes. Deploy only committed state."
    pinned=$(git -C "$path" rev-parse HEAD)
    git -C "$path" fetch --quiet origin || warn "could not fetch $path; the pushed-check below may be stale."
    if [ -z "$(git -C "$path" branch -r --contains "$pinned" 2>/dev/null)" ]; then
        fail "$path is pinned at $(git -C "$path" rev-parse --short HEAD), which is not on any remote branch. Push it first — an unpushed pointer breaks composer install for everyone else."
    fi
# `git submodule status` encodes state as a single leading character, and a
# clean submodule's is a space — which `read` would swallow, shifting every
# field left. Name it instead.
done < <(git submodule status | sed -e 's/^ /clean /' -e 's/^+/ahead /' \
                                    -e 's/^-/uninit /' -e 's/^U/conflict /')
ok "submodules clean, pinned where the superproject says, and pushed"

SHA=$(git rev-parse HEAD)
SHORT=$(git rev-parse --short HEAD)
SUBJECT=$(git log -1 --pretty=%s)
ok "HEAD $SHORT — $SUBJECT"

if [ "$DRY_RUN" = 1 ] && [ "$SKIP_TESTS" = 0 ]; then
    SKIP_TESTS=1
    dim "--dry-run: skipping tools/ci-local.sh; run it directly for a full rehearsal"
fi

if [ "$SKIP_TESTS" = 1 ]; then
    warn "skipping tools/ci-local.sh (--skip-tests)"
else
    step "Verification (tools/ci-local.sh --ci)"
    dim "lint, composer --no-dev rehearsal, PHPStan level 0, unit, integration"
    bash tools/ci-local.sh --ci || fail "verification failed. Fix it, or re-run with --skip-tests if you know why."
    ok "all five CI jobs green"
fi

# ============================================================ build =========

step "Release plan"

assert_bootstrapped
HOME_ABS=$(remote_home)
PREV=$(current_release)
REL="$(date +%Y%m%d-%H%M%S)-$SHORT"
NEW=$RELEASES/$REL
NEW_ABS=$HOME_ABS/$NEW

info "server:   $DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$APP"
info "live now: ${PREV:-<none>}"
info "building: $REL"

RSYNC_LINK=()
if [ -n "$PREV" ]; then
    RSYNC_LINK=(--link-dest="$HOME_ABS/$RELEASES/$PREV")
    dim "unchanged files hardlink to the previous release (nothing writes to tracked files at runtime)"
fi

# The release is exactly the committed tree — the file list comes from git, not
# from the working directory, so untracked local cruft (a stale sitemap, a
# scratch dump, an editor backup) can never reach production.
FILE_LIST=$(mktemp "${TMPDIR:-/tmp}/deploy-files-XXXXXX")
git ls-files --recurse-submodules -z > "$FILE_LIST"
FILE_COUNT=$(tr -cd '\0' < "$FILE_LIST" | wc -c)
info "$FILE_COUNT tracked files (superproject + three submodules)"

if [ "$DRY_RUN" = 1 ]; then
    step "Dry run — what would transfer"
    rsync -a --files-from="$FILE_LIST" --from0 "${RSYNC_LINK[@]}" \
        --dry-run --stats -e "ssh -o ControlPath=$CTRL_PATH -p $DEPLOY_SSH_PORT" \
        ./ "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$NEW_ABS/" | sed -n '/^Number of files/,/^Total bytes/p' | sed 's/^/    /'
    printf '\n%sDry run complete. The server was not modified.%s\n' "$C_DIM" "$C_OFF"
    exit 0
fi

confirm "deploy $SHORT to $DEPLOY_BASE_URL"

step "Backing up the live public/.htaccess"
# Tracked, and therefore replaced by every release. The backup is the escape
# hatch for an emergency hand-edit on the server; it lives outside any release.
rsh "cd $APP && mkdir -p shared/data/htaccess-backups && cp -a public/.htaccess shared/data/htaccess-backups/htaccess-$(date +%Y%m%d-%H%M%S)"
ok "saved to shared/data/htaccess-backups/ (reachable as data/htaccess-backups/ inside every release)"

step "Building release $REL"
rsh "mkdir -p $NEW_ABS"
rsync -a --files-from="$FILE_LIST" --from0 "${RSYNC_LINK[@]}" \
    -e "ssh -o ControlPath=$CTRL_PATH -p $DEPLOY_SSH_PORT" \
    ./ "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$NEW_ABS/"
ok "tree uploaded"

printf '%s\n' "$SHA" | rsh "cat > $NEW_ABS/.revision"
dim ".revision written — SionModel\\Error\\RequestContext reads it, so every recorded"
dim "exception names the release that produced it (immutable now, unlike phploy's)"

step "Linking shared state into the release"
# Data-driven on purpose: anything dropped into shared/data/ or shared/public/
# is linked into every future release without editing this script. That is also
# how server-only docroot files (site-verification files, one-off scripts) are
# kept — see docs/DEPLOY.md § Server-only files.
rsh "
set -e
cd $HOME_ABS/$APP
# rsync creates only the directories that hold tracked files, so these three
# may not exist yet — data/ in particular is almost entirely gitignored.
mkdir -p releases/$REL/data releases/$REL/public releases/$REL/config/autoload

# A shared entry whose name collides with tracked content is refused rather than
# linked: 'ln -sfn' onto an existing directory creates the link *inside* it, so
# the release would silently get releases/X/data/foo/foo and the real directory
# would go on being used. That is how data/publications — tracked repo content —
# would have broken had it been listed as shared.
link_shared() {
    target=\$1; linkdir=\$2; up=\$3
    name=\$(basename \"\$target\")
    if [ -e \"\$linkdir/\$name\" ] && [ ! -L \"\$linkdir/\$name\" ]; then
        echo \"REFUSING to link \$target: \$linkdir/\$name already exists as tracked content\" >&2
        return 1
    fi
    ln -sfn \"\$up\$target\" \"\$linkdir/\$name\"
}

for d in shared/data/*; do
    [ -e \"\$d\" ] || continue
    link_shared \"\$d\" releases/$REL/data ../../../
done
for d in shared/public/*; do
    [ -e \"\$d\" ] || continue
    link_shared \"\$d\" releases/$REL/public ../../../
done
# Both patterns, because laminas' autoload glob is {,*.}local.php and '*.local.php'
# does NOT match 'local.php' — which is the file holding the database credentials.
# Matching only the first pattern skips it silently and the swap takes the site down.
for f in shared/config-autoload/local.php shared/config-autoload/*.local.php; do
    [ -e \"\$f\" ] || continue
    link_shared \"\$f\" releases/$REL/config/autoload ../../../../
done
# Per-release, deliberately not shared: a new tree must never meet a merged
# config cache built from the old one. That pairing is what produced the
# 'A plugin by the name \"requestUri\" was not found' burst on every deploy.
mkdir -p releases/$REL/data/config releases/$REL/data/cache/twig
"
ok "shared data, media and *.local.php linked; caches are per-release"

step "composer install --no-dev"
# A full copy rather than cp -al: composer may rewrite files in place, and a
# hardlink shared with the previous release would corrupt the rollback target.
if [ -n "$PREV" ]; then
    rsh "cp -a $HOME_ABS/$RELEASES/$PREV/vendor $NEW_ABS/vendor 2>/dev/null || true"
    dim "seeded vendor/ from $PREV; install below is a no-op when the lock is unchanged"
elif [ "$INITIAL_SWAP" = 1 ]; then
    rsh "cp -a $HOME_ABS/$APP/vendor $NEW_ABS/vendor 2>/dev/null || true"
    dim "seeded vendor/ from the flat tree being replaced"
fi
rsh "cd $NEW_ABS && php composer.phar install --no-dev --no-interaction --optimize-autoloader" \
    || fail "composer install failed in the new release. Nothing was swapped; the site is untouched."
ok "dependencies installed"

step "Pre-deploy migrations"
# Against the live database while the OLD code is still serving, which is what
# 'pre' means: the schema must be ready before the code that reads it arrives.
# A failure here aborts before the swap, so production keeps running unchanged.
bash tools/migrate.sh apply --phase=pre --yes \
    || fail "a pre-deploy migration failed. Nothing was swapped; production is still on ${PREV:-the previous release} and its data is unchanged (a dml migration rolls back whole). Read the error above and data/deploy/backups/."

step "Warming the release (still not serving)"
# Every one of these used to run *after* the code went live, which is why a
# deploy had a stale window: English strings until the catalogs rebuilt, a
# sitemap from the previous release, a cold config cache for the first visitor.
rsh "cd $NEW_ABS && php bin/console jtranslate:export-catalogs" \
    || warn "jtranslate:export-catalogs failed — translations are safe in the database, but this release's catalogs are stale and some strings will render in English."
rsh "cd $NEW_ABS && php bin/console sitemap:build --force" \
    || warn "sitemap:build failed — this release ships without sitemap files until the cron rebuilds them."
ok "catalogs, merged config and sitemap built"

# ============================================================== the swap ====

step "Swapping"
SWAP_TS=$SECONDS
if [ "$INITIAL_SWAP" = 1 ]; then
    # The one non-atomic moment in the whole design, and it happens exactly once:
    # a real directory cannot be replaced by a symlink with rename(2). The old
    # docroot is kept as public.pre-atomic, so the way back is a single mv.
    warn "initial swap: public/ is a real directory, so this is a mv + ln, not a rename."
    warn "There is a sub-second window here. It is the only one, and it never recurs."
    rsh "cd $HOME_ABS/$APP && mv public public.pre-atomic && ln -sfn releases/$REL/public public"
    ok "live release is now $REL; previous docroot kept as public.pre-atomic"
    dim "way back: mv public public.symlink && mv public.pre-atomic public"
else
    rsh "cd $HOME_ABS/$APP && ln -sfn releases/$REL/public public.swap && mv -Tf public.swap public"
    ok "live release is now $REL (one rename(2); no window)"
fi

step "Post-swap"
# APCu belongs to the SAPI that created it, so this has to be an HTTP request
# into the web server — a CLI apcu_clear_cache() flushes a segment nobody reads.
rsh "cd $NEW_ABS && php bin/console cache:flush-persistent" \
    || warn "persistent-cache flush failed; APCu may serve stale navigation branches. Re-run: php bin/console cache:flush-persistent"

# There used to be a second hook here: a curl to /en/associations/do-work, which
# ran autoFillTimeZones() and updateAssociationMd5s(). Both were retired 2026-08-17
# because both were dead work. The time-zone sweep filled 0 rows (every candidate
# is in a multi-zone country or one with no zone list) and returned void, so the
# key it reported under was always null. The md5 sweep wrote five columns that
# nothing read — the sole consumer outside the model was a commented-out line —
# and it existed to repair a write-path bug that wrote the same digest to all five.
# See docs/DEPLOY.md § What the deploy no longer does.
if [ -z "$DEPLOY_API_KEY" ]; then
    warn "DEPLOY_API_KEY is empty — skipping the cache-status smoke checks."
fi

step "Making the swap visible to PHP"
reset_opcode_caches "$NEW_ABS" "$REL"

step "Confirming the live site is running $REL"
assert_live_revision "$SHA" \
    || fail "the live site is NOT running the release that was just swapped in.

  Nothing irreversible has happened yet: the symlink points at $REL, no post-deploy
  migration has run, and the previous release is untouched. This is the check that
  exists because on 2026-08-17 it did not: the swap succeeded, a migration dropped
  five columns, and three OPcache pools went on serving the PREVIOUS release, which
  could not run against the new schema. 449 fatals, and the automatic rollback made
  it permanent by restoring the very release that was broken.

  Read docs/incident-2026-08-17-stale-opcache.md, then either re-run the deploy or
  reset the caches by hand. Do NOT run migrations until /_health reports $SHA."

step "Post-deploy migrations"
# The code is live, which is what 'post' is for: every db7.x retirement had to
# run after its fix, because phrase discovery un-retires whatever the site still
# looks up. A failure here does NOT roll the symlink back — the code is fine and
# reverting it would not undo a data change either way.
POST_MIGRATIONS_FAILED=0
bash tools/migrate.sh apply --phase=post --yes || POST_MIGRATIONS_FAILED=1

# ================================================================ verify ====

step "Smoke checks against the live site"
build_smoke_env
if env "${SMOKE_ENV[@]}" bash tools/smoke-prod.sh; then
    ok "smoke checks passed"
else
    printf '\n%sSMOKE CHECKS FAILED%s\n' "$C_ERR" "$C_OFF" >&2
    # Whether rolling back is the safe move depends on what already ran. If a
    # destructive migration applied in THIS deploy is absent from the previous
    # release, that release cannot run against the schema it would return to, and
    # rolling back trades a possibly-partial failure for a certain one. That is the
    # 2026-08-17 sequence exactly, and the automatic rollback is what made it stick.
    ROLLBACK_BLOCKERS=''
    if [ -n "$PREV" ]; then
        ROLLBACK_BLOCKERS=$(rsh "cd $RELEASES/$REL/database 2>/dev/null && for f in \$(grep -l '@destructive: *yes' *.sql 2>/dev/null); do [ -f $RELEASES/$PREV/database/\$f ] || echo \$f; done" || true)
    fi
    if [ -n "$ROLLBACK_BLOCKERS" ]; then
        warn "NOT rolling back automatically. $PREV predates these destructive migration(s):"
        printf '        %s\n' $ROLLBACK_BLOCKERS >&2
        warn "and they are already applied, so $PREV cannot run against this schema."
        warn "The site stays on $REL, which at least matches the database."
        info ""
        info "Diagnose before acting. If smoke reported zero-byte 200s, read"
        info "docs/incident-2026-08-17-stale-opcache.md first — a stale opcode cache"
        info "looks exactly like broken code and is fixed by re-running the deploy."
        info "To override once you have decided: ./tools/deploy.sh --rollback --to $PREV --allow-incompatible"
    elif [ -n "$PREV" ]; then
        printf '%srolling back automatically%s\n' "$C_ERR" "$C_OFF" >&2
        rsh "cd $HOME_ABS/$APP && ln -sfn releases/$PREV/public public.swap && mv -Tf public.swap public"
        rsh "cd $HOME_ABS/$RELEASES/$PREV && php bin/console cache:flush-persistent" || true
        reset_opcode_caches "$HOME_ABS/$RELEASES/$PREV" "$PREV" || true
        warn "rolled back to $PREV. The failed release is kept at $RELEASES/$REL for inspection."
        warn "No database migration was undone by this rollback."
    elif [ "$INITIAL_SWAP" = 1 ]; then
        rsh "cd $HOME_ABS/$APP && rm -f public && mv public.pre-atomic public"
        warn "restored the pre-atomic docroot. The server is back on the flat layout,"
        warn "exactly as it was before this run; the release is kept at $RELEASES/$REL."
    else
        warn "no previous release to roll back to — the site is on $REL and smoke is failing."
    fi
    exit 1
fi

# ================================================================ finish ====

step "Housekeeping"
git tag -f "deploy/$(date +%Y%m%d-%H%M)" >/dev/null
dim "tagged deploy/$(date +%Y%m%d-%H%M) locally (never pushed; 'git tag -l deploy/*' answers what shipped)"

# Prune, but never the live release and never the one rollback would reach for.
KEPT=$(rsh "
cd $HOME_ABS/$RELEASES
ls -1dt */ 2>/dev/null | sed 's#/\$##' | tail -n +$((DEPLOY_KEEP_RELEASES + 1)) | while read -r old; do
    [ \"\$old\" = '$REL' ] && continue
    [ \"\$old\" = '${PREV:-}' ] && continue
    rm -rf -- \"\$old\" && echo \"\$old\"
done
")
if [ -n "$KEPT" ]; then
    dim "pruned: $(printf '%s' "$KEPT" | tr '\n' ' ')"
fi

ELAPSED=$((SECONDS - START_TS))
printf '\n%s✓ %s live at %s — %ds total, swap at %ds%s\n' \
    "$C_OK" "$REL" "$DEPLOY_BASE_URL" "$ELAPSED" "$SWAP_TS" "$C_OFF"
printf '  %srollback: ./tools/deploy.sh --rollback%s\n' "$C_DIM" "$C_OFF"

if [ "$POST_MIGRATIONS_FAILED" = 1 ]; then
    printf '\n'
    warn "The code deployed and the site is healthy, but a POST-deploy migration failed."
    warn "That is a data step, not a code one — rolling back the release would not undo it"
    warn "and would not fix it. Read the error above, then:"
    warn "  ./tools/migrate.sh status      what applied and what did not"
    warn "  data/deploy/backups/           the snapshot taken before it ran"
    exit 1
fi
