#!/usr/bin/env bash
#
# The deploy workflow's triggers, and the three conditions that keep a fork away from the
# deploy key.
#
# WHY THIS FILE EXISTS. On 2026-09-24 deploy.yml became push-to-deploy: `workflow_run` on
# CI completing, no approval step. That trade is sound only while four textual facts hold,
# and every one of them breaks silently — no error, no failing job, just a deploy that
# stops happening or one that happens when it must not.
#
#   1. `workflows: [CI]` matches ci.yml's `name:`, NOT its filename. Rename the CI workflow
#      and every automatic deploy stops, permanently and without a single red mark
#      anywhere. Nothing else in the repository connects those two strings.
#
#   2. A `workflow_run` job runs in the DEFAULT BRANCH's context. That means the
#      `production` environment's branch restriction — which is the guard the dispatch path
#      leans on entirely — sees `refs/heads/master` and passes no matter whose commit CI was
#      testing. CI runs on a fork's pull request. So without the `if:`, a fork's green CI
#      run reaches a job holding the SSH key, the database password and the maintenance
#      API key. The three conditions ARE the guard; drop one and the environment does not
#      catch it.
#
#   3. No `pull_request`/`pull_request_target` trigger, for the same reason stated the
#      other way round.
#
#   4. The commit deployed is `workflow_run.head_sha`, not `github.sha`. For a workflow_run
#      event those differ whenever a second merge lands during CI's ~7 minutes: the run
#      that went green tested the older commit while `github.sha` already names the newer
#      one. Deploying that pairing credits an unverified commit with a green run, which is
#      the exact claim DEPLOY_VERIFIED_SHA exists to make honestly.
#
# Textual on purpose, like its neighbours: running the thing means connecting to
# production. What can be checked without a server is that the words are still there, and
# for all four of these the words are the whole mechanism.
set -uo pipefail

cd "$(dirname "$0")/../.."

DEPLOY=.github/workflows/deploy.yml
FAILED=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        FAILED=$((FAILED + 1))
    fi
}

[ -f "$DEPLOY" ] || { echo "workflow triggers: $DEPLOY is missing" >&2; exit 1; }

# --- 1. Every workflow named in the trigger exists under that name. -------------------
# Discovered, not listed: read the names out of the trigger rather than hard-coding "CI",
# so renaming the dependency in deploy.yml cannot quietly stop testing the new name.
WANTED=$(sed -n 's/^ *workflows: *\[\(.*\)\] *$/\1/p' "$DEPLOY" | tr ',' '\n' \
    | sed 's/^ *//; s/ *$//; s/^"\(.*\)"$/\1/; s/^'"'"'\(.*\)'"'"'$/\1/' | grep -v '^$')

if [ -z "$WANTED" ]; then
    check 1 "deploy.yml declares a workflow_run trigger with a workflows: [...] list"
else
    while IFS= read -r wf; do
        HITS=$(grep -lxF "name: $wf" .github/workflows/*.yml 2>/dev/null | wc -l)
        case "$HITS" in
            1) check 0 "workflow_run waits on '$wf', and exactly one workflow is named that" ;;
            0) check 1 "workflow_run waits on '$wf', but NO workflow is named that — automatic deploys are dead" ;;
            *) check 1 "workflow_run waits on '$wf', but $HITS workflows claim that name" ;;
        esac
    done <<EOF
$WANTED
EOF
fi

# --- 2. The three conditions, read out of the job's own `if:`. -------------------------
# Not a grep over the file: a condition sitting in a comment is not a guard.
GUARD=$(awk '/^    if: >-$/ {f = 1; next} f && /^    [a-z]/ {f = 0} f {print}' "$DEPLOY")

if [ -z "$GUARD" ]; then
    check 1 "the deploy job carries an if: guard"
else
    case "$GUARD" in
        *"workflow_run.conclusion == 'success'"*)
            check 0 "the guard requires CI to have SUCCEEDED (a completed run may have failed)" ;;
        *) check 1 "the guard does not require workflow_run.conclusion == 'success'" ;;
    esac
    case "$GUARD" in
        *"workflow_run.head_branch == 'master'"*)
            check 0 "the guard requires the CI run's head branch to be master" ;;
        *) check 1 "the guard does not require workflow_run.head_branch == 'master'" ;;
    esac
    case "$GUARD" in
        *'workflow_run.head_repository.full_name == github.repository'*)
            check 0 "the guard requires the CI run to come from THIS repository, not a fork" ;;
        *) check 1 "the guard does not pin head_repository — a fork's CI can reach the deploy key" ;;
    esac
fi

# --- 3. No trigger a pull request can reach. -------------------------------------------
if grep -qE '^\s*pull_request(_target)?:' "$DEPLOY"; then
    check 1 "deploy.yml has a pull_request trigger; the secrets are reachable from a fork"
else
    check 0 "deploy.yml has no pull_request or pull_request_target trigger"
fi

# --- 4. The deployed commit is the one CI tested. --------------------------------------
if grep -q 'TARGET_SHA: ${{ github.event.workflow_run.head_sha || github.sha }}' "$DEPLOY"; then
    check 0 "the target commit is workflow_run.head_sha, falling back to github.sha for a dispatch"
else
    check 1 "TARGET_SHA is not defined as workflow_run.head_sha || github.sha"
fi

BARE=$(grep -c '\${{ github\.sha }}' "$DEPLOY")
if [ "$BARE" = 0 ]; then
    check 0 "no step reads a bare github.sha, which under workflow_run can outrun the tested commit"
else
    check 1 "$BARE bare \${{ github.sha }} reference(s) remain; under workflow_run they may name an untested commit"
fi

if [ "$FAILED" = 0 ]; then
    echo "workflow triggers: all checks passed"
    exit 0
fi
echo "workflow triggers: $FAILED check(s) failed" >&2
exit 1
