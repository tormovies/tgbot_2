#!/bin/bash
# Сторож tgbot99: если бот не запущен — запускает его.
# Cron (каждые 5 мин): */5 * * * * /home/admin/domains/website.com.ru/public_html/tgbot99/bot-watchdog.sh

BOT_DIR="/home/admin/domains/website.com.ru/public_html/tgbot99"
PHP="/usr/local/php83/bin/php"

if ! pgrep -f "tgbot99/bot.php" > /dev/null; then
    cd "$BOT_DIR" || exit 1
    nohup "$PHP" "$BOT_DIR/bot.php" >> data/bot.log 2>&1 &
    echo "$(date '+%Y-%m-%d %H:%M:%S') [watchdog] Бот был выключен, запущен." >> "$BOT_DIR/data/bot.log"
fi
