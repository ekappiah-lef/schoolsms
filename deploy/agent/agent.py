#!/usr/bin/env python3
"""
EduNetwork server helper. Runs as root on the server (systemd: edunetwork-agent.path / .service), started whenever
the LEF Control Centre drops a job file into /opt/edunetwork/jobs/pending. It only carries out the jobs below,
each with strictly checked inputs, and writes the result to /opt/edunetwork/jobs/done/<id>.json.

  create_school  {slug, domain}            new school: folder, settings, database, app, nginx site, HTTPS certificate
  issue_token    {slug}                    give an existing school its Control Centre key (CONTROL_API_TOKEN)
  set_env        {slug, values}            change a school's keys (email, SMS, MoMo, ClearEnroll, WhatsApp, clock-in), restart it
  restart        {slug}                    restart a school's app
  backup         {slug}                    database dump into the school's backups/manual
  inventory      {}                        every school: version, health, disk, last backup

Schools: "edusuite" is the test school (/opt/edusuite); every other school is /opt/schools/<slug>.
"""
import base64, glob, json, os, re, secrets, shutil, subprocess, sys, time, urllib.request

JOBS = '/opt/edunetwork/jobs'
SCHOOLS = '/opt/schools'
TEST = '/opt/edusuite'
BASE_DOMAIN = '.edunetwork.lefsignature.com'
CERT_EMAIL = 'info@lefsignature.com'
SLUG = re.compile(r'^[a-z][a-z0-9-]{1,29}$')
DOMAIN = re.compile(r'^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$')
RESERVED = {'control', 'www', 'api', 'mail', 'admin', 'edusuite', 'test', 'demo'}
ENV_KEYS = {'MAIL_DRIVER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'SMS_DRIVER', 'SMS_SENDER_ID', 'SMSONLINEGH_API_KEY', 'NOTICE_ALLOWLIST', 'MOMO_DRIVER', 'MOMO_COLLECTION_SUBSCRIPTION_KEY', 'MOMO_API_USER',
            'MOMO_API_KEY', 'MOMO_CALLBACK_URL', 'CLEARENROLL_API_URL', 'CLEARENROLL_API_KEY', 'WHATSAPP_ENABLED', 'WHATSAPP_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID',
            'WHATSAPP_TEMPLATE', 'WHATSAPP_TEMPLATE_LANG', 'STAFF_CLOCK_DEVICES', 'STAFF_CLOCK_TOKEN'}


def run(cmd, cwd=None, check=True, timeout=900):
    p = subprocess.run(cmd, cwd=cwd, shell=isinstance(cmd, str), capture_output=True, text=True, timeout=timeout)
    if check and p.returncode != 0:
        raise RuntimeError(f"{cmd if isinstance(cmd, str) else ' '.join(cmd)} failed: {(p.stderr or p.stdout).strip()[-600:]}")
    return p.stdout.strip()


def folder(slug):
    if slug == 'edusuite':
        return TEST
    if not SLUG.match(slug or ''):
        raise ValueError('Invalid school name.')
    return os.path.join(SCHOOLS, slug)


def read_env(path):
    out = {}
    if os.path.exists(path):
        for line in open(path).read().split('\n'):
            if '=' in line and not line.lstrip().startswith('#'):
                k, v = line.split('=', 1)
                out[k.strip()] = v
    return out


def write_env(path, updates, header=None):
    lines = open(path).read().split('\n') if os.path.exists(path) else ([f'# {header}'] if header else [])
    seen = set()
    for i, line in enumerate(lines):
        k = line.split('=', 1)[0].strip() if '=' in line else None
        if k in updates:
            lines[i] = f'{k}={updates[k]}'
            seen.add(k)
    while lines and lines[-1] == '':
        lines.pop()
    lines += [f'{k}={v}' for k, v in updates.items() if k not in seen]
    open(path, 'w').write('\n'.join(lines) + '\n')
    os.chmod(path, 0o600)
    shutil.chown(path, 'ubuntu', 'ubuntu')


def compose(path):
    extra = ['-f', 'docker-compose.local.yml'] if os.path.exists(os.path.join(path, 'docker-compose.local.yml')) else []
    return ['docker', 'compose', '-f', 'docker-compose.prod.yml'] + extra


def wait_healthy(path, seconds=420):
    end = time.time() + seconds
    while time.time() < end:
        out = run(compose(path) + ['ps', 'app', '--format', '{{.Health}}'], cwd=path, check=False)
        if out.strip() == 'healthy':
            return True
        time.sleep(5)
    return False


def recreate(path):
    run(compose(path) + ['up', '-d', '--force-recreate', 'app'], cwd=path)
    if not wait_healthy(path):
        raise RuntimeError('The app did not become healthy after restarting.')


def nginx_site(domain, port, name):
    tag = re.sub(r'[^a-z0-9]', '-', domain)
    snippet = f'/etc/nginx/snippets/{tag}-proxy.conf'
    open(snippet, 'w').write(
        f'proxy_pass http://127.0.0.1:{port};\nproxy_http_version 1.1;\nproxy_set_header Host $host;\nproxy_set_header X-Real-IP $remote_addr;\n'
        'proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;\nproxy_set_header X-Forwarded-Proto $scheme;\nproxy_set_header Upgrade $http_upgrade;\n'
        'proxy_set_header Connection "upgrade";\nproxy_read_timeout 120s;\n')
    site = f'/etc/nginx/sites-available/{domain}'
    open(site, 'w').write(
        f'# {name} (port {port}); created by the EduNetwork server helper\nserver {{\n    listen 80;\n    listen [::]:80;\n    server_name {domain};\n'
        '    client_max_body_size 25m;\n    add_header Strict-Transport-Security "max-age=31536000" always;\n'
        f'    location ~ ^/(login|password/) {{\n        limit_req zone=edusuite_login burst=10 nodelay;\n        include snippets/{tag}-proxy.conf;\n    }}\n'
        f'    location / {{\n        include snippets/{tag}-proxy.conf;\n    }}\n}}\n')
    link = f'/etc/nginx/sites-enabled/{domain}'
    if not os.path.exists(link):
        os.symlink(site, link)
    run(['nginx', '-t'])
    run(['systemctl', 'reload', 'nginx'])
    run(['certbot', '--nginx', '-d', domain, '--non-interactive', '--agree-tos', '--redirect', '-m', CERT_EMAIL], timeout=300)


def free_port():
    used = set()
    for env in glob.glob(f'{SCHOOLS}/*/.env') + ['/opt/control/.env']:
        p = read_env(env).get('APP_PORT')
        if p and p.isdigit():
            used.add(int(p))
    listening = run("ss -ltnH | awk '{print $4}' | sed 's/.*://'", check=False).split()
    used |= {int(x) for x in listening if x.isdigit()}
    port = 8004
    while port in used or port == 8090:
        port += 1
    return port


def create_school(job):
    slug = (job.get('slug') or '').strip().lower()
    if not SLUG.match(slug) or slug in RESERVED:
        raise ValueError('Choose another web name for the school (letters, numbers and dashes; not a reserved word).')
    domain = (job.get('domain') or f'{slug}{BASE_DOMAIN}').strip().lower()
    if not DOMAIN.match(domain):
        raise ValueError('Invalid web address.')
    path = folder(slug)
    if os.path.exists(path):
        raise ValueError('A school with this web name already exists.')
    name = (job.get('name') or slug)[:120].replace('\n', ' ')

    test_env = read_env(f'{TEST}/.env')
    base = read_env(f'{TEST}/.env.docker')
    port = free_port()
    os.makedirs(f'{path}/deploy')
    for d in ('backups', 'seed'):
        os.makedirs(f'{path}/{d}', mode=0o700)
    shutil.copy(f'{TEST}/docker-compose.prod.yml', path)
    for f in ('deploy.sh', 'backup.sh'):
        shutil.copy(f'{TEST}/deploy/{f}', f'{path}/deploy/')
    write_env(f'{path}/.env', {'COMPOSE_PROJECT_NAME': f'edunetwork-{slug}', 'APP_PORT': str(port),
                               'APP_IMAGE': test_env.get('APP_IMAGE', ''), 'IMAGE_TAG': test_env.get('IMAGE_TAG', 'latest')})
    dbpass, root = secrets.token_urlsafe(24), secrets.token_urlsafe(24)
    db = re.sub(r'[^a-z0-9]', '_', slug)
    token = secrets.token_urlsafe(32)
    env = {k: v for k, v in base.items() if not k.startswith('SCHOOL_') and k not in ('CONTROL_API_TOKEN', 'APP_ROLE', 'CONTROL_JOBS_DIR')}
    env.update({'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(), 'APP_URL': f'https://{domain}',
                'DB_DATABASE': db, 'DB_USERNAME': db, 'DB_PASSWORD': dbpass, 'MARIADB_DATABASE': db, 'MARIADB_USER': db, 'MARIADB_PASSWORD': dbpass,
                'MARIADB_ROOT_PASSWORD': root, 'CLEARENROLL_API_KEY': '', 'MOMO_DRIVER': 'fake', 'CONTROL_API_TOKEN': token})
    open(f'{path}/.env.docker', 'w').write(f'# {name} (https://{domain})\n' + '\n'.join(f'{k}={v}' for k, v in env.items()) + '\n')
    open(f'{path}/.health_url', 'w').write(f'https://{domain}/healthz\n')
    run(['chown', '-R', 'ubuntu:ubuntu', path])
    os.chmod(path, 0o700)
    for f in ('.env', '.env.docker'):
        os.chmod(f'{path}/{f}', 0o600)

    run(compose(path) + ['up', '-d'], cwd=path)
    if not wait_healthy(path):
        raise RuntimeError('The new school did not start in time; see `docker compose logs app` in ' + path)
    nginx_site(domain, port, name)
    return {'slug': slug, 'domain': domain, 'url': f'https://{domain}', 'port': port, 'token': token}


def issue_token(job):
    path = folder(job.get('slug'))
    if not os.path.isdir(path):
        raise ValueError('No such school.')
    env = read_env(f'{path}/.env.docker')
    token = env.get('CONTROL_API_TOKEN') or secrets.token_urlsafe(32)
    if not env.get('CONTROL_API_TOKEN'):
        write_env(f'{path}/.env.docker', {'CONTROL_API_TOKEN': token})
        recreate(path)
    url = env.get('APP_URL', '')
    return {'slug': job.get('slug'), 'url': url, 'domain': url.replace('https://', '').rstrip('/'), 'token': token,
            'port': int(read_env(f'{path}/.env').get('APP_PORT') or 0)}


def set_env(job):
    path = folder(job.get('slug'))
    values = job.get('values') or {}
    if not isinstance(values, dict) or not values:
        raise ValueError('Nothing to change.')
    bad = [k for k in values if k not in ENV_KEYS]
    if bad:
        raise ValueError('These settings cannot be changed here: ' + ', '.join(bad))
    clean = {}
    for k, v in values.items():
        v = '' if v is None else str(v)
        if '\n' in v or '\r' in v or len(v) > 500:
            raise ValueError(f'Invalid value for {k}.')
        clean[k] = v
    write_env(f'{path}/.env.docker', clean)
    recreate(path)
    return {'slug': job.get('slug'), 'changed': sorted(clean)}


def restart(job):
    recreate(folder(job.get('slug')))
    return {'slug': job.get('slug')}


def backup(job):
    path = folder(job.get('slug'))
    os.makedirs(f'{path}/backups/manual', exist_ok=True)
    name = f"{path}/backups/manual/{time.strftime('%Y-%m-%d_%H%M')}.sql.gz"
    run(f"{' '.join(compose(path))} exec -T db sh -c 'mariadb-dump -uroot -p\"$MARIADB_ROOT_PASSWORD\" --single-transaction --routines --triggers \"$MARIADB_DATABASE\"' | gzip > {name}", cwd=path)
    return {'slug': job.get('slug'), 'file': os.path.basename(name), 'size': os.path.getsize(name)}


def inventory(job):
    out = []
    for slug, path in [('edusuite', TEST)] + [(os.path.basename(p), p) for p in sorted(glob.glob(f'{SCHOOLS}/*')) if os.path.isdir(p)]:
        env, denv = read_env(f'{path}/.env'), read_env(f'{path}/.env.docker')
        dumps = sorted(glob.glob(f'{path}/backups/**/*.sql.gz', recursive=True), key=os.path.getmtime)
        health = ''
        try:
            health = urllib.request.urlopen(f"http://127.0.0.1:{env.get('APP_PORT', '8003')}/healthz", timeout=5).read().decode()[:40]
        except Exception as e:
            health = 'down'
        out.append({'slug': slug, 'url': denv.get('APP_URL'), 'version': env.get('IMAGE_TAG'), 'port': env.get('APP_PORT', '8003'),
                    'healthy': 'ok' in health, 'last_backup': time.strftime('%Y-%m-%d %H:%M', time.localtime(os.path.getmtime(dumps[-1]))) if dumps else None,
                    'backups': len(dumps), 'disk_mb': int(run(['du', '-sm', path], check=False).split()[0] or 0) if os.path.isdir(path) else 0,
                    'has_token': bool(denv.get('CONTROL_API_TOKEN'))})
    return {'schools': out}


HANDLERS = {'create_school': create_school, 'issue_token': issue_token, 'set_env': set_env, 'restart': restart, 'backup': backup, 'inventory': inventory}


def main():
    os.makedirs(f'{JOBS}/done', exist_ok=True)
    for f in sorted(glob.glob(f'{JOBS}/pending/*.json')):
        jid = os.path.basename(f)[:-5]
        if not re.match(r'^[A-Za-z0-9-]{8,64}$', jid):
            os.remove(f)
            continue
        running = f'{JOBS}/running-{jid}.json'
        try:
            os.rename(f, running)
        except FileNotFoundError:
            continue
        result = {'id': jid, 'started_at': time.strftime('%Y-%m-%d %H:%M:%S')}
        try:
            job = json.load(open(running))
            handler = HANDLERS.get(job.get('type'))
            if not handler:
                raise ValueError('Unknown job.')
            result.update({'ok': True, 'result': handler(job.get('payload') or {})})
        except Exception as e:
            result.update({'ok': False, 'error': str(e)[:1000]})
        result['finished_at'] = time.strftime('%Y-%m-%d %H:%M:%S')
        done = f'{JOBS}/done/{jid}.json'
        open(done, 'w').write(json.dumps(result))
        os.chmod(done, 0o640)
        shutil.chown(done, 'root', 'www-data')
        os.remove(running)


if __name__ == '__main__':
    main()
