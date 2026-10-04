# Test deployment: https://dev.lefsignature.com:8443

LAV SMS runs in Docker (app + MariaDB) on the existing AWS server, next to the app already on
`dev.lefsignature.com`. It is reached on **port 8443** of the same host name, so the DNS record
stays exactly as it is and the existing app is not touched.

```
Browser ──https──▶ dev.lefsignature.com:443  ──▶ existing dev app (unchanged)
Browser ──https──▶ dev.lefsignature.com:8443 ──▶ nginx ──▶ 127.0.0.1:8081 ──▶ LAV SMS container ──▶ MariaDB container
```

## Option A — separate test instance (first)

A new EC2 instance runs only LAV SMS, so the server with `dev` and `portal` is not touched.
It is reached by its IP address over `http://` (an SSL certificate needs a domain name); that is
fine for testing with demo data. Once approved, move it to the main server (Option B below).

1. **Launch the instance** — Ubuntu 24.04, **t3.small** (2 GB RAM; the build needs it), 20 GB disk.
   Security group inbound: SSH 22 from your IP, HTTP 80 from `0.0.0.0/0` (or your boss's/office IP).
   Allocate an **Elastic IP** and attach it, so the address does not change after a restart.
2. **Install Docker**: `curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker ubuntu` (log out/in).
3. **Upload and unpack** (from your PC):
   ```bash
   scp -i your-key.pem lav-sms-deploy.tar.gz ubuntu@ELASTIC_IP:~
   ssh -i your-key.pem ubuntu@ELASTIC_IP "tar -xzf lav-sms-deploy.tar.gz && mv lav_sms-master lav-sms"
   ```
4. **Configure** on the instance:
   ```bash
   cd ~/lav-sms
   echo "APP_BIND=80" > .env                 # compose only: publish on port 80
   cp .env.docker.example .env.docker
   nano .env.docker
   #   APP_URL=http://ELASTIC_IP
   #   SESSION_SECURE_COOKIE=false
   #   strong passwords: DB_PASSWORD = MARIADB_PASSWORD, and MARIADB_ROOT_PASSWORD
   docker compose build
   docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show   # paste into APP_KEY=
   docker compose up -d
   docker compose logs -f app                 # wait for "resuming normal operations"
   ```
5. Open **http://ELASTIC_IP** and sign in (`admin` / `cj`). Then see "After the first login" below.

Moving later: back up the database (see Useful commands), copy it to the main server as
`docker/seed/lav_sms.sql`, and follow Option B with an empty database — it imports the backup.

### Push to deploy (instead of uploading a bundle)

Laptop key: `~/.ssh/lav_sms_server` (alias `lav-sms-test` in `~/.ssh/config`; set its `HostName` to the Elastic IP).
Git remote `server` = `lav-sms-test:lav-sms.git`.

```bash
# once: install Docker, add the deploy key, create the repo + deploy hook
ssh -i aws-key.pem ubuntu@ELASTIC_IP "bash -s -- '$(cat ~/.ssh/lav_sms_server.pub)'" < deploy/server-setup.sh
git push server main                                         # first push: code only
scp docker/seed/lav_sms.sql lav-sms-test:~/lav-sms/docker/seed/   # demo data (not in git)
ssh lav-sms-test                                             # create ~/lav-sms/.env and .env.docker (step 4)
git push server main                                         # builds and starts; every later push redeploys
```

## Option B — main server, https://dev.lefsignature.com:8443

### 1. Open the port on AWS

EC2 → the instance's **Security group** → Edit inbound rules → add:

| Type | Port | Source |
| --- | --- | --- |
| Custom TCP | 8443 | `0.0.0.0/0` (or only your boss's / office IP) |

If the server runs `ufw`: `sudo ufw allow 8443/tcp`.

### 2. Install Docker (once)

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # log out and back in
docker compose version
```

### 3. Copy the project to the server

From your PC (skip `node_modules` and `vendor`; the image builds them):

```bash
rsync -av --exclude node_modules --exclude vendor --exclude .git ./ ubuntu@dev.lefsignature.com:~/lav-sms/
```

or push to a private Git repository and `git clone` it on the server. Also copy
`docker/seed/lav_sms.sql` (the demo data; it is git-ignored) into `~/lav-sms/docker/seed/`.

### 4. Configure

```bash
cd ~/lav-sms
cp .env.docker.example .env.docker
nano .env.docker            # set strong DB passwords (DB_PASSWORD = MARIADB_PASSWORD)
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
# paste the printed key into APP_KEY= in .env.docker
```

Leave `MAIL_DRIVER=log` and `SMS_DRIVER=log` while your boss tests, so nothing is emailed or texted.
`NOTICE_ALLOWLIST` is a second safety net: even with real drivers, only those contacts receive messages.

### 5. Start

```bash
docker compose up -d
docker compose logs -f app   # wait for "resuming normal operations"
curl -I http://127.0.0.1:8081/login   # expect 200
```

On the first start the empty database is filled from `docker/seed/lav_sms.sql`.

### 6. Publish on port 8443 with nginx

The server already has nginx (or similar) serving `dev.lefsignature.com` with a certificate. Re-use it:

```bash
sudo nginx -T | grep -E "server_name|ssl_certificate"     # find the existing cert paths
sudo cp docker/nginx-lav-sms-8443.conf /etc/nginx/sites-available/lav-sms-8443
sudo nano /etc/nginx/sites-available/lav-sms-8443           # fix the cert paths if different
sudo ln -s /etc/nginx/sites-available/lav-sms-8443 /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Open **https://dev.lefsignature.com:8443**.

## After the first login

* **Settings → Logo**: upload the school logo again (the saved logo pointed to a PC on the local network).
* Demo logins (password `cj`): `admin`, `accountant`, `teacher`, `parent`, `student`. Super admin: `cj`.
  Change these passwords before sharing the link widely.

## Updating to a new version

```bash
cd ~/lav-sms && git pull          # or rsync again
docker compose up -d --build      # data stays in the dbdata volume; new migrations run automatically
```

## Useful commands

| Task | Command |
| --- | --- |
| Logs | `docker compose logs -f app` |
| Laravel command | `docker compose exec app php artisan <command>` |
| Reset to fresh demo data | `docker compose exec app php artisan db:seed --class=DemoSchoolSeeder --force` |
| Back up the database | `docker compose exec db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" lav_sms' > backup.sql` |
| Stop | `docker compose down` (add `-v` only to delete the data too) |

## Notes

* Port 8443 is used because 443 belongs to the existing app. If you later want a clean URL without a
  port, add a sub-domain record (e.g. `sms.lefsignature.com`) and change `listen 8443` to `listen 443`
  with `server_name sms.lefsignature.com`.
* The app container only listens on `127.0.0.1:8081`, so it is not reachable except through nginx.
* PDFs now include the school logo (the image has the PHP `gd` extension).
