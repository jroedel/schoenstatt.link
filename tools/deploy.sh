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
#   -y, --yes      no confirmation prompt (a routine deploy already asks nothing;
#                  this forces past the prompt an UNUSUAL run would raise)
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

# Reasons this run is not a routine deploy, appended to as they are discovered.
# An empty list is what lets the deploy proceed without a confirmation prompt, so
# anything added here is a claim that a human should look before production
# changes. Declared before the config is read, because the first entry can come
# from there.
UNUSUAL=()
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
    *) warn ".deploy.local is mode $CONFIG_MODE; it holds credentials. chmod 600 it."
       UNUSUAL+=(".deploy.local is mode $CONFIG_MODE, not 600 — it holds the DDL password") ;;
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

# ------------------------------------------------------- not hanging forever ----
#
# Until 2026-08-18 there was no timeout of any kind on any of the ~40 remote calls,
# no keepalive, and no output at all while one was in flight. So a stalled step and
# a slow step looked identical from the outside, and the operator's only signal was
# a cursor that had stopped moving. It happened on "Warming the release", to a
# deploy of a commit that had warmed fine nine minutes earlier — the two commands in
# that step take under two seconds between them, so it was never the work.
#
# Three defences, because they catch different failures and none of them subsumes
# the others:
#
#   1. **ssh keepalives** notice a dead network path — the connection the local end
#      still believes in and the far end has forgotten. ssh then exits instead of
#      blocking on a socket nothing will ever answer.
#   2. **`timeout`** bounds a call whose connection is perfectly healthy but whose
#      remote command is stuck: a lock wait, a full disk, a process nothing will
#      wake. Keepalives cannot see this — the channel is fine, it is the far end
#      that is not coming back.
#   3. **A heartbeat** prints elapsed seconds while a call is in flight, so a slow
#      step announces itself instead of being indistinguishable from a wedged one.
#      This is the one that would have answered the question on the day.

# GNU coreutils has `timeout`; macOS has it only as `gtimeout`, and only if
# coreutils is installed. Absent both, the deploy still runs and loses defence 2 —
# said out loud at preflight rather than degrading silently, because "no timeout"
# is exactly the condition this block exists to make visible.
# >>> remote-call machinery — test/Deploy/rsh-behaviour-test.sh extracts everything
# between these two markers and drives it with a local stand-in for ssh. Keep the
# block self-contained: it may use `warn` and the colour variables, and nothing else
# from this script.
TIMEOUT_BIN=''
if command -v timeout >/dev/null 2>&1; then
    TIMEOUT_BIN=timeout
elif command -v gtimeout >/dev/null 2>&1; then
    TIMEOUT_BIN=gtimeout
fi

# Seconds before a remote command is presumed hung. Generous on purpose: this is
# meant to bound a stall, not to police a slow server. The handful of calls that
# legitimately take minutes set their own with `RSH_TIMEOUT=<seconds> rsh ...`.
RSH_DEFAULT_TIMEOUT=180

# Silence before the heartbeat speaks, and the gap between beats after that. The
# first beat is late enough that the dozens of sub-second calls stay quiet.
RSH_HEARTBEAT_AFTER=15
RSH_HEARTBEAT_EVERY=15

# Not gated on stderr being a terminal: when a deploy is piped to a log, a beat
# every 15 seconds is precisely the record you want when reading back to find out
# where it stopped.
HEARTBEAT_PID=''
heartbeat_start() {
    local label=$1 budget=$2
    (
        local waited=$RSH_HEARTBEAT_AFTER
        sleep "$RSH_HEARTBEAT_AFTER"
        while :; do
            printf '%s      … still waiting on %s — %ds of %ds%s\n' \
                "$C_DIM" "$label" "$waited" "$budget" "$C_OFF" >&2
            sleep "$RSH_HEARTBEAT_EVERY"
            waited=$((waited + RSH_HEARTBEAT_EVERY))
        done
    ) &
    HEARTBEAT_PID=$!
}
heartbeat_stop() {
    if [ -n "$HEARTBEAT_PID" ]; then
        kill "$HEARTBEAT_PID" 2>/dev/null || true
        wait "$HEARTBEAT_PID" 2>/dev/null || true
        HEARTBEAT_PID=''
    fi
}

# Every remote call goes through here, which is why the timeout lives here and not
# at the call sites: a protection that has to be remembered is one that will be
# forgotten by the fortieth call.
#
# ssh runs in the FOREGROUND and the heartbeat is the background job, not the other
# way round. Two reasons: a backgrounded ssh cannot prompt for the key passphrase —
# it would be stopped on SIGTTIN instead, which looks exactly like the hang this
# block exists to prevent — and it would stop receiving Ctrl-C from the terminal, so
# an interrupted deploy would leave the remote command running.
#
# A third reason was written here first and is false, which is worth leaving on the
# record because it is the one everybody reaches for: *"a background command in a
# non-interactive shell has its stdin reassigned to /dev/null"*. Measured on bash
# 5.2 — `printf x | { cat > f & wait $!; }` writes x. The POSIX sentence people
# quote does not bite when stdin is an explicit redirection, so backgrounding would
# NOT have corrupted the two piped call sites. It is still not worth doing, because
# `timeout` already bounds the call and backgrounding buys nothing.
#
# What genuinely would break those two sites — the .revision write and the
# opcache-helper distribution — is anything that takes stdin away: `ssh -n`, a
# `< /dev/null` on the command, or a redirect added inside this function. An empty
# .revision would then be blamed on the server by the revision gate downstream.
# test/Deploy/rsh-behaviour-test.sh covers both that wiring and the absence of -n.
#
# Six call sites capture stdout with $(rsh ...), so the heartbeat writes to stderr.
rsh() {
    local budget=${RSH_TIMEOUT:-$RSH_DEFAULT_TIMEOUT}
    local label=${RSH_LABEL:-remote command}
    local status=0

    heartbeat_start "$label" "$budget"
    if [ -n "$TIMEOUT_BIN" ]; then
        "$TIMEOUT_BIN" "${budget}s" "${SSH[@]}" "$1" || status=$?
    else
        "${SSH[@]}" "$1" || status=$?
    fi
    heartbeat_stop

    # 124 is `timeout` reporting that it killed the call. Worth separating from the
    # remote command's own non-zero exit, because the two mean opposite things: a
    # normal failure is the server telling you something, a 124 is the server
    # telling you nothing at all, and only the second one implicates the deploy
    # rather than the release.
    if [ "$status" = 124 ]; then
        warn "no response from the server after ${budget}s — $label. The connection was open; the remote command did not return. Nothing later in this deploy has run."
    fi
    return $status
}
# <<< remote-call machinery

# One multiplexed connection for the whole run: a deploy makes a dozen ssh
# calls and the key needs its passphrase entered at most once.
#
# ServerAliveInterval x ServerAliveCountMax is the dead-path budget (~60s).
# ConnectTimeout bounds only the initial handshake, which is a different failure
# again — an unreachable host rather than one that stops replying mid-call.
CTRL_PATH=$(mktemp -u "${TMPDIR:-/tmp}/deploy-ssh-XXXXXX")
SSH_KEEPALIVE=(-o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4)
SSH=(ssh -o ControlMaster=auto -o ControlPath="$CTRL_PATH" -o ControlPersist=120
     "${SSH_KEEPALIVE[@]}"
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
    # First, before anything prints: a heartbeat that outlives the script would keep
    # talking over whatever the shell says next, and Ctrl-C reaches here with one
    # still running by definition.
    heartbeat_stop
    if [ -n "$FILE_LIST" ]; then rm -f "$FILE_LIST"; fi
    # A reset helper left on the server is a publicly reachable cache flush, so it
    # goes even if the deploy died mid-step. RESET_FILE is cleared by the happy
    # path, so this only fires when something went wrong.
    if [ -n "${RESET_FILE:-}" ]; then
        # 20s, not the default 180: this runs on the way out, often after Ctrl-C, and
        # a cleanup that hangs is how a hang gets blamed on the wrong step.
        RSH_TIMEOUT=20 RSH_LABEL="removing the opcache reset helper" \
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
#
# One reading this reset destroys, taken on the way past. The interned-strings
# buffer is append-only — nothing is ever evicted, so its usage only climbs
# within a segment's life and the opcache_reset() below puts it back to zero.
# That makes the moment before a reset the only time a *warm* number can be
# observed, and a deploy is the one occasion something already reaches every
# pool. It costs no extra request: the helper had to read the status anyway to
# print `age=`. Without this, the figure that decides whether
# opcache.interned_strings_buffer is big enough could only ever be measured by
# someone polling by hand at the right moment — and after a deploy there is no
# right moment for hours.
# >>> interned sampling
WARM_INTERNED=''
sample_interned() {
    local body=$1 age line used buf strings rendered

    age=$(printf '%s' "$body" | sed -n 's/^age=//p')
    line=$(printf '%s' "$body" | sed -n 's|^interned=||p')
    case $line in ''|'-') return 0 ;; esac
    # Younger than five minutes means this loop already reset it, or the pool is
    # newly started. Either way it is a cold number and reporting it would invite
    # exactly the misreading this exists to prevent.
    [ "${age:-0}" -ge 300 ] 2>/dev/null || return 0

    used=${line%%/*}; line=${line#*/}
    buf=${line%%/*}; strings=${line##*/}
    [ "${buf:-0}" -gt 0 ] 2>/dev/null || return 0

    rendered=$(awk -v a="$age" -v u="$used" -v b="$buf" -v s="$strings" \
        'BEGIN { printf "after %5.1fh alive: %5.1f%% of %dMB used, %d strings", a/3600, 100*u/b, b/1048576, s }')
    # Deduplicated on the rendered line, which carries the age — two pools would
    # have to agree to a tenth of an hour AND on every figure to collapse into one.
    case $WARM_INTERNED in *"$rendered"*) return 0 ;; esac
    WARM_INTERNED="${WARM_INTERNED}${rendered}"$'\n'
}
# <<< interned sampling

RESET_FILE=''
reset_opcode_caches() {
    local release_abs=$1 rel=$2 want=${3:-} token src url i fresh=0 agreed=0 misses=0 body status live

    WARM_INTERNED=''
    token=$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')
    # A glob, because the helper is written into EVERY release, not just this one.
    # Which directory the web server resolves the docroot to is not something this
    # script should have to be right about, and on 2026-08-17 being wrong about it
    # cost an afternoon. One 400-byte file per release removes the question.
    RESET_FILE="$RELEASES/*/public/zz-opcache-$token.php"
    url="$DEPLOY_BASE_URL/zz-opcache-$token.php?t=$token"

    # Quoted heredoc, then substitute the token with a bash expansion. The point is
    # that the shell never interprets this PHP: `$_GET`, `$s` and the rest arrive
    # exactly as written, with no backslash-escaping to get right by hand.
    #
    # An earlier version used an unquoted heredoc so the token could interpolate,
    # which forced `\$s` escaping throughout. That was suspected of shipping a parse
    # error and was NOT guilty — the bytes it produced were checked afterwards and
    # lint clean. It is written this way because it is easier to read and impossible
    # to get subtly wrong, not because the other way was broken. A hand-written
    # helper during the 2026-08-17 incident *did* ship a parse error exactly this
    # way, and 30 requests to the resulting 500 were mistaken for 30 successful
    # resets — which is the real argument for not hand-escaping anything here.
    src=$(cat <<'PHPEOF'
<?php
if (!hash_equals('__TOKEN__', $_GET['t'] ?? '')) { http_response_code(404); exit; }
header('Content-Type: text/plain');
$s = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
echo 'age=', is_array($s) ? time() - $s['opcache_statistics']['start_time'] : -1, "\n";
$i = is_array($s) && isset($s['interned_strings_usage']) ? $s['interned_strings_usage'] : null;
echo 'interned=', is_array($i) ? $i['used_memory'] . '/' . $i['buffer_size'] . '/' . $i['number_of_strings'] : '-', "\n";
echo 'reset=', var_export(function_exists('opcache_reset') ? opcache_reset() : null, true), "\n";
echo 'from=', __DIR__, "\n";
PHPEOF
    )
    # Sent as plain text on stdin, deliberately. An earlier version base64'd it,
    # on a suspicion that shell quoting was corrupting the PHP — that suspicion was
    # measured and found false, and encoding it made the one thing this script
    # writes to a production docroot unreadable both in the script and in `ps` on
    # the server. Anything a deploy executes remotely should be legible to whoever
    # is reading the deploy at 2am; the exact bytes are the heredoc directly above.
    # Piping to `cat` over ssh is already binary-safe.
    printf '%s\n' "${src//__TOKEN__/$token}" \
        | rsh "d=\$(mktemp) && cat > \$d && for r in $RELEASES/*/public; do cp \$d \$r/zz-opcache-$token.php; done && rm -f \$d" \
        || { warn "could not write the opcache reset helper"; RESET_FILE=''; return 1; }

    # Wait for the web server to SEE it before judging a 404. This is the whole
    # lesson of the 14:33 deploy: the helper was written and requested inside four
    # seconds, answered "No input file specified", and three retries two seconds
    # apart were still far too eager. Six minutes later the identical file at the
    # identical path served every one of 25 requests. A freshly created file is not
    # instantly visible to Apache here, and no amount of cleverness substitutes for
    # waiting — so wait, visibly, and only then start resetting.
    local waited=0
    until curl --silent --show-error --max-time 15 -o /dev/null -w '%{http_code}' "$url" 2>/dev/null | grep -q '^200$'; do
        waited=$((waited + 3))
        if [ "$waited" -gt 60 ]; then
            warn "the reset helper never became reachable after ${waited}s."
            warn "Last response:"
            printf '        %s\n' "$(curl --silent --max-time 15 -w ' [http %{http_code}]' "$url" 2>&1 | head -c 200 | tr '\n' '|')" >&2
            rsh "rm -f $RESET_FILE" || warn "could not remove $RESET_FILE — delete by hand."
            RESET_FILE=''
            return 1
        fi
        sleep 3
    done
    [ "$waited" -gt 0 ] && dim "helper became reachable after ${waited}s"

    # Stop once several consecutive hits all land on an already-fresh segment: that
    # is the observable for "every pool has been through this", without needing to
    # know how many pools there are. Capped so a host that reports no age cannot spin.
    for i in $(seq 1 40); do
        body=$(curl --silent --show-error --max-time 30 -w '\n%{http_code}' "$url" 2>/dev/null) || body=$'\n000'
        status=${body##*$'\n'}
        body=${body%$'\n'*}
        case $body in
            *"age=-1"*)
                dim "OPcache is not enabled on this host; nothing to reset"
                rsh "rm -f $RESET_FILE" || warn "could not remove $RESET_FILE — delete it by hand."
                RESET_FILE=''
                return 0
                ;;
            *reset=true*) ;;
            *)
                # Say what actually came back, and do not give up on one bad read.
                #
                # The previous version printed "did not answer as expected", broke
                # out of the loop, and then reported success anyway — so a deploy
                # shipped with no cache reset at all, the next step had to discover
                # it, and the run recorded nothing about WHY. On 2026-08-17 that
                # left the cause unknowable after the fact: by the time anyone
                # looked, the same helper returned `age=47 reset=true HTTP 200` by
                # hand. Whatever it was, it was transient or environmental, and the
                # only reason it stayed a mystery is that nothing wrote down the
                # response. Now it retries, and it prints.
                misses=$((misses + 1))
                warn "reset helper answered HTTP $status on attempt $i (miss $misses/3), not a reset:"
                printf '        %s\n' "$(printf '%s' "$body" | head -c 300 | tr '\n' '|')" >&2
                if [ "$misses" -ge 3 ]; then
                    warn "giving up after 3 bad reads. The file on the server was:"
                    rsh "ls -l $RESET_FILE 2>&1 | sed 's/^/        /'" >&2 || true
                    rsh "rm -f $RESET_FILE" || warn "could not remove $RESET_FILE — delete it by hand."
                    RESET_FILE=''
                    return 1
                fi
                sleep 2
                continue
                ;;
        esac
        sample_interned "$body"

        # Stop on the thing that actually matters: the live site reporting the
        # release we just swapped in. The first version counted OPcache segment
        # ages instead and, on the 15:03 deploy, never saw six consecutive fresh
        # reads in forty hits — so it warned on a deploy that had in fact worked,
        # while the revision check on the very next step passed 12/12. A warning
        # that fires on success is worse than no warning: it teaches everyone to
        # ignore the one mechanism standing between a bad swap and a destructive
        # migration.
        #
        # Segment age was always a proxy anyway. The revision is the observable.
        if [ -n "$want" ] && [ -n "$DEPLOY_API_KEY" ]; then
            live=$(curl --silent --show-error --max-time 20 \
                --header "X-Api-Key: $DEPLOY_API_KEY" "$DEPLOY_BASE_URL/_health" 2>/dev/null \
                | sed -n 's/.*"revision" *: *"\([0-9a-f]*\)".*/\1/p')
            if [ "$live" = "$want" ]; then
                agreed=$((agreed + 1))
                [ "$agreed" -ge 8 ] && break
            else
                agreed=0
            fi
            continue
        fi
        # No key, or no expected revision (a rollback): fall back to segment age,
        # which is the best available signal when nothing can report a revision.
        if [ "$(printf '%s' "$body" | sed -n 's/^age=//p')" -lt 30 ] 2>/dev/null; then
            fresh=$((fresh + 1))
            [ "$fresh" -ge 6 ] && break
        else
            fresh=0
        fi
    done

    rsh "rm -f $RESET_FILE" || warn "could not remove $RESET_FILE — delete it by hand."
    RESET_FILE=''

    if [ -n "$WARM_INTERNED" ]; then
        info "Interned strings per pool, read at the instant this reset each one:"
        printf '%s' "$WARM_INTERNED" | while IFS= read -r line; do dim "  $line"; done
        dim "  Append-only buffer, so these are high-water marks and the warmest"
        dim "  readings that exist. 90%+ means it filled and silently stopped interning;"
        dim "  see docs/php-85.md and tools/opcache-sample.sh."
    fi

    if [ "$agreed" -ge 8 ]; then
        ok "8 consecutive probes report $rel; opcode caches are serving the new release"
        return 0
    fi
    if { [ -z "$want" ] || [ -z "$DEPLOY_API_KEY" ]; } && [ "$fresh" -ge 6 ]; then
        ok "opcode caches reset on every pool that answered (release $rel)"
        return 0
    fi
    warn "40 reset hits without 8 consecutive probes agreeing on $rel."
    warn "The revision check that follows is what decides whether this mattered."
    return 1
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
            # The one swap that is not atomic — mv + ln, with a real (sub-second)
            # window. It happens once per server, and it is worth a human eye.
            UNUSUAL+=("first swap: public/ is a real directory, so this is mv + ln with a brief window")
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
    #
    # The target's own .revision is what the reset verifies against. A rollback that
    # silently does not take effect is exactly as dangerous as a deploy that does
    # not — arguably worse, because it is reached for when something is already
    # wrong and its whole value is being sure.
    TARGET_SHA=$(rsh "cat $RELEASES/$TARGET/.revision 2>/dev/null" | tr -d '\r\n') || TARGET_SHA=''
    reset_opcode_caches "$RELEASES/$TARGET" "$TARGET" "$TARGET_SHA" || true

    step "Smoke checks"
    build_smoke_env
    env "${SMOKE_ENV[@]}" bash tools/smoke-prod.sh
    exit $?
fi

# ============================================================== preflight ===

START_TS=$SECONDS

step "Preflight"

# Say it at the top, where it can still be acted on, rather than discovering it
# from a deploy that hangs. Everything else still applies — ssh keepalives and the
# heartbeat do not depend on this binary — so it is a warning, not a failure.
if [ -z "$TIMEOUT_BIN" ]; then
    warn "neither 'timeout' nor 'gtimeout' is installed locally, so remote calls are unbounded: a stuck command on the server will hang this deploy indefinitely, as it did on 2026-08-18. The heartbeat below will still tell you it is stuck. On macOS: brew install coreutils."
fi

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
        UNUSUAL+=("local changes were stashed — this deploys HEAD, not what you see")
        ok "stashed"
    else
        printf '%s\n' "$DIRTY" | sed 's/^/      /'
        fail "working tree is dirty. Commit it, or re-run with --stash."
    fi
fi

if [ -n "$GIT_REF" ]; then
    warn "deploying '$GIT_REF' rather than master — this is not a normal deploy."
    UNUSUAL+=("deploying '$GIT_REF', not master")
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
    # CI cannot run until 2026-09-01 (Actions quota), so ci-local is the ONLY
    # verification this change gets. Skipping it is exactly the case where someone
    # should be asked.
    [ "$DRY_RUN" = 1 ] || UNUSUAL+=("tests were skipped, and CI cannot run — nothing verified this build")
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
        --dry-run --stats --timeout=120 -e "ssh -o ControlPath=$CTRL_PATH ${SSH_KEEPALIVE[*]} -p $DEPLOY_SSH_PORT" \
        ./ "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$NEW_ABS/" | sed -n '/^Number of files/,/^Total bytes/p' | sed 's/^/    /'
    printf '\n%sDry run complete. The server was not modified.%s\n' "$C_DIM" "$C_OFF"
    exit 0
fi

# A routine deploy no longer asks.
#
# The prompt was the last human checkpoint before production, and on 2026-08-17 it
# prevented exactly none of three failed deploys: the preflight, the revision gate
# and the smoke rollback caught all of them, and every abort happened before
# anything irreversible. What it costs is real, though — it is the one thing
# stopping `merge, pull, deploy` from being a single unattended command.
#
# So it stays for the runs where a human genuinely has something to decide, and
# goes for the rest. The test is deliberately conservative: anything making this
# differ from "current master, verified, onto the usual server" puts it back.
if [ "${#UNUSUAL[@]}" -eq 0 ]; then
    dim "routine deploy — nothing overridden; not asking. (--dry-run inspects first)"
    dim "the revision gate, destructive-migration wait and smoke rollback still apply"
else
    warn "this is NOT a routine deploy:"
    printf '        - %s\n' "${UNUSUAL[@]}" >&2
    confirm "deploy $SHORT to $DEPLOY_BASE_URL anyway"
fi

step "Backing up the live public/.htaccess"
# Tracked, and therefore replaced by every release. The backup is the escape
# hatch for an emergency hand-edit on the server; it lives outside any release.
rsh "cd $APP && mkdir -p shared/data/htaccess-backups && cp -a public/.htaccess shared/data/htaccess-backups/htaccess-$(date +%Y%m%d-%H%M%S)"
ok "saved to shared/data/htaccess-backups/ (reachable as data/htaccess-backups/ inside every release)"

step "Building release $REL"
rsh "mkdir -p $NEW_ABS"
rsync -a --files-from="$FILE_LIST" --from0 "${RSYNC_LINK[@]}" \
    --timeout=120 -e "ssh -o ControlPath=$CTRL_PATH ${SSH_KEEPALIVE[*]} -p $DEPLOY_SSH_PORT" \
    ./ "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$NEW_ABS/"
ok "tree uploaded"

printf '%s\n' "$SHA" | rsh "cat > $NEW_ABS/.revision"
dim ".revision written — SionModel\\Error\\RequestContext reads it, so every recorded"
dim "exception names the release that produced it (immutable now, unlike phploy's)"

# Give public/index.php an identity of its own.
#
# rsync --link-dest hardlinks every unchanged file to the previous release, and
# index.php has not changed since 2026-08-07 — measured on the server, EIGHT links
# to one inode with one mtime shared across all eight releases. It is also the one
# file reached through a path that never changes, because the docroot symlink is
# what varies underneath it. So OPcache can cache it under a stable path, and
# `validate_timestamps` compares an mtime that is byte-identical in every release:
# the check cannot fire, and the previous release keeps executing after the swap.
#
# `touch` alone would be actively wrong — it would move the mtime on the SHARED
# inode, i.e. on every release at once. Breaking the link is the point: copy, then
# rename over, which leaves a new inode with a current mtime and touches nothing
# else. The release is not serving yet, so the rename is unobserved.
#
# Stated honestly: this is reasoned insurance, not a proven fix. Whether OPcache
# here keys on the symlink path or the resolved one was never established, and if
# it is the resolved path this changes nothing at all. It costs one copy of a 3 KB
# file per deploy, which is worth paying for a mechanism this expensive to be wrong
# about. See docs/incident-2026-08-17-stale-opcache.md.
rsh "cd $NEW_ABS/public && cp -p index.php index.php.tmp && mv -f index.php.tmp index.php && touch index.php" \
    || warn "could not break the index.php hardlink; a stale opcode cache is likelier."
dim "public/index.php unlinked from the previous release and re-stamped"

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

# shared/data/import is the one shared directory the *application* creates files
# in rather than an operator, so it has to exist before the first upload rather
# than after somebody notices. Without it the loop below links nothing, the
# library import writes into releases/<this one>/data/import/, and every
# uploaded spreadsheet disappears at the next swap — weeks later, as imports
# whose file 'went missing'. Cheap to guarantee, expensive to diagnose.
mkdir -p shared/data/import

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
    RSH_TIMEOUT=600 RSH_LABEL="copying vendor from the previous release" \
        rsh "cp -a $HOME_ABS/$RELEASES/$PREV/vendor $NEW_ABS/vendor 2>/dev/null || true"
    dim "seeded vendor/ from $PREV; install below is a no-op when the lock is unchanged"
elif [ "$INITIAL_SWAP" = 1 ]; then
    RSH_TIMEOUT=600 RSH_LABEL="copying vendor from the live tree" \
        rsh "cp -a $HOME_ABS/$APP/vendor $NEW_ABS/vendor 2>/dev/null || true"
    dim "seeded vendor/ from the flat tree being replaced"
fi
RSH_TIMEOUT=1800 RSH_LABEL="composer install" \
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
# These used to run *after* the code went live, which is why a deploy had a stale
# window: English strings until the catalogs rebuilt, and a sitemap from the
# previous release.
#
# The merged-config cache is NOT warmed here, and this step used to claim it was.
# It cannot be: bin/console sets config_cache_enabled=false (see the comment there
# — a test run must not write data/config, and CI has no writable one), so no
# console command can ever populate it. Measured 2026-08-17 by clearing
# data/config and running a console command: still empty. One web request: both
# files written.
#
# In practice it is warm before any visitor arrives anyway — the opcode-cache
# reset, the twelve /_health probes and the smoke run all execute the new release
# before the deploy finishes, and on production the cache file appears ~30s after
# the swap because of them. The two deploys that aborted at the gate are the proof
# of the mechanism: their config cache was written 7-8 minutes later, at the exact
# moment the caches were reset by hand and the release first executed anything.
RSH_TIMEOUT=300 RSH_LABEL="jtranslate:export-catalogs" \
    rsh "cd $NEW_ABS && php bin/console jtranslate:export-catalogs" \
    || warn "jtranslate:export-catalogs failed — translations are safe in the database, but this release's catalogs are stale and some strings will render in English."
RSH_TIMEOUT=300 RSH_LABEL="sitemap:build" \
    rsh "cd $NEW_ABS && php bin/console sitemap:build --force" \
    || warn "sitemap:build failed — this release ships without sitemap files until the cron rebuilds them."
ok "catalogs and sitemap built (merged config warms on the first request, below)"

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
    RSH_TIMEOUT=30 RSH_LABEL="the symlink swap" \
        rsh "cd $HOME_ABS/$APP && ln -sfn releases/$REL/public public.swap && mv -Tf public.swap public"
    ok "live release is now $REL (one rename(2); no window)"
fi

step "Post-swap"
# APCu belongs to the SAPI that created it, so this has to be an HTTP request
# into the web server — a CLI apcu_clear_cache() flushes a segment nobody reads.
RSH_TIMEOUT=120 RSH_LABEL="cache:flush-persistent" \
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
# Deliberately not fatal. A failed reset is not itself a reason to stop — the
# revision check on the next step is what knows whether it mattered, and it gives
# a far better message than a bare non-zero exit under `set -e` would.
reset_opcode_caches "$NEW_ABS" "$REL" "$SHA" || warn "continuing to the revision check, which is the real gate."

step "Confirming the live site is running $REL"
# One retry, and it is not belt-and-braces. On the first rollout of this check
# (2026-08-17) the reset step missed, the gate correctly refused, and re-running
# the whole deploy would have hit the same wall — the release was fine, the caches
# were not, and nothing in the loop could break out of it. A second reset is
# harmless and idempotent, so try that before declaring the deploy stuck.
if ! assert_live_revision "$SHA"; then
    warn "retrying the opcode-cache reset once, then re-checking."
    reset_opcode_caches "$NEW_ABS" "$REL" "$SHA" || true
    assert_live_revision "$SHA" \
    || fail "the live site is NOT running the release that was just swapped in, after two cache resets.

  Nothing irreversible has happened yet: the symlink points at $REL, no post-deploy
  migration has run, and the previous release is untouched. This is the check that
  exists because on 2026-08-17 it did not: the swap succeeded, a migration dropped
  five columns, and three OPcache pools went on serving the PREVIOUS release, which
  could not run against the new schema. 449 fatals, and the automatic rollback made
  it permanent by restoring the very release that was broken.

  Read docs/incident-2026-08-17-stale-opcache.md, then reset the caches by hand
  before re-running: write a PHP file with a NEW name into
  $NEW_ABS/public/ that calls opcache_reset(), request it repeatedly until
  /_health reports $SHA, and delete it. A new filename is the point — it has no
  cache entry anywhere, so it always compiles from disk.

  Do NOT run migrations until /_health reports $SHA."
fi

# If a DESTRUCTIVE migration is about to run, the momentary agreement above is not
# enough. Measured after the 15:03 deploy: the gate passed 12/12, then two minutes
# later 6 of 6 probes reported the PREVIOUS release, then a mixed 9/11, and only
# after ~4 minutes did it settle at 20/20 on the new one. Pools the reset loop
# never reached kept serving old code until they recycled on their own.
#
# For an ordinary deploy that is harmless — both releases run against the same
# schema. For a migration that DROPS something it is the 2026-08-17 outage with a
# delay on it: the columns go while a pool is still executing code that selects
# them. So when something irreversible is pending, wait for the agreement to hold
# rather than merely to occur.
#
# Scoped to destructive migrations on purpose. Every deploy paying three minutes
# for a hazard that applies to a handful of them would be a tax people route
# around, and a check people route around protects nothing.
PENDING_DESTRUCTIVE=''
if PLAN=$(bash tools/migrate.sh plan 2>/dev/null); then
    while read -r f; do
        [ -n "$f" ] || continue
        if grep -qE '^--[[:space:]]*@destructive:[[:space:]]*yes' "database/$f" 2>/dev/null; then
            PENDING_DESTRUCTIVE="$PENDING_DESTRUCTIVE $f"
        fi
    done < <(printf '%s\n' "$PLAN" | awk '/PENDING/ {print $2}')
else
    # Could not read the plan. Assume the strict path: being slow is recoverable,
    # dropping a column under running code is not.
    warn "could not read the migration plan; treating this as destructive."
    PENDING_DESTRUCTIVE=' (unknown)'
fi

if [ -n "$PENDING_DESTRUCTIVE" ]; then
    step "Sustained revision check — destructive migration pending:$PENDING_DESTRUCTIVE"
    info "Older code cannot survive this migration, so the live site must agree on"
    info "$REL consistently, not once. Three rounds, 45s apart."
    for round in 1 2 3; do
        if ! assert_live_revision "$SHA"; then
            fail "a pool is still serving an older release, and the pending migration(s)
 ($PENDING_DESTRUCTIVE) would break whatever is still running that code.

  Nothing irreversible has happened: the symlink points at $REL and no post-deploy
  migration has run. This is drift, and it clears on its own as pools recycle —
  measured at roughly four minutes on 2026-08-17. Wait a few minutes and run:

      ./tools/deploy.sh --migrations     # confirm what is still pending
      bash tools/migrate.sh apply --phase=post

  Do not force it. Read docs/incident-2026-08-17-stale-opcache.md for what
  happens when a DROP meets code that has not caught up."
        fi
        [ "$round" -lt 3 ] && sleep 45
    done
    ok "agreement held across three rounds; safe to migrate"
fi

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
        PREV_SHA=$(rsh "cat $RELEASES/$PREV/.revision 2>/dev/null" | tr -d '\r\n') || PREV_SHA=''
        reset_opcode_caches "$HOME_ABS/$RELEASES/$PREV" "$PREV" "$PREV_SHA" || true
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
