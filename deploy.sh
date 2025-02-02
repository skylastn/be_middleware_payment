# Load environment variables from .env file
# source .env

# Jalankan perintah nohup dan simpan output ke docker-compose.log
nohup bash -c 'docker compose down && docker compose build && docker compose up -d' >docker-compose.log 2>&1 &

# Tunggu hingga perintah nohup selesai
wait $!
DISCORD_TOKEN="https://discord.com/api/webhooks/1335524843843616842/Apk0MtQvVhr85gCZwsCl0CIeuCgAWFNPQw7swgc5Vfr1Gu_Z5TjYsM9Ito0a6HE8SRHM"
# Cek status keluar
if [ $? -ne 0 ]; then
    # Jika terjadi error, kirim notifikasi ke Discord dengan file log
    curl -F "file=@docker-compose.log" -F "content=Error saat menjalankan docker-compose. Lihat log untuk detail." $DISCORD_TOKEN
else
    # Jika berhasil, kirim notifikasi ke Discord dengan file log
    curl -F "file=@docker-compose.log" -F "content=Docker-compose berhasil dijalankan. Lihat log untuk detail." $DISCORD_TOKEN
fi
