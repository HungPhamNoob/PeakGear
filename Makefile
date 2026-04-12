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

# Lệnh deploy static content
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