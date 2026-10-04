#!/bin/bash
# One-time setup of the LAV SMS test server (Ubuntu). Run from your laptop with the AWS key:
#
#   ssh -i aws-key.pem ubuntu@SERVER_IP "bash -s -- '$(cat ~/.ssh/lav_sms_server.pub)'" < deploy/server-setup.sh
#
# It adds your deploy key, installs Docker, and creates ~/lav-sms.git: pushing to it checks the
# code out into ~/lav-sms and rebuilds/restarts the containers.
set -e
PUBKEY="$1"

# 1. Allow the laptop's deploy key
mkdir -p ~/.ssh && chmod 700 ~/.ssh
touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys
if [ -n "$PUBKEY" ] && ! grep -qF "$PUBKEY" ~/.ssh/authorized_keys; then
    echo "$PUBKEY" >> ~/.ssh/authorized_keys
    echo "Deploy key added."
fi

# 2. Docker and git
if ! command -v docker >/dev/null; then
    curl -fsSL https://get.docker.com | sudo sh
    sudo usermod -aG docker "$USER"
fi
command -v git >/dev/null || sudo apt-get install -y git

# 3. Bare repository + working copy
mkdir -p ~/lav-sms/docker/seed
if [ ! -d ~/lav-sms.git ]; then
    git init --bare -b main ~/lav-sms.git
fi

cat > ~/lav-sms.git/hooks/post-receive <<'HOOK'
#!/bin/bash
# Deploy on every push to main: check out the code, then rebuild and restart.
set -e
TARGET="$HOME/lav-sms"
while read -r old new ref; do
    [ "$ref" = "refs/heads/main" ] || { echo "Ignoring $ref (only main is deployed)."; continue; }
    echo "==> Checking out main into $TARGET"
    git --work-tree="$TARGET" --git-dir="$HOME/lav-sms.git" checkout -f main
    cd "$TARGET"
    if [ ! -f .env.docker ]; then
        echo "==> First push done. Now create $TARGET/.env.docker (see docs/DEPLOY.md), then push again"
        echo "    or run: cd $TARGET && docker compose up -d --build"
        exit 0
    fi
    echo "==> Building and restarting containers"
    sg docker -c "docker compose up -d --build"
    sg docker -c "docker image prune -f" >/dev/null
    echo "==> Deployed."
done
HOOK
chmod +x ~/lav-sms.git/hooks/post-receive

echo
echo "Server ready. Log out and back in once so the docker group applies."
echo "On your laptop:  git push server main"
