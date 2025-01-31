deployProduction:
	make copyEnvProd
	make running

deployDevelopment:
	make copyEnvDev
	make running

running:
	chmod +x deploy_log.sh
	chmod +x deploy.sh
	> docker-compose.log
	> run_output.log
