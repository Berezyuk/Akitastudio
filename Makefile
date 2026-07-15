-include .env
export

.PHONY: up down init build-frontend prerender prod

up:
	docker compose up -d

down:
	docker compose down

# Быстрая сборка без пре-рендера (для тестирования)
build-frontend:
	@echo "Сборка фронтенда (VITE_API_URL=$(VITE_API_URL))..."
	docker compose run --rm --no-deps frontend npm run build
	@echo "Готово: dist/"

# Сборка + пре-рендер для SEO (запускать на хосте, требует Node.js и запущенного API)
# VITE_API_URL должен указывать на продакшен-API до запуска этой команды
prerender:
	@echo "Сборка + пре-рендер (VITE_API_URL=$(VITE_API_URL))..."
	@echo "Убедитесь, что API доступен по $(VITE_API_URL)"
	npm run build:prerender
	@echo "Готово: dist/ содержит HTML с контентом для поисковых ботов"

# Полный продакшен-деплой: пре-рендер (SEO) + запуск всех сервисов.
# TLS терминирует ХОСТОВОЙ nginx (см. docs/nginx-host.conf) — этот стек слушает
# только localhost: 8000 (API) и 3001 (статика).
#
# --build обязателен. docker/php/php.ini не монтируется, а КОПИРУЕТСЯ в образ
# (docker/php/Dockerfile: COPY docker/php/php.ini ...). Без --build `up -d` берёт
# существующий образ, и правки php.ini не доезжают вообще — молча, без единого
# следа в выводе. На проде так и вышло: включённый session.cookie_secure пролежал
# в git месяц, а кука на HTTPS всё это время уходила без Secure. Здесь же живут
# display_errors=Off и log_errors — без пересборки фаталы продолжат утекать
# клиенту со стек-трейсом. Сборка с кешем почти бесплатна, стухший образ — нет.
prod: prerender
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
	@echo "Прод обновлён. Снаружи отдаёт хостовой nginx: https://akita-studio.ru"

init:
	@echo "Установка npm-зависимостей..."
	docker compose run --rm --no-deps frontend npm install
	docker compose up -d
	
	@echo "Ожидание готовности PostgreSQL..."
	@until [ "$$(docker inspect --format='{{.State.Health.Status}}' akita_postgres)" = "healthy" ]; do sleep 1; done
	
	@echo "Применение схемы базы данных..."
	@docker compose exec -T postgres sh -c 'psql -U $$POSTGRES_USER -d $$POSTGRES_DB' < docker/postgres/init.sql
	
	@echo "Создание администратора..."
	@hash=$$(docker compose exec -T php sh -c 'ADMIN_PASSWORD="$(ADMIN_PASSWORD)" php -r "echo password_hash(getenv('\''ADMIN_PASSWORD'\''), PASSWORD_BCRYPT);"') && \
	printf "INSERT INTO users (login, password_hash, role) VALUES ('$(ADMIN_LOGIN)', '%s', 'admin') ON CONFLICT (login) DO UPDATE SET password_hash = EXCLUDED.password_hash, role = 'admin';\n" "$$hash" | \
	docker compose exec -T postgres sh -c 'psql -U $$POSTGRES_USER -d $$POSTGRES_DB'
	
	@echo "Ожидание бакетов MinIO..."
	@until [ "$$(docker inspect --format='{{.State.Status}}' akita_minio_init 2>/dev/null)" = "exited" ]; do sleep 2; done

	@echo "Готово. Приложение: http://localhost:5173 | Администратор: $(ADMIN_LOGIN)"
