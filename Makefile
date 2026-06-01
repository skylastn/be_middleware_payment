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
	php artisan migrate --force
	php artisan optimize:clear
	@build_status=0; cleanup_status=0; \
	docker compose run --rm pos-build || build_status=$$?; \
	docker compose down --rmi all --remove-orphans || cleanup_status=$$?; \
	if [ $$build_status -ne 0 ]; then exit $$build_status; fi; \
	exit $$cleanup_status

run:
	make copyEnvLocal
	./run.sh

running:
	chmod +x deploy.sh
	> docker-compose.log
	> deploy.log
	nohup ./deploy.sh > deploy.log 2>&1 &
