include .env
export $(shell sed 's/=.*//' .env)

copyEnvLocal:
	cp ".env.local" ".env"

copyEnvDocker:
	cp ".env.docker" ".env"

copyEnvProd:
	cp ".env.production" ".env"

migrate:
	php artisan migrate

migrate-revert:
	php artisan migrate:reset

freshInstall:
	php artisan migrate:refresh
	make initSeeder

initSeeder: migrate
	php artisan db:seed --class=InitSeeder

deployProduction:
	make copyEnvProd
	make running

deployLocalDocker:
	make copyEnvDocker
	make running

deploy:
	# Builds frontend assets (admin React / backoffice) using a throwaway node container.
	# Note: The main Docker image build (via deploy.sh / make deployLocalDocker / docker compose build)
	# now includes the admin React build automatically via multi-stage (see Dockerfile).
	# This target is still useful for:
	#   - Rebuilding assets on the host (for volume-mounted dev)
	#   - Running before a non-Docker production step
	php artisan migrate --force
	php artisan optimize:clear
	docker compose -f docker-compose-no-container.yml run --rm pos-build

run:
	make copyEnvLocal
	./run.sh

running:
	chmod +x deploy.sh
	./deploy.sh
# 	docker compose down && docker compose build && docker compose up -d
# 	> docker-compose.log
# 	> deploy.log
# 	nohup ./deploy.sh > deploy.log 2>&1 &
