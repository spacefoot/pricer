help:
	@grep -E '^\w+:.*?##\s.*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

init: ## Build container
	@docker compose down -v
	@docker compose up --build
	@docker compose run cli composer install

test: ## Run tests
	@docker compose run cli php vendor/bin/phpunit
