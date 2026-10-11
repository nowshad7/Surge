# Deployment

Surge is a long-running process. In production you want it supervised, behind a
TLS-terminating proxy, and (optionally) scaled across nodes.

## TLS / `wss://`

Surge does **not** terminate TLS itself. Run it behind a proxy that does, and
point it at the local Surge port.

### nginx

```nginx
server {
    listen 443 ssl;
    server_name ws.example.com;

    ssl_certificate     /etc/letsencrypt/live/ws.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ws.example.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:9019;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }
}
```

Then your Echo client uses `wsHost: 'ws.example.com'`, `wsPort: 443`,
`forceTLS: true`.

The same vhost also proxies the HTTP trigger API (it's the same port), so your
backend can point at `https://ws.example.com` too.

## Keeping it running

### systemd

```ini
# /etc/systemd/system/surge.service
[Unit]
Description=Surge realtime server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/app/artisan surge:serve
Restart=always
User=www-data
Environment=SURGE_APP_ID=my-app
Environment=SURGE_APP_KEY=my-key
Environment=SURGE_APP_SECRET=my-secret

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now surge
```

### Supervisor

```ini
[program:surge]
command=php /var/www/app/artisan surge:serve
autostart=true
autorestart=true
user=www-data
stopsignal=TERM
```

Surge handles `SIGINT`/`SIGTERM` for a clean shutdown.

## Multiple nodes

Run Surge on several hosts, enable [Redis scaling](/guide/scaling), and put a
load balancer in front with **sticky sessions** so each WebSocket stays on one
node.

## Resource notes

- Each connection is a file descriptor — raise `ulimit -n` for high connection
  counts. The lite runtime also caps connections (`SURGE_MAX_CONNECTIONS`,
  default 10,000).
- The lite runtime is single-core; scale with more nodes (or the experimental
  [turbo runtime](/guide/runtimes#turbo)) rather than expecting one process to
  saturate a many-core box.

Next: understand the [architecture](/guide/architecture).
