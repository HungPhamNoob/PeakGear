DOCKER_COMPOSE = docker compose exec
MAGENTO_BIN = /var/www/html/bin/magento
STATIC_DIR = /var/www/html/pub/static/frontend/PeakGear/climbing/*
VIEW_PREPROCESS_DIR = /var/www/html/var/view_preprocessed/*

.PHONY: cache-flush clean-static deploy-static themes
# Lệnh xóa cache Magento
cache-flush:
	$(DOCKER_COMPOSE) php php $(MAGENTO_BIN) cache:flush

# Lệnh xóa các file static và view preprocessed
clean-static:
	$(DOCKER_COMPOSE) php sh -lc "rm -rf $(STATIC_DIR) $(VIEW_PREPROCESS_DIR)"

# Lệnh deploy static contet
deploy-static:
	$(DOCKER_COMPOSE) php php -d memory_limit=2G $(MAGENTO_BIN) setup:static-content:deploy -f vi_VN en_US

# Lệnh thực hiện tất cả các tác vụ trên
themes: cache-flush clean-static deploy-static

.PHONY: deploy-full

deploy-full:
	$(DOCKER_COMPOSE) php bash -c "\
	set -e && \
	php $(MAGENTO_BIN) setup:upgrade && \
	php $(MAGENTO_BIN) cache:flush && \
	rm -rf $(STATIC_DIR) $(VIEW_PREPROCESS_DIR) && \
	php $(MAGENTO_BIN) setup:static-content:deploy -f && \
	php $(MAGENTO_BIN) indexer:reindex && \
	rm -rf generated/* var/cache/* var/page_cache/* var/di/* && \
	php $(MAGENTO_BIN) setup:di:compile && \
	php $(MAGENTO_BIN) cache:flush && \
	chmod -R 777 var pub/static generated || true \
	"

# ===========================================
# PRODUCTION COMMANDS
# ===========================================
PROD_COMPOSE = docker compose -f docker-compose.prod.yaml
PROD_MAGE = $(PROD_COMPOSE) exec -T php php /var/www/html/bin/magento

.PHONY: prod-start prod-stop prod-restart prod-logs prod-status
.PHONY: prod-deploy prod-backup prod-reindex prod-cache-flush
.PHONY: prod-shell prod-magento

prod-start:
	$(PROD_COMPOSE) up -d

prod-stop:
	$(PROD_COMPOSE) down

prod-restart:
	$(PROD_COMPOSE) restart

prod-logs:
	$(PROD_COMPOSE) logs -f

prod-logs-nginx:
	$(PROD_COMPOSE) logs -f nginx

prod-logs-php:
	$(PROD_COMPOSE) logs -f php

prod-logs-mysql:
	$(PROD_COMPOSE) logs -f mysql

prod-status:
	$(PROD_COMPOSE) ps

prod-deploy:
	bash scripts/deploy.sh

prod-backup:
	bash scripts/backup.sh

prod-reindex:
	$(PROD_MAGE) indexer:reindex

prod-cache-flush:
	$(PROD_MAGE) cache:flush

prod-shell:
	$(PROD_COMPOSE) exec php bash

prod-magento:
	$(PROD_MAGE) $(CMD)