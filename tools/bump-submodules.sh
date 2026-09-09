#!/usr/bin/env bash
# Pin the submodules to their merged commits and push the result to the superproject PR.
#
# WHY THIS EXISTS: this is the one step of the cross-repo workflow that is both purely
# mechanical and genuinely dangerous to get wrong. CLAUDE.md describes it in four lines —
# "after the submodule PRs merge: fetch, checkout modernization, merge --ff-only, then
# commit pointer bumps pinning the MERGE COMMITS" — and each of those lines is a place a
# release can be broken in a way that is invisible until a deploy.
#
# The three failures it exists to prevent, all of which have happened on this project:
#
#   1. **Pinning a commit nobody else can see.** A release is `git ls-files
#      --recurse-submodules`, so a pointer to an unpushed submodule commit produces a
#      superproject that cannot be checked out anywhere but the machine that made it. The
#      deploy fails at the clone, long after review.
#   2. **Believing GitHub that a PR merged.** A stacked PR merges into its *base*, not into
#      `modernization`. jtranslate#8 once showed MERGED while its commits sat on the
#      feature branch. The only trustworthy test is ancestry, which is what this runs.
#   3. **Pinning something other than what was tested.** If anything else landed on
#      `modernization` between the local run and the merge, the pointer moves to a tree
#      ci-local never saw. That is not automatically wrong, but it must be said out loud
#      rather than discovered later.
#
# It refuses rather than guesses. Every check below either passes or stops the script with
# a message naming what to do; nothing is repaired automatically, because every repair
# here is a judgement about someone else's merge.
#
# Usage:
#   make dev-bump-submodules              # verify, bump, commit, push
#   make dev-bump-submodules DRY_RUN=1    # verify and report, change nothing
#
# The submodule feature branch is assumed to share the superproject's branch name, which
# is the convention CLAUDE.md sets ("same feature-branch name everywhere"). A submodule
# with no such branch was not part of this change and is skipped.

set -uo pipefail

DRY_RUN="${DRY_RUN:-0}"
INTEGRATION_BRANCH="${INTEGRATION_BRANCH:-modernization}"

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'; OFF=$'\033[0m'
ok()   { printf '  %sok%s    %s\n' "$GREEN" "$OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$YELLOW" "$OFF" "$1"; }
die()  { printf '\n%sRefused:%s %s\n' "$RED" "$OFF" "$1" >&2; exit 1; }
step() { printf '\n%s=== %s%s\n' "$BOLD" "$1" "$OFF"; }

cd "$(dirname "$0")/.." || die "cannot reach the project root"

# --- the superproject must be somewhere it is safe to commit -----------------------
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[ "$BRANCH" = "HEAD" ] && die "the superproject is in detached HEAD; check out your feature branch first"
case "$BRANCH" in
    master|main) die "on $BRANCH. Pointer bumps belong on the feature branch whose PR they update." ;;
esac

# Tracked changes outside the submodule pointers. Untracked files are ignored on purpose:
# the commit below adds named paths, never `-a`, so a scratch file cannot be swept in, and
# refusing over one would be officious. A *tracked* edit is different — it is the kind of
# thing a person means to commit and would not expect to find in a pointer bump.
DIRTY="$(git status --porcelain --untracked-files=no | grep -v '^.M module/' || true)"
[ -n "$DIRTY" ] && die "the working tree has tracked changes that are not submodule pointers:
$DIRTY
Commit or stash them; this script commits pointers and nothing else."

printf '%sBumping submodule pointers on %s%s\n' "$BOLD" "$BRANCH" "$OFF"
[ "$DRY_RUN" = "1" ] && printf '%s(dry run: nothing will be changed)%s\n' "$YELLOW" "$OFF"

BUMPED=()
SKIPPED=()
RETESTED=()

# `git config --get-regexp` prints "<key> <value>"; for submodule.<name>.path the value
# IS the path, so there is nothing further to look up.
while read -r _KEY SUB; do
    [ -n "$SUB" ] && [ -d "$SUB" ] || die "submodule path '$SUB' is missing from the working tree"
    step "$SUB"

    git -C "$SUB" fetch -q origin || die "$SUB: fetch failed"

    # A submodule that never had this batch's branch was not part of the change.
    if ! git -C "$SUB" rev-parse --verify -q "refs/heads/$BRANCH" >/dev/null; then
        ok "no $BRANCH branch here — not part of this change, skipping"
        SKIPPED+=("$SUB")
        continue
    fi

    TIP="$(git -C "$SUB" rev-parse "refs/heads/$BRANCH")"
    REMOTE="refs/remotes/origin/$INTEGRATION_BRANCH"
    git -C "$SUB" rev-parse --verify -q "$REMOTE" >/dev/null \
        || die "$SUB: no origin/$INTEGRATION_BRANCH. Is the remote right?"
    TARGET="$(git -C "$SUB" rev-parse "$REMOTE")"

    # (2) ancestry, not GitHub's word for it
    if ! git -C "$SUB" merge-base --is-ancestor "$TIP" "$TARGET"; then
        die "$SUB: $BRANCH is NOT an ancestor of origin/$INTEGRATION_BRANCH.

Its PR has not merged, or it merged into a different base — a stacked PR merges into
its own base and can report MERGED while its commits sit on the feature branch.
Nothing was changed. Check the PR's base branch before retrying."
    fi
    ok "$BRANCH is merged into origin/$INTEGRATION_BRANCH"

    # Move the checkout onto the integration branch, fast-forward only: a merge commit
    # created here would be a local invention nobody reviewed.
    git -C "$SUB" checkout -q "$INTEGRATION_BRANCH" 2>/dev/null \
        || die "$SUB: cannot check out $INTEGRATION_BRANCH — is the working tree clean?"
    git -C "$SUB" merge --ff-only "origin/$INTEGRATION_BRANCH" -q \
        || die "$SUB: $INTEGRATION_BRANCH will not fast-forward to origin. It has local commits;
resolve that by hand rather than letting a script decide."

    SUB_DIRTY="$(git -C "$SUB" status --porcelain)"
    [ -n "$SUB_DIRTY" ] && die "$SUB: uncommitted changes after checkout:
$SUB_DIRTY"

    MERGED="$(git -C "$SUB" rev-parse HEAD)"

    # (3) is the tree we are about to pin the tree that was tested?
    if git -C "$SUB" diff --quiet "$TIP" "$MERGED"; then
        ok "tree is byte-identical to the branch ci-local ran against"
    else
        warn "tree DIFFERS from $BRANCH — something else landed on $INTEGRATION_BRANCH."
        warn "That may be fine, but ci-local has not run against this exact tree."
        RETESTED+=("$SUB")
    fi

    # (1) never pin something the remote does not have
    git -C "$SUB" branch -r --contains "$MERGED" 2>/dev/null | grep -q "origin/$INTEGRATION_BRANCH" \
        || die "$SUB: $MERGED is not on origin/$INTEGRATION_BRANCH.
A release is 'git ls-files --recurse-submodules'; a pointer to an unpushed commit
produces a superproject nobody else can check out."
    ok "pinning $(git -C "$SUB" log --oneline -1 "$MERGED")"

    BUMPED+=("$SUB")
done < <(git config --file .gitmodules --get-regexp '^submodule\..*\.path$')

# --- commit and push ---------------------------------------------------------------
step "Superproject"

CHANGED="$(git status --porcelain -- module/ | grep '^.M ' | awk '{print $2}' || true)"
if [ -z "$CHANGED" ]; then
    ok "every pointer is already at its merged commit — nothing to do"
    exit 0
fi

printf '  pointers to move:\n'
for c in $CHANGED; do printf '    %s\n' "$c"; done

if [ "$DRY_RUN" = "1" ]; then
    printf '\n%sDry run: stopping before commit.%s\n' "$YELLOW" "$OFF"
    exit 0
fi

MSG_BODY=""
for s in "${BUMPED[@]}"; do
    MSG_BODY+="  - ${s#module/}: $(git -C "$s" log --oneline -1)"$'\n'
done
NOTE=""
if [ ${#RETESTED[@]} -gt 0 ]; then
    NOTE=$'\n''Note: '"${RETESTED[*]}"' picked up commits beyond the branch ci-local ran
against, so this pointer is not the exact tree that was verified.'$'\n'
fi

git add -- $CHANGED || die "git add failed"
git commit -q -F - <<MSG
Pin the submodules to their merged commits

Each pointer moves from the feature-branch tip to the commit now on
${INTEGRATION_BRANCH}, verified by ancestry rather than by a merge status:

${MSG_BODY}${NOTE}
Written by tools/bump-submodules.sh.
MSG
[ $? -eq 0 ] || die "commit failed"
ok "committed $(git log --oneline -1)"

git push -q origin "$BRANCH" || die "push failed — the commit is local; push it yourself"
ok "pushed to origin/$BRANCH"

if command -v gh >/dev/null 2>&1; then
    PR="$(gh pr view --json url,state -q '.url + "  (" + .state + ")"' 2>/dev/null || true)"
    [ -n "$PR" ] && ok "updated $PR"
fi

printf '\n%sDone.%s' "$GREEN" "$OFF"
if [ ${#SKIPPED[@]} -gt 0 ]; then
    printf ' Skipped (no %s branch): %s' "$BRANCH" "${SKIPPED[*]}"
fi
printf '\n'
