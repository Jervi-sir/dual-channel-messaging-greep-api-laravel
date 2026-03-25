# Production Deployment Guide: Nginx & Supervisor

This guide contains individual configuration files for **Nginx** and **Supervisor** to run your Laravel application and Reverb server on ports **9010** and **9011**.

---

## 1. Environment Updates (.env)

Apply these settings to your `.env` file to ensure the application knows its public domains and internal ports.

```env
# General API Domain
APP_URL=http://green-api.anywaresolutions.org:9010

# Reverb WebSocket Configuration
REVERB_HOST="green-reverb.anywaresolutions.org"
REVERB_PORT=9011
REVERB_SCHEME=http

# Frontend (Vite) Reverb Binding
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 2. Supervisor Configuration (Individual Files)

Supervisor manages your long-running processes. These files should be placed in `/etc/supervisor/conf.d/`.

### Server 1: Laravel Reverb (`/etc/supervisor/conf.d/green-reverb.conf`)
sudo nano /etc/supervisor/conf.d/green-reverb.conf
```ini
[program:green-reverb]
process_name=%(program_name)s
command=php /home/jervi/projects/dual-channel-messaging-green-api-laravel/artisan reverb:start --host=127.0.0.1 --port=9011
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=jervi
numprocs=1
redirect_stderr=true
stdout_logfile=/home/jervi/projects/dual-channel-messaging-green-api-laravel/storage/logs/reverb.log
stopwaitsecs=3600
```

### Server 2: Queue Worker (`/etc/supervisor/conf.d/green-worker.conf`)
sudo nano /etc/supervisor/conf.d/green-worker.conf
```ini
[program:green-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/jervi/projects/dual-channel-messaging-green-api-laravel/artisan queue:work --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=jervi
numprocs=1
redirect_stderr=true
stdout_logfile=/home/jervi/projects/dual-channel-messaging-green-api-laravel/storage/logs/worker.log
stopwaitsecs=3600
```
### Server 3: Laravel api (`/etc/supervisor/conf.d/green-api.conf`)
sudo nano /etc/supervisor/conf.d/green-api.conf
```ini
[program:green-api]
process_name=%(program_name)s
command=php /home/jervi/projects/dual-channel-messaging-green-api-laravel/artisan serve --host=127.0.0.1 --port=9010
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=jervi
numprocs=1
redirect_stderr=true
stdout_logfile=/home/jervi/projects/dual-channel-messaging-green-api-laravel/storage/logs/green-api.log
stopwaitsecs=3600
```


---

## 3. Nginx Server Blocks

Place these in `/etc/nginx/sites-available/`.

### API Domain (`/etc/nginx/sites-available/green.anywaresolutions.org`)
sudo nano /etc/nginx/sites-available/green.anywaresolutions.org
**Port: 9010**
```nginx
server {
    server_name green.anywaresolutions.org;

    location / {
        proxy_pass http://localhost:9010;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;
    }

}
server {
    if ($host = green.anywaresolutions.org) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    listen 80;
    server_name green.anywaresolutions.org;
    return 404; # managed by Certbot
}
```

### Reverb Domain (`/etc/nginx/sites-available/green-websocket.anywaresolutions.org`)
**Port: 9011 (reverb Proxy)**
sudo nano /etc/nginx/sites-available/green-websocket.anywaresolutions.org
```nginx
server {
    server_name green-websocket.anywaresolutions.org;

    # Allow larger uploads
    client_max_body_size 25M;
    client_body_buffer_size 256k;
    client_body_timeout 300s;

    location / {
        proxy_pass http://localhost:9011;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Streaming uploads — don't buffer at Nginx
        proxy_request_buffering off;
        proxy_max_temp_file_size 0;

        # Timeouts for slow networks
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;

        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;

        # WebSocket support
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name green-websocket.anywaresolutions.org;
    return 301 https://$host$request_uri;
}

```

---

## 4. Activation Steps

### 1. Update Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 2. Update Nginx
```bash
sudo ln -s /etc/nginx/sites-available/green.anywaresolutions.org /etc/nginx/sites-enabled/green.anywaresolutions.org
sudo ln -s /etc/nginx/sites-available/green-websocket.anywaresolutions.org /etc/nginx/sites-enabled/green-websocket.anywaresolutions.org
sudo nginx -t
sudo systemctl reload nginx
```

---

## 5. SSL Configuration with Certbot

To secure your connection with HTTPS, use Certbot (Let's Encrypt).

### 1. Install Certbot
```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx -y
```

### 2. Generate SSL Certificates
Since your sites use custom ports (9010/9011), you should use the `--nginx` plugin to handle the challenge via port 80.

```bash
sudo certbot --nginx -d green.anywaresolutions.org
sudo certbot --nginx -d green-websocket.anywaresolutions.org
```

### 3. Update Nginx for SSL
After running Certbot, it will likely add SSL directives and default to port 443. If you want to keep using ports **9010 and 9011 with SSL**, ensure your `listen` directives look like this:

**For API (9010):**
```nginx
server {
    listen 9010 ssl; # managed by Certbot
    server_name green.anywaresolutions.org;
    
    ssl_certificate /etc/letsencrypt/live/green.anywaresolutions.org/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/green.anywaresolutions.org/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

    # ... rest of your config
}
```

**For Reverb (9011):**
```nginx
server {
    listen 9011 ssl; # managed by Certbot
    server_name green-reverb.anywaresolutions.org;

    ssl_certificate /etc/letsencrypt/live/green-reverb.anywaresolutions.org/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/green-reverb.anywaresolutions.org/privkey.pem; # managed by Certbot
    
    # ... rest of your config
}
```

> [!TIP]
> **Firewall**: Ensure ports 9010 and 9011 are open: `sudo ufw allow 9010 && sudo ufw allow 9011`
