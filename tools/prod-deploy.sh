#!/usr/bin/env bash
# Get onto a clean, current master and deploy it.
#
# WHY THIS EXISTS: the deploy itself is already careful — tools/deploy.sh builds a release
# in a directory nothing is serving, swaps one symlink, resets every opcode-cache segment
# and polls /_health before running a migration. What it cannot check is whether the tree
# it is about to package is the tree you think it is. That is this script's whole job, and
# it is worth a script rather than a shell alias because of one hazard in particular.
#
# ## The submodule hazard, which has bitten this session
#
# **A release is `git ls-files --recurse-submodules` over the WORKING TREE.** Not over
# HEAD. So the submodule code that ships is whatever is checked out inside module/*, and
# `git checkout master` does NOT move a submodule's working tree — those stay wherever
# they were left, which on this project is usually `modernization`, often several merges
# ahead of what master pins.
#
# Deploying in that state ships the application from master and its shared libraries from
# somewhere else. The failure is not subtle and it is not caught by anything else: on
# 2026-09-09 the same mismatch, in the capsule, produced an empty 200 on every page —
# `git submodule status` showed `+` on two modules and ci-local reported fatal-200
# site-wide. In production it would be that, live, on a tree no branch can reproduce.
#
# `git submodule status` marks it: a leading `+` means the checkout differs from the
# pinned commit, `-` means it was never initialised. Either refuses here.
#
# **It refuses rather than fixing.** The correct repair depends on which side is right: if
# master pins the older commit, the submodule needs `git checkout modernization && git
# merge --ff-only origin/modernization` and a pointer bump (`make dev-bump-submodules`);
# if master is right, the submodule checkout is stale. A script cannot know which, and
# guessing here means deploying something nobody chose.
#
# Usage:
#   make prod-deploy                # checks, then hands over to tools/deploy.sh
#   make prod-deploy DRY_RUN=1      # checks, then deploy.sh --dry-run (server untouched)
#   make prod-deploy CI=1           # run ci-local.sh first, and refuse if it fails
#   make prod-deploy CHECKS_ONLY=1  # run the checks and stop; never reaches deploy.sh
#
# CHECKS_ONLY exists because the checks are the part worth exercising — before a release,
# or when changing this script — and every other way of doing that ends one step away from
# a live deploy. Piping this script's output through `head` or `sed` truncates what you
# see and does NOT stop it running, which is a foot-gun this flag removes rather than
# documents.
#
# Everything after the checks is tools/deploy.sh, which has its own confirmation for an
# unusual run and its own rollback. See docs/DEPLOY.md.

set -uo pipefail

DRY_RUN="${DRY_RUN:-0}"
CI="${CI:-0}"
CHECKS_ONLY="${CHECKS_ONLY:-0}"

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'; OFF=$'\033[0m'
ok()   { printf '  %sok%s    %s\n' "$GREEN" "$OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$YELLOW" "$OFF" "$1"; }
die()  { printf '\n%sRefused:%s %s\n' "$RED" "$OFF" "$1" >&2; exit 1; }

cd "$(dirname "$0")/.." || die "cannot reach the project root"

printf '%sPreparing to deploy master%s\n\n' "$BOLD" "$OFF"

# --- 1. nothing uncommitted, because the working tree is what ships ----------------
# deploy.sh has --stash for the case where you know you have local work. Reaching that
# accidentally is how an experiment ends up in production.
#
# Submodule pointer differences are excluded here and handled by check 3, which knows
# what they mean. They surface in `git status` as a plain ' M module/X', and telling
# someone to "commit or stash" that is actively wrong advice: committing it MOVES THE
# POINTER, which is the opposite of what a deploy wants.
DIRTY="$(git status --porcelain --untracked-files=no | grep -v '^.M module/' || true)"
[ -n "$DIRTY" ] && die "the working tree has uncommitted changes:
$DIRTY
A release is built from the working tree, so these would ship. Commit, stash, or use
./tools/deploy.sh --stash if you meant to."
ok "working tree is clean (submodule pointers checked separately)"

# --- 2. get onto master, fast-forward only ----------------------------------------
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [ "$BRANCH" != "master" ]; then
    git checkout -q master || die "cannot check out master"
    ok "switched from $BRANCH to master"
else
    ok "already on master"
fi

git fetch -q origin || die "fetch failed"
git merge --ff-only origin/master -q 2>/dev/null || die "master will not fast-forward to origin/master.
It has diverged from the remote. Sort that out before deciding to deploy it."

# `merge --ff-only` is NOT enough on its own, and the difference is the whole point of
# this check. When HEAD is *ahead* of origin/master it prints "Already up to date." and
# exits 0 — a local commit nobody else has would sail straight through to production,
# which is the exact failure this script exists to stop. Comparing the two commits is
# what actually answers "are we synced".
HEAD_SHA="$(git rev-parse HEAD)"
ORIGIN_SHA="$(git rev-parse origin/master)"
if [ "$HEAD_SHA" != "$ORIGIN_SHA" ]; then
    AHEAD="$(git rev-list --count origin/master..HEAD)"
    die "master is $AHEAD commit(s) ahead of origin/master:

$(git log --oneline origin/master..HEAD)

Deploying would ship code that is on no remote — unreviewable, and unreproducible by
anyone else or by a rollback. Push it and let it merge, or reset to origin/master."
fi
ok "master is synced with origin at $(git log --oneline -1)"

# --- 3. THE ONE THAT MATTERS: submodule checkouts must match the pinned commits ----
MISMATCH="$(git submodule status --recursive | grep -E '^[+-]' || true)"
if [ -n "$MISMATCH" ]; then
    printf '\n'
    while read -r line; do
        MARK="${line:0:1}"
        SUB="$(printf '%s' "$line" | awk '{print $2}')"
        case "$MARK" in
            +) warn "$SUB is checked out at a DIFFERENT commit than master pins" ;;
            -) warn "$SUB is not initialised" ;;
        esac
    done <<< "$MISMATCH"
    die "submodule checkouts do not match what master pins.

A release is 'git ls-files --recurse-submodules' over the WORKING TREE, so this would
ship the application from master and its libraries from wherever they happen to sit.
That is an empty 200 on every page, live, on a tree no branch can reproduce.

  git submodule status        # '+' marks each one that differs

Then decide which side is right — do not let a script choose:
  · master pins an older commit than the submodule has?  The pointers were never
    bumped: run 'make dev-bump-submodules' on the branch whose PR is open, or bump
    them by hand, and merge that first.
  · the submodule checkout is stale?  Inside it:
        git fetch && git checkout modernization && git merge --ff-only origin/modernization
    Never a bare 'git submodule update' — it detaches HEAD, which this project's
    workflow (CLAUDE.md) relies on not happening."
fi
ok "submodule checkouts match the pinned commits"

# --- 4. the pinned commits must exist on their remotes -----------------------------
# Otherwise master names a commit nobody else can fetch: the deploy would work from this
# machine and from nowhere else, and a rollback or a rebuild elsewhere would fail.
while read -r _KEY SUB; do
    [ -d "$SUB" ] || continue
    PINNED="$(git rev-parse "HEAD:$SUB" 2>/dev/null)" || continue
    git -C "$SUB" fetch -q origin 2>/dev/null || warn "$SUB: could not fetch origin; skipping the remote check"
    if ! git -C "$SUB" branch -r --contains "$PINNED" >/dev/null 2>&1 \
       || [ -z "$(git -C "$SUB" branch -r --contains "$PINNED" 2>/dev/null)" ]; then
        die "$SUB: master pins $PINNED, which is on no remote branch.
Push that commit, or master names code only this machine has."
    fi
done < <(git config --file .gitmodules --get-regexp '^submodule\..*\.path$')
ok "every pinned submodule commit is on a remote"

# --- 5. credentials, before anything slow -----------------------------------------
[ -f .deploy.local ] || die "no .deploy.local. Seed it from .deploy.local.dist with ./config.sh.
It is gitignored and mode 0600; credentials never reach the server."
ok ".deploy.local is present"

# --- 6. optional: prove it green first --------------------------------------------
if [ "$CI" = "1" ]; then
    printf '\n%sRunning ci-local.sh first%s\n' "$BOLD" "$OFF"
    ./tools/ci-local.sh || die "ci-local failed. Not deploying."
fi

# --- hand over ---------------------------------------------------------------------
if [ "$CHECKS_ONLY" = "1" ]; then
    printf '\n%sAll checks passed. CHECKS_ONLY is set, so stopping here.%s\n' "$GREEN" "$OFF"
    printf 'Run without CHECKS_ONLY to deploy.\n'
    exit 0
fi

printf '\n%sChecks passed. Handing over to tools/deploy.sh%s\n\n' "$GREEN" "$OFF"
if [ "$DRY_RUN" = "1" ]; then
    exec ./tools/deploy.sh --dry-run
fi
exec ./tools/deploy.sh
