-include .env
export

.PHONY: up down init build-frontend prod

up:
	docker compose up -d

down:
	docker compose down

build-frontend:
	@echo "Сборка фронтенда (VITE_API_URL=$(VITE_API_URL))..."
	docker compose run --rm --no-deps frontend npm run build
	@echo "Готово: dist/"

prod: build-frontend
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
	@echo "Продакшен запущен. Фронтенд: http://localhost:5173 | API: http://localhost:8000"

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
	
	@echo "Ожидание готовности MinIO..."
	@until [ "$$(docker inspect --format='{{.State.Health.Status}}' akita_minio)" = "healthy" ]; do sleep 2; done
	
	@echo "Создание бакетов MinIO..."
	@docker run --rm \
			--network container:akita_minio \
			-e MC_HOST_local="http://$(MINIO_ROOT_USER):$(MINIO_ROOT_PASSWORD)@localhost:9000" \
			--entrypoint sh minio/mc -c \
			"mc mb --ignore-existing local/order-photos local/portfolio && \
			 mc anonymous set download local/order-photos local/portfolio"
			 
	@echo "Готово. Приложение: http://localhost:5173 | Администратор: $(ADMIN_LOGIN)"
