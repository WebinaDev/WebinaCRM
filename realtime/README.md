# WebinoCRM Realtime (WebSocket)

Sidecar Node.js server for team chat push notifications.

## Setup

```bash
cd webinocrm/realtime
npm install
export WS_SECRET="same-as-wordpress-option-webinocrm_ws_secret"
export PORT=8080
npm start
```

In WordPress, set options (or `wp-config.php` constants):

- `webinocrm_ws_host` — public hostname (e.g. `localhost` or your domain)
- `webinocrm_ws_port` — `8080` (or reverse-proxy path for `wss`)
- `webinocrm_ws_secret` — shared secret with this server

Production: put `wss` behind nginx/Caddy proxy to this process; use `pm2` or systemd.

## Health

`GET http://localhost:8080/` returns `webinocrm realtime ok`.

## Broadcast (internal)

WordPress calls `POST http://127.0.0.1:8080/broadcast` with header `X-WS-Secret` and body:

```json
{ "user_ids": [1, 2], "data": { "type": "chat_message", "message": {} } }
```
