##
## Install & try
## -----
##
install: ## Install dependencies
	composer install

example: ## Run the mod-three example
	php examples/modulo_3.php

##
## Tests & QA
## -----
##
test: ## Run PHPUnit tests
	vendor/bin/phpunit

test-coverage: ## Run tests with coverage
	vendor/bin/phpunit --coverage-html coverage

cs-check: ## Check code style
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style
	vendor/bin/php-cs-fixer fix

phpstan: ## Run PHPStan
	vendor/bin/phpstan analyse

quality: cs-check phpstan test
	@echo "✅ All quality checks passed!"

.DEFAULT_GOAL := help
help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
.PHONY: help