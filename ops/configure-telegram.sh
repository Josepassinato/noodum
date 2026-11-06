#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$project_dir/.env"

if [[ ! -t 0 ]]; then
  echo "Run this command from an interactive terminal." >&2
  exit 1
fi

if [[ ! -f "$env_file" ]]; then
  echo "Missing $env_file" >&2
  exit 1
fi

write_env_value() {
  local key="$1"
  local value="$2"
  local tmp found=0 line
  tmp="$(mktemp "$project_dir/.env.telegram.XXXXXX")"
  chmod 600 "$tmp"

  while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" == "$key="* ]]; then
      printf '%s=%s\n' "$key" "$value" >>"$tmp"
      found=1
    else
      printf '%s\n' "$line" >>"$tmp"
    fi
  done <"$env_file"

  if [[ "$found" -eq 0 ]]; then
    printf '%s=%s\n' "$key" "$value" >>"$tmp"
  fi

  mv "$tmp" "$env_file"
  chmod 600 "$env_file"
}

echo "Configure the optional private Telegram channel."
echo "Use a newly created BotFather token. Do not reuse a token that has appeared in a log, chat, ticket, or repository."
read -r -s -p "New bot token: " bot_token
printf '\n'
read -r -p "Your numeric private Telegram chat ID: " owner_chat_id

if [[ ! "$bot_token" =~ ^[0-9]{6,12}:[A-Za-z0-9_-]{30,}$ ]]; then
  echo "The token format is invalid." >&2
  exit 1
fi
if [[ ! "$owner_chat_id" =~ ^[1-9][0-9]{4,19}$ ]]; then
  echo "The private chat ID format is invalid." >&2
  exit 1
fi

webhook_secret="$(openssl rand -hex 32)"
base_url="$(grep -E '^HUMHUB_BASE_URL=' "$env_file" | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
base_url="${base_url:-http://localhost:8080}"
base_url="${base_url%/}"

write_env_value NOODUM_TELEGRAM_ENABLED true
write_env_value NOODUM_TELEGRAM_BOT_TOKEN "$bot_token"
write_env_value NOODUM_TELEGRAM_WEBHOOK_SECRET "$webhook_secret"
write_env_value NOODUM_TELEGRAM_ALLOWED_CHAT_IDS "$owner_chat_id"
write_env_value NOODUM_TELEGRAM_WEBHOOK_URL "${base_url}/aiops/telegram/webhook"

unset bot_token webhook_secret

cd "$project_dir"
docker compose up -d --force-recreate app cron
docker compose exec -T --user www-data app php protected/yii aiops/telegram-webhook set
docker compose exec -T --user www-data app php protected/yii aiops/telegram-webhook status
docker compose exec -T --user www-data app php protected/yii aiops/telegram-test

echo "Telegram channel activated. Send /help from the allowlisted private chat."
