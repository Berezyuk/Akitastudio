-include .env
export

.PHONY: up down init build-frontend prerender prod tls-init

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

# Одноразовый бутстрап TLS. Запускать ОДИН раз перед первым `make prod`:
# nginx не стартует без файлов сертификата, поэтому первый серт берём в режиме
# --standalone (certbot сам поднимает веб-сервер на 80). Продление потом делает
# certbot-контейнер через webroot — см. docker-compose.prod.yml.
#
# Требуется: DOMAIN и CERTBOT_EMAIL в .env, домен уже указывает на этот сервер,
# порт 80 снаружи открыт и никем не занят.
# Сначала прогони с DRY_RUN=1: у Let's Encrypt жёсткие лимиты (5 неудач на домен
# в час), и пара кривых попыток блокирует выпуск на неделю.
#   make tls-init DRY_RUN=1   -> проверка без выпуска
#   make tls-init             -> боевой выпуск
tls-init:
	@test -n "$(DOMAIN)" || { echo "ОШИБКА: не задан DOMAIN (положи в .env)"; exit 1; }
	@test -n "$(MEDIA_DOMAIN)" || { echo "ОШИБКА: не задан MEDIA_DOMAIN (положи в .env)"; exit 1; }
	@test -n "$(CERTBOT_EMAIL)" || { echo "ОШИБКА: не задан CERTBOT_EMAIL (положи в .env)"; exit 1; }
	@echo "Останавливаю стек: certbot --standalone займёт порт 80..."
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down
	docker volume create akitastudio_certbot_certs
	@echo "Домены: $(DOMAIN), $(MEDIA_DOMAIN)$(if $(DRY_RUN), (ПРОБНЫЙ ПРОГОН — сертификат не выпустится),)"
	docker run --rm -p 80:80 \
	  -v akitastudio_certbot_certs:/etc/letsencrypt \
	  certbot/certbot certonly --standalone $(if $(DRY_RUN),--dry-run,) \
	  -d $(DOMAIN) -d $(MEDIA_DOMAIN) \
	  --email $(CERTBOT_EMAIL) --agree-tos --no-eff-email
	@echo "Готово.$(if $(DRY_RUN), Пробный прогон прошёл — теперь без DRY_RUN=1., Дальше: make prod)"

# Полный продакшен-деплой: пре-рендер (SEO) + запуск всех сервисов.
# Перед ПЕРВЫМ запуском нужен `make tls-init` — иначе nginx упадёт на
# отсутствующем сертификате.
prod: prerender
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
	@echo "Продакшен запущен: https://$(DOMAIN) | API: https://$(DOMAIN)/api"

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
