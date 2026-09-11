#!/usr/bin/env bash
# Deploy master, from a checkout of this repository that nothing else touches.
#
# WHY THIS EXISTS: the deploy itself is already careful — tools/deploy.sh builds a release
# in a directory nothing is serving, swaps one symlink, resets every opcode-cache segment
# and polls /_health before running a migration. What it cannot check is whether the tree
# it is about to package is the tree you think it is. That is this script's whole job, and
# it is worth a script rather than a shell alias because of one hazard in particular.
#
# ## The submodule hazard, which is why the checks exist at all
#
# **A release is `git ls-files --recurse-submodules` over the WORKING TREE.** Not over
# HEAD. So the submodule code that ships is whatever is checked out inside module/*, and
# `git checkout master` does NOT move a submodule's working tree — those stay wherever
# they were left, which on this project is usually `modernization`, often several merges
# ahead of what master pins.
#
# Deploying in that state ships the application from master and its shared libraries from
# somewhere else. The failure is not subtle: on 2026-09-09 the same mismatch, in the
# capsule, produced an empty 200 on every page — `git submodule status` showed `+` on two
# modules and ci-local reported fatal-200 site-wide. In production it would be that, live,
# on a tree no branch can reproduce.
#
# ## The deploy checkout
#
# Because the release is the working tree, this script used to take over YOURS: `git
# checkout master`, `git merge --ff-only`, and a refusal whenever you had uncommitted
# work. A deploy and an afternoon's work could not share a machine, and a branch switch
# during a deploy corrupted the deploy.
#
# It no longer touches your tree. It maintains a second checkout —
#
#     $DEPLOY_TREE   (default: ${XDG_CACHE_HOME:-~/.cache}/schoenstatt.link-deploy)
#
# hard-reset to origin/master with the pinned submodule commits checked out inside it, and
# hands THAT to tools/deploy.sh. Your tree is only ever read.
#
# Four consequences worth knowing:
#
#  · Two of the old checks are gone because the state they refused is now impossible
#    rather than merely detected: the tree cannot be dirty (it is reset every run), and
#    master cannot be ahead of origin (it is not your master — it is origin/master, just
#    fetched). A local-only commit can no longer reach production even by accident.
#  · The submodules in the deploy checkout are DETACHED at what master pins. That is
#    correct there and wrong in your tree, where CLAUDE.md's workflow needs them on
#    `modernization`; nothing here changes that rule, and nothing here runs in your tree.
#  · Everything from tools/deploy.sh onwards is master's copy, read from the deploy
#    checkout. Only this file comes from your working tree, so a change to the deploy
#    machinery is exercised by `make prod-deploy` only after it is merged.
#  · ci-local cannot run in the deploy checkout — see below.
#
# ## Verification
#
# Nothing else verifies a build: GitHub Actions is out of minutes, so ci-local is the only
# check there is. It runs in the capsule, and docker-compose.yml bind-mounts YOUR tree
# into the capsule — running it from the deploy checkout would test your tree while
# reporting on the release. So verification stays where the capsule is, under one
# condition:
#
#  · your tree clean, on the same commit, submodules matching → ci-local runs here, and
#    tools/deploy.sh is told this revision is proven and does not repeat it;
#  · anything else → nothing can verify this build. tools/deploy.sh is told that instead,
#    counts it an unusual deploy, and asks before the swap.
#
# CI=1 refuses rather than warns, and runs the FULL ci-local (smoke, fuzz and smoke-prod
# as well as the seven CI jobs) rather than the --ci subset.
#
# Usage:
#   make prod-deploy                # checks, then hands over to tools/deploy.sh
#   make prod-deploy DRY_RUN=1      # checks, then deploy.sh --dry-run (server untouched)
#   make prod-deploy CI=1           # full ci-local first, and refuse if it fails
#   make prod-deploy CHECKS_ONLY=1  # run the checks and stop; never reaches deploy.sh
#   make prod-deploy DEPLOY_TREE=…  # put the deploy checkout somewhere else
#
# The open-PR check is the only one that warns rather than refuses: it lists open pull
# requests whose head is not in the tree being deployed. Twice a deploy has shipped the
# previous release because the PR the operator had in mind had not merged, and every other
# check passed — correctly, because master really was clean, synced and consistent.
#
# CHECKS_ONLY exists because the checks are the part worth exercising — before a release,
# or when changing this script — and every other way of doing that ends one step away from
# a live deploy. Piping this script's output through `head` or `sed` truncates what you
# see and does NOT stop it running, which is a foot-gun this flag removes rather than
# documents. Without CI=1 it stops before verification, so it costs a fetch and nothing
# else.
#
# Everything after the checks is tools/deploy.sh, which has its own confirmation for an
# unusual run and its own rollback. See docs/DEPLOY.md.

# ## Why the brace around everything below
#
# This file is read from YOUR working tree, and the whole point of the deploy checkout is
# that you carry on working in it — including `git checkout`, which REWRITES THIS FILE
# while bash is reading it. Bash reads a script incrementally and seeks by byte offset, so
# without this it resumes inside whatever the other branch's copy happens to have at that
# offset: measured 2026-09-09, a script that is replaced mid-run executes the
# replacement's remaining lines at every size tried (1 KB to 60 KB, growing or shrinking).
# Two consequences, both bad — a syntax error mid-deploy, and checks that silently come
# from a branch nobody chose.
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
REPO_ROOT="$(pwd -P)"

DEPLOY_TREE="${DEPLOY_TREE:-${XDG_CACHE_HOME:-$HOME/.cache}/schoenstatt.link-deploy}"
case "$DEPLOY_TREE" in
    "$REPO_ROOT" | "$REPO_ROOT"/*)
        die "DEPLOY_TREE is $DEPLOY_TREE, which is inside the working tree.

It has to be outside it. Everything under the project root is either tracked, deliberately
ignored, or swept by the test and search tooling — a second copy of the application there
would be found by all three." ;;
esac

printf '%sPreparing to deploy master%s\n' "$BOLD" "$OFF"
printf '  built in %s\n' "$DEPLOY_TREE"
printf '  this working tree is only read\n\n'

# --- 1. credentials, before anything slow -----------------------------------------
[ -f "$REPO_ROOT/.deploy.local" ] || die "no .deploy.local. Seed it from .deploy.local.dist with ./config.sh.
It is gitignored and mode 0600; credentials never reach the server."
ok ".deploy.local is present"

# --- 2. the deploy checkout, at origin/master -------------------------------------
ORIGIN_URL="$(git remote get-url origin 2>/dev/null)" || die "this clone has no 'origin' remote"

if [ ! -d "$DEPLOY_TREE/.git" ]; then
    [ -e "$DEPLOY_TREE" ] && die "$DEPLOY_TREE exists and is not a git checkout. Move it aside."
    printf '  %screating the deploy checkout (first run only)%s\n' "$BOLD" "$OFF"
    # Cloned from here rather than from GitHub: a local clone hardlinks the object
    # store, so the superproject's ~99 MB costs neither network nor disk. The remote
    # is corrected immediately below and everything after that fetches from origin.
    git clone --quiet "$REPO_ROOT" "$DEPLOY_TREE" || die "could not clone into $DEPLOY_TREE"
fi

git -C "$DEPLOY_TREE" remote set-url origin "$ORIGIN_URL" \
    || die "could not point the deploy checkout's origin at $ORIGIN_URL"
git -C "$DEPLOY_TREE" fetch --quiet --prune origin \
    || die "the deploy checkout cannot fetch origin. Whether it holds current master
cannot be established, so it is not deployed."
git -C "$DEPLOY_TREE" checkout --quiet -B master origin/master \
    || die "could not put the deploy checkout on origin/master"
git -C "$DEPLOY_TREE" reset --hard --quiet origin/master \
    || die "could not reset the deploy checkout to origin/master"
ok "deploy checkout is origin/master at $(git -C "$DEPLOY_TREE" log --oneline -1)"

# --- 3. THE ONE THAT MATTERS: submodule checkouts must match the pinned commits ----
# In your tree this is a refusal, because the correct repair depends on which side is
# right and a script must not guess. Here it is simply done: the checkout exists to hold
# what master pins and nothing else, so --force is the whole answer. The status check
# after it is a post-condition, not a question.
git -C "$DEPLOY_TREE" submodule sync --quiet --recursive \
    || die "could not sync the submodule URLs in the deploy checkout"
git -C "$DEPLOY_TREE" submodule update --init --recursive --force --quiet \
    || die "could not check out the pinned submodule commits in the deploy checkout.
The first run clones them from GitHub, so this needs network and, over SSH, a key that has
been unlocked in this desktop session."

MISMATCH="$(git -C "$DEPLOY_TREE" submodule status --recursive | grep -E '^[+-]' || true)"
if [ -n "$MISMATCH" ]; then
    printf '\n%s\n' "$MISMATCH"
    die "the deploy checkout's submodules still do not match what master pins, after a
--force update. That should not be possible; do not deploy past it.

A release is 'git ls-files --recurse-submodules' over the WORKING TREE, so this would ship
the application from master and its libraries from wherever they happen to sit. That is an
empty 200 on every page, live, on a tree no branch can reproduce."
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
    [ -d "$DEPLOY_TREE/$SUB" ] || continue
    PINNED="$(git -C "$DEPLOY_TREE" rev-parse "HEAD:$SUB" 2>/dev/null)" || continue
    git -C "$DEPLOY_TREE/$SUB" fetch -q origin 2>/dev/null \
        || die "$SUB: cannot fetch origin, so whether the pinned commit is merged cannot be
established. Refusing rather than assuming."

    if ! git -C "$DEPLOY_TREE/$SUB" rev-parse --verify -q "refs/remotes/origin/$INTEGRATION_BRANCH" >/dev/null; then
        die "$SUB: no origin/$INTEGRATION_BRANCH to check the pinned commit against."
    fi

    if ! git -C "$DEPLOY_TREE/$SUB" merge-base --is-ancestor "$PINNED" "origin/$INTEGRATION_BRANCH" 2>/dev/null; then
        WHERE="$(git -C "$DEPLOY_TREE/$SUB" branch -r --contains "$PINNED" 2>/dev/null | tr -d ' ' | paste -sd, - )"
        die "$SUB: master pins $PINNED, which is NOT on origin/$INTEGRATION_BRANCH.

${WHERE:+It is on: $WHERE
}
That is a commit whose PR has not been pulled — unreviewed, and on a branch that may
still be rebased or abandoned. Merge that submodule's PR, then re-pin with
'make dev-bump-submodules'.

To deploy something other than master deliberately, ./tools/deploy.sh --ref REF says so."
    fi
done < <(git config --file "$DEPLOY_TREE/.gitmodules" --get-regexp '^submodule\..*\.path$')
ok "every pinned submodule commit is merged into origin/$INTEGRATION_BRANCH"

# --- 5. the two things that must not exist twice ----------------------------------
# Both are links, so both are untracked, and deploy.sh refuses to package a tree with
# anything untracked in it. They are this script's doing rather than the repository's, so
# they are excluded per-checkout in .git/info/exclude — which no reset touches and which
# master's .gitignore therefore never has to know about.
EXCLUDE="$DEPLOY_TREE/.git/info/exclude"
mkdir -p "$(dirname "$EXCLUDE")"
grep -qx '/data/deploy' "$EXCLUDE" 2>/dev/null \
    || printf '\n# Written by tools/prod-deploy.sh; both are links back to the working tree.\n/.deploy.local\n/data/deploy\n' >> "$EXCLUDE"

# .deploy.local holds the full-DDL database password. One copy on disk, not two: the
# deploy checkout reaches the same file, and `stat` follows the link, so deploy.sh still
# sees mode 600.
ln -sfn "$REPO_ROOT/.deploy.local" "$DEPLOY_TREE/.deploy.local" \
    || die "could not link .deploy.local into the deploy checkout"

# data/deploy/ is where tools/migrate.sh dumps the production tables a migration is about
# to change, and where the migration plan is written. That is the operator's material and
# real production data — it belongs in one known place, not scattered across whatever
# checkout happened to run the deploy.
mkdir -p "$REPO_ROOT/data/deploy" || die "could not create $REPO_ROOT/data/deploy"
[ -L "$DEPLOY_TREE/data/deploy" ] || rm -rf "$DEPLOY_TREE/data/deploy"
ln -sfn "$REPO_ROOT/data/deploy" "$DEPLOY_TREE/data/deploy" \
    || die "could not link data/deploy/ into the deploy checkout"
ok "linked .deploy.local and data/deploy/ from this tree"

# The post-condition deploy.sh will insist on, asked here where the answer is still
# comprehensible. Its preflight refuses a tree with ANY untracked file in it, and this is
# the check that finds out — measured: `.gitignore`'s `/data/deploy/` has a trailing
# slash, which matches a directory and not the symlink above, so without the exclude
# written just now the deploy aborted three steps into tools/deploy.sh.
RESIDUE="$(git -C "$DEPLOY_TREE" status --porcelain --ignore-submodules=dirty)"
if [ -n "$RESIDUE" ]; then
    printf '\n%s\n' "$RESIDUE"
    die "the deploy checkout is not clean after being reset to origin/master.

tools/deploy.sh packages a working tree and refuses a dirty one, so this would abort the
deploy halfway. Anything listed above is either an ignore rule that does not cover what
this script puts there, or a file something else wrote into $DEPLOY_TREE."
fi

# --- 6. open pull requests that are not in this tree -------------------------------
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
DEPLOY_SHA="$(git -C "$DEPLOY_TREE" rev-parse HEAD)"
if command -v gh >/dev/null 2>&1; then
    # `|| true` twice over: gh is optional, may be unauthenticated, and may be offline.
    # None of that is a reason to stand between someone and a deploy.
    OPEN_PRS="$(gh pr list --state open --limit 20 --json number,title,headRefOid \
        -q '.[] | [.number, .headRefOid, .title] | @tsv' 2>/dev/null || true)"

    NOT_HERE=""
    while IFS=$'\t' read -r PR_NUM PR_SHA PR_TITLE; do
        [ -n "${PR_NUM:-}" ] || continue
        # A head commit the deploy checkout has never fetched is certainly not in the
        # tree; one it has is in the tree only if it is an ancestor of what ships.
        if git -C "$DEPLOY_TREE" cat-file -e "${PR_SHA}^{commit}" 2>/dev/null \
           && git -C "$DEPLOY_TREE" merge-base --is-ancestor "$PR_SHA" "$DEPLOY_SHA" 2>/dev/null; then
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

# --- 7. can this build be verified at all? ----------------------------------------
# ci-local needs the capsule, and docker-compose.yml bind-mounts THIS tree into it. So the
# only revision the capsule can speak for is the one checked out here, undisturbed. Asking
# it about anything else would test this tree and report on the release.
HERE_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
HERE_SHA="$(git rev-parse HEAD)"
HERE_DIRTY="$(git status --porcelain --untracked-files=no)"
HERE_UNINIT="$(git submodule status --recursive | grep -E '^-' || true)"

UNVERIFIABLE=""
if [ "$HERE_SHA" != "$DEPLOY_SHA" ]; then
    UNVERIFIABLE="the capsule serves this working tree, which is on $HERE_BRANCH at $(git rev-parse --short HEAD), not the ${DEPLOY_SHA:0:7} being deployed"
elif [ -n "$HERE_DIRTY" ] || [ -n "$HERE_UNINIT" ]; then
    # A ' M module/X' counts: the submodule working tree is what a release is made of, so
    # a moved pointer there is a different application, not a cosmetic difference.
    UNVERIFIABLE="the capsule serves this working tree, which has uncommitted changes"
fi

if [ "$CHECKS_ONLY" = "1" ] && [ "$CI" != "1" ]; then
    printf '\n%sAll checks passed. CHECKS_ONLY is set, so stopping here.%s\n' "$GREEN" "$OFF"
    if [ -n "$UNVERIFIABLE" ]; then
        printf 'A real run would deploy unverified: %s.\n' "$UNVERIFIABLE"
    else
        printf 'A real run would verify it first: ci-local.sh --ci in this tree.\n'
    fi
    printf 'Run without CHECKS_ONLY to deploy.\n'
    exit 0
fi

VERIFIED=""
if [ "$DRY_RUN" = "1" ]; then
    ok "dry run — not verifying (tools/deploy.sh skips ci-local for a dry run too)"
elif [ -n "$UNVERIFIABLE" ]; then
    if [ "$CI" = "1" ]; then
        die "CI=1 asks for this build to be proven green, and nothing can prove it:
$UNVERIFIABLE.

Either get this tree onto the revision being deployed —
    git checkout master && git pull --ff-only && git submodule update --init --recursive
(the last one detaches, so re-checkout 'modernization' in each submodule afterwards; see
CLAUDE.md) — or drop CI=1 and decide at tools/deploy.sh's prompt."
    fi
    warn "nothing can verify this build: $UNVERIFIABLE"
    warn "tools/deploy.sh will count it an unusual deploy and ask before the swap"
else
    printf '\n%sVerifying %s in this tree — the capsule holds exactly it%s\n' \
        "$BOLD" "${DEPLOY_SHA:0:7}" "$OFF"
    if [ "$CI" = "1" ]; then
        ./tools/ci-local.sh --no-cache || die "ci-local failed. Not deploying."
    else
        ./tools/ci-local.sh --ci --no-cache || die "ci-local --ci failed. Not deploying."
    fi
    VERIFIED="$DEPLOY_SHA"
    ok "verified — tools/deploy.sh will not run ci-local again"
fi

# --- hand over ---------------------------------------------------------------------
if [ "$CHECKS_ONLY" = "1" ]; then
    printf '\n%sAll checks passed. CHECKS_ONLY is set, so stopping here.%s\n' "$GREEN" "$OFF"
    printf 'Run without CHECKS_ONLY to deploy.\n'
    exit 0
fi

printf '\n%sChecks passed. Handing over to %s/tools/deploy.sh%s\n\n' "$GREEN" "$DEPLOY_TREE" "$OFF"
cd "$DEPLOY_TREE" || die "cannot enter the deploy checkout"

# The contract deploy.sh reads: one of these two is set, never both. Exported empty
# rather than left unset so a stale value from the environment cannot masquerade as this
# run's answer.
export DEPLOY_VERIFIED_SHA="$VERIFIED"
export DEPLOY_UNVERIFIED_REASON="$UNVERIFIABLE"

if [ "$DRY_RUN" = "1" ]; then
    exec ./tools/deploy.sh --dry-run
fi
exec ./tools/deploy.sh
}
