#!/bin/bash
# Installs (or updates) the EduNetwork server helper. Run with sudo on the server; the CD pipeline runs it on every deploy.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
mkdir -p /opt/edunetwork/agent /opt/edunetwork/jobs/pending /opt/edunetwork/jobs/done
install -m 755 -o root -g root "$here/agent.py" /opt/edunetwork/agent/agent.py
# The Control Centre (www-data inside its container, gid 33) may drop jobs and read results; nothing else.
chown root:33 /opt/edunetwork/jobs /opt/edunetwork/jobs/pending /opt/edunetwork/jobs/done
chmod 2770 /opt/edunetwork/jobs/pending
chmod 2750 /opt/edunetwork/jobs /opt/edunetwork/jobs/done

cat > /etc/systemd/system/edunetwork-agent.service <<'EOF'
[Unit]
Description=EduNetwork server helper (jobs from the LEF Control Centre)

[Service]
Type=oneshot
ExecStart=/usr/bin/python3 /opt/edunetwork/agent/agent.py
TimeoutStartSec=1800
EOF

cat > /etc/systemd/system/edunetwork-agent.path <<'EOF'
[Unit]
Description=Start the EduNetwork server helper when a job arrives

[Path]
PathExistsGlob=/opt/edunetwork/jobs/pending/*.json
Unit=edunetwork-agent.service

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now edunetwork-agent.path >/dev/null
echo "EduNetwork server helper installed."
