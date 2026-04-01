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
	$(DOCKER_COMPOSE) php rm -rf $(STATIC_DIR) $(VIEW_PREPROCESS_DIR)

# Lệnh deploy static content
deploy-static:
	$(DOCKER_COMPOSE) php php -d memory_limit=2G $(MAGENTO_BIN) setup:static-content:deploy -f vi_VN en_US

# Lệnh thực hiện tất cả các tác vụ trên
themes: cache-flush clean-static deploy-static