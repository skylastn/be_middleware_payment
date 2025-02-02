include .env
export $(shell sed 's/=.*//' .env)

copyEnvLocal:
	cp ".env.local" ".env"

copyEnvDocker:
	cp ".env.docker" ".env"

copyEnvProd:
	cp ".env.production" ".env"

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
