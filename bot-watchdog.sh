#!/bin/bash
# Сторож tgbot99: перезапуск если бот мёртв, завис (heartbeat старый) или пора по расписанию.
# Cron: */5 * * * * /home/admin/domains/website.com.ru/public_html/tgbot99/bot-watchdog.sh

BOT_DIR="/home/admin/domains/website.com.ru/public_html/tgbot99"
PHP="/usr/local/php83/bin/php"
LOG="$BOT_DIR/data/bot.log"
HEARTBEAT="$BOT_DIR/data/heartbeat"
STALE_SEC=300          # heartbeat старше 5 мин = завис
RESTART_INTERVAL=21600 # принудительный рестарт каждые 6 ч

do_restart() {
    pkill -f "tgbot99/bot.php" 2>/dev/null
    sleep 2
    cd "$BOT_DIR" || exit 1
    nohup "$PHP" "$BOT_DIR/bot.php" < /dev/null >> "$LOG" 2>&1 &
    echo "$(date '+%Y-%m-%d %H:%M:%S') [watchdog] $1" >> "$LOG"
}

# 1) Процесс не запущен
if ! pgrep -f "tgbot99/bot.php" | grep -v grep > /dev/null; then
    do_restart "Бот не запущен, запущен."
    exit 0
fi

# 2) Heartbeat старый = завис (в файле лежит timestamp от bot.php)
now=$(date +%s)
if [ -f "$HEARTBEAT" ]; then
    hb=$(cat "$HEARTBEAT" 2>/dev/null)
    if [ -n "$hb" ] && [ $((now - hb)) -gt $STALE_SEC ]; then
        do_restart "Heartbeat старше ${STALE_SEC}с, бот завис — перезапуск."
        exit 0
    fi
fi

# 3) Процесс жив дольше 6 ч = принудительный рестарт
pid=$(pgrep -f "tgbot99/bot.php" | grep -v grep | head -1)
if [ -n "$pid" ]; then
    etimes=$(ps -o etimes= -p "$pid" 2>/dev/null | tr -d ' ')
    if [ -n "$etimes" ] && [ "$etimes" -gt $RESTART_INTERVAL ] 2>/dev/null; then
        do_restart "Плановый перезапуск (каждые 6 ч)."
        exit 0
    fi
fi
