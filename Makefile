# Developer entry points. Everything here delegates to a script in tools/ that carries
# its own reasoning; this file exists so the common ones are discoverable by typing
# `make` rather than by reading the directory.
#
# `prod-deploy` deploys. It is a person's decision and it prompts before the swap; the
# target exists to make the checks that precede it unskippable, not to make deploying
# casual. See docs/DEPLOY.md.

.DEFAULT_GOAL := help
.PHONY: help dev-bump-submodules prod-deploy ci

help: ## Show this help
	@printf '\033[1mTargets\033[0m\n'
	@grep -hE '^[a-z][a-zA-Z0-9_-]*:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-22s\033[0m %s\n", $$1, $$2}'
	@printf '\nVariables\n'
	@printf '  \033[32m%-22s\033[0m %s\n' 'DRY_RUN=1' 'verify and report without changing anything'
	@printf '  \033[32m%-22s\033[0m %s\n' 'CI=1' 'prod-deploy: run ci-local.sh first and refuse if it fails'

ci: ## Run everything CI runs, plus smoke, fuzz and the post-deploy script
	@./tools/ci-local.sh

## After the submodule PRs merge: verify the merges by ancestry, fast-forward each
## submodule, pin the pointers to the merged commits and push to the superproject PR.
## Refuses rather than guesses — see the script header for the three failures it prevents.
dev-bump-submodules: ## Pin submodules to their merged commits and push
	@./tools/bump-submodules.sh

## Check, then deploy. The checks exist because a release is built from the WORKING TREE:
## `git checkout master` does not move a submodule's checkout, so the libraries that ship
## are whatever is sitting in module/*. See the script header.
prod-deploy: ## Fast-forward master, verify the tree, then run tools/deploy.sh
	@./tools/prod-deploy.sh
