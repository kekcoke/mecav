# ============================================================
# Mecav — Development & Production Orchestration
# ============================================================

# Base compose files
DEV_COMPOSE := -f docker-compose.yml -f docker-compose.dev.yml
PROD_COMPOSE := -f docker-compose.yml -f docker-compose.prod.yml

# ── Development ──────────────────────────────────────────────

dev:
	docker compose $(DEV_COMPOSE) up

dev-d:
	docker compose $(DEV_COMPOSE) up -d

dev-down:
	docker compose $(DEV_COMPOSE) down

dev-logs:
	docker compose $(DEV_COMPOSE) logs -f

dev-rebuild:
	docker compose $(DEV_COMPOSE) build --no-cache
	docker compose $(DEV_COMPOSE) up -d

# ── Production ────────────────────────────────────────────────

prod:
	docker compose $(PROD_COMPOSE) up -d

prod-down:
	docker compose $(PROD_COMPOSE) down

prod-logs:
	docker compose $(PROD_COMPOSE) logs -f

prod-rebuild:
	docker compose $(PROD_COMPOSE) build --no-cache
	docker compose $(PROD_COMPOSE) up -d

# ── Database ──────────────────────────────────────────────────

db-logs:
	docker compose logs -f db

db-shell:
	docker compose exec db psql -U diagrams -d diagrams_local

# ── Utility ───────────────────────────────────────────────────

ps:
	docker compose $(DEV_COMPOSE) ps

clean:
	docker compose $(DEV_COMPOSE) down -v --remove-orphans
	docker system prune -f

.PHONY: dev dev-d dev-down dev-logs dev-rebuild \
        prod prod-down prod-logs prod-rebuild \
        db-logs db-shell ps clean
