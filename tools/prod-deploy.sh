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
# The last check is the only one that warns rather than refuses: it lists open pull
# requests whose head is not in the tree being deployed. Twice a deploy has shipped the
# previous release because the PR the operator had in mind had not merged, and every other
# check passed — correctly, because master really was clean, synced and consistent.
#
# CHECKS_ONLY exists because the checks are the part worth exercising — before a release,
# or when changing this script — and every other way of doing that ends one step away from
# a live deploy. Piping this script's output through `head` or `sed` truncates what you
# see and does NOT stop it running, which is a foot-gun this flag removes rather than
# documents.
#
# Everything after the checks is tools/deploy.sh, which has its own confirmation for an
# unusual run and its own rollback. See docs/DEPLOY.md.

# ## Why the brace around everything below
#
# Check 2 runs `git checkout master`, which REWRITES THIS FILE while bash is reading it.
# Bash reads a script incrementally and seeks by byte offset, so without this it resumes
# inside whatever master's copy happens to have at that offset: measured 2026-09-09, a
# script that replaces itself mid-run executes the replacement's remaining lines at every
# size tried (1 KB to 60 KB, growing or shrinking). Two consequences, both bad — a syntax
# error mid-deploy, and checks that silently come from the branch you just left rather
# than the one you are deploying.
#
# A brace group is one compound command, so bash parses all of it before running any of
# it. Nothing may be added after the closing brace.
{
set -uo pipefail

DRY_RUN="${DRY_RUN:-0}"
CI="${CI:-0}"
CHECKS_ONLY="${CHECKS_ONLY:-0}"
INTEGRATION_BRANCH="${INTEGRATION_BRANCH:-modernization}"

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

# --- 4. the pinned commits must be MERGED, not merely pushed -----------------------
# The weaker question — "is this commit on some remote branch?" — passes for a commit
# sitting on a pushed but unmerged feature branch. That would deploy code whose review is
# still open, from a branch that may yet be rebased or abandoned.
#
# The question worth asking is whether the pointer names something on the integration
# branch, which is the same thing as "has this submodule's PR been pulled". Asking it
# here matters even though tools/bump-submodules.sh already refuses to pin anything it
# has not proved is an ancestor of origin/$INTEGRATION_BRANCH: that makes the guarantee
# true by convention, held in one script, and a deploy should not depend on how a pointer
# came to be. Two independent checks, one at the pin and one at the deploy.
#
# A deliberate exception has a door: ./tools/deploy.sh --ref REF says out loud that it is
# deploying something other than master.
while read -r _KEY SUB; do
    [ -d "$SUB" ] || continue
    PINNED="$(git rev-parse "HEAD:$SUB" 2>/dev/null)" || continue
    git -C "$SUB" fetch -q origin 2>/dev/null \
        || die "$SUB: cannot fetch origin, so whether the pinned commit is merged cannot be
established. Refusing rather than assuming."

    if ! git -C "$SUB" rev-parse --verify -q "refs/remotes/origin/$INTEGRATION_BRANCH" >/dev/null; then
        die "$SUB: no origin/$INTEGRATION_BRANCH to check the pinned commit against."
    fi

    if ! git -C "$SUB" merge-base --is-ancestor "$PINNED" "origin/$INTEGRATION_BRANCH" 2>/dev/null; then
        WHERE="$(git -C "$SUB" branch -r --contains "$PINNED" 2>/dev/null | tr -d ' ' | paste -sd, - )"
        die "$SUB: master pins $PINNED, which is NOT on origin/$INTEGRATION_BRANCH.

${WHERE:+It is on: $WHERE
}
That is a commit whose PR has not been pulled — unreviewed, and on a branch that may
still be rebased or abandoned. Merge that submodule's PR, then re-pin with
'make dev-bump-submodules'.

To deploy something other than master deliberately, ./tools/deploy.sh --ref REF says so."
    fi
done < <(git config --file .gitmodules --get-regexp '^submodule\..*\.path$')
ok "every pinned submodule commit is merged into origin/$INTEGRATION_BRANCH"

# --- 5. credentials, before anything slow -----------------------------------------
[ -f .deploy.local ] || die "no .deploy.local. Seed it from .deploy.local.dist with ./config.sh.
It is gitignored and mode 0600; credentials never reach the server."
ok ".deploy.local is present"

# --- 6. optional: prove it green first --------------------------------------------
if [ "$CI" = "1" ]; then
    printf '\n%sRunning ci-local.sh first%s\n' "$BOLD" "$OFF"
    ./tools/ci-local.sh || die "ci-local failed. Not deploying."
fi

# --- 7. open pull requests that are not in this tree -------------------------------
# **A warning, never a refusal.** Deploying while PRs are open is normal and most of them
# are nobody's intention to ship. This exists because of the one case that is: twice now a
# deploy has shipped the previous release because the PR the operator had in mind had not
# actually merged — and every check above passed, correctly, because master really was
# clean, synced and internally consistent. "Is this the code you just merged?" is not a
# question this script can answer, so it names what is open and leaves the judgement where
# it belongs.
#
# Submodule PRs are deliberately not listed: an unmerged one shows up as a pointer that is
# not on the integration branch, which check 4 already refuses over.
if command -v gh >/dev/null 2>&1; then
    # `|| true` twice over: gh is optional, may be unauthenticated, and may be offline.
    # None of that is a reason to stand between someone and a deploy.
    OPEN_PRS="$(gh pr list --state open --limit 20 --json number,title,headRefOid \
        -q '.[] | [.number, .headRefOid, .title] | @tsv' 2>/dev/null || true)"

    NOT_HERE=""
    while IFS=$'\t' read -r PR_NUM PR_SHA PR_TITLE; do
        [ -n "${PR_NUM:-}" ] || continue
        # A head commit this clone has never fetched is certainly not in the tree; one it
        # has is in the tree only if it is an ancestor of what we are about to ship.
        if git cat-file -e "${PR_SHA}^{commit}" 2>/dev/null \
           && git merge-base --is-ancestor "$PR_SHA" HEAD 2>/dev/null; then
            continue
        fi
        NOT_HERE="${NOT_HERE}  #${PR_NUM} ${PR_TITLE}"$'\n'
    done <<< "$OPEN_PRS"

    if [ -n "$NOT_HERE" ]; then
        printf '\n'
        warn "open pull request(s) NOT in the tree about to be deployed:"
        printf '%s' "$NOT_HERE"
        printf '  If you meant to ship one, merge it and re-run. Otherwise carry on.\n'
    else
        ok "no open pull request is missing from this tree"
    fi
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
}
