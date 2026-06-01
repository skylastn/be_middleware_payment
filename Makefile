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

run:
	make copyEnvLocal
	./run.sh

running:
	chmod +x deploy.sh
	> docker-compose.log
	> deploy.log
	nohup ./deploy.sh > deploy.log 2>&1 &
