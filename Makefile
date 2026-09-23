# Developer entry points. Everything here delegates to a script in tools/ that carries
# its own reasoning; this file exists so the common ones are discoverable by typing
# `make` rather than by reading the directory.
#
# `prod-deploy` deploys. It is a person's decision and it prompts before the swap; the
# target exists to make the checks that precede it unskippable, not to make deploying
# casual. It builds the release in its own checkout and never touches this working tree.
# See docs/DEPLOY.md.

.DEFAULT_GOAL := help
.PHONY: help prod-deploy ci

help: ## Show this help
	@printf '\033[1mTargets\033[0m\n'
	@grep -hE '^[a-z][a-zA-Z0-9_-]*:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-22s\033[0m %s\n", $$1, $$2}'
	@printf '\nVariables\n'
	@printf '  \033[32m%-22s\033[0m %s\n' 'DRY_RUN=1' 'verify and report without changing anything'
	@printf '  \033[32m%-22s\033[0m %s\n' 'CI=1' 'prod-deploy: run ci-local.sh first and refuse if it fails'
	@printf '  \033[32m%-22s\033[0m %s\n' 'CHECKS_ONLY=1' 'prod-deploy: run the checks and stop, never deploying'
	@printf '  \033[32m%-22s\033[0m %s\n' 'DEPLOY_TREE=DIR' 'prod-deploy: where the deploy checkout lives'

ci: ## Run everything CI runs, plus smoke, fuzz and the post-deploy script
	@./tools/ci-local.sh

## The fast triad: phpcs + phpstan + unit. What the pre-push hook runs, and what to
## reach for between edits — the full suite has a 122s median and a 431s p90.
qa: ## phpcs + phpstan + unit, nothing else
	@./tools/ci-local.sh qa

## Opt this clone into .githooks/pre-push. Per-clone on purpose: a hook committed into
## a shared repo that everyone must run is a hook someone will --no-verify around.
dev-hooks: ## Enable the repository git hooks in this clone
	@git config core.hooksPath .githooks
	@printf 'core.hooksPath = .githooks — pre-push now runs `ci-local.sh qa`.\n'
	@printf 'Undo with: git config --unset core.hooksPath\n'

## Check, then deploy — from a checkout of its own, NOT from this working tree. A release
## is built from a working tree, so a deploy used to take yours over (checkout master,
## merge --ff-only, and a refusal if you had uncommitted work). It now maintains
## a checkout at origin/master and ships that, leaving you free to carry on here.
## See the script header.
prod-deploy: ## Deploy origin/master from a separate checkout
	@./tools/prod-deploy.sh
