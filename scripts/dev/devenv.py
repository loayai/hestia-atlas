"""Development helpers: read the local .env (never printed) and talk to the dev server.

Usage:
  python scripts/dev/devenv.py bootstrap-key   # install a local SSH key on the dev server
  python scripts/dev/devenv.py host            # print "user@host:port" (no secrets)

The .env file may be UTF-8 or UTF-16; only the variable names documented in
README (SERVER_HOST, SERVER_PORT, SERVER_USER, SERVER_PASSWORD) are used.
"""
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
KEY = Path.home() / ".ssh" / "hestia_atlas_dev_ed25519"


def load_env():
    raw = (ROOT / ".env").read_bytes()
    for enc in ("utf-16", "utf-8-sig", "latin-1"):
        try:
            text = raw.decode(enc)
            if "SERVER_HOST" in text:
                break
        except UnicodeError:
            continue
    env = {}
    for line in text.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def bootstrap_key():
    import paramiko

    env = load_env()
    if not KEY.exists():
        KEY.parent.mkdir(exist_ok=True)
        os.system(f'ssh-keygen -q -t ed25519 -N "" -C hestia-atlas-dev -f "{KEY}"')
    pub = KEY.with_suffix(".pub").read_text().strip()
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(env["SERVER_HOST"], port=int(env.get("SERVER_PORT", 22)),
              username=env["SERVER_USER"], password=env["SERVER_PASSWORD"],
              look_for_keys=False, allow_agent=False, timeout=20)
    cmd = ("umask 077; mkdir -p ~/.ssh; touch ~/.ssh/authorized_keys; "
           f"grep -qF '{pub}' ~/.ssh/authorized_keys || echo '{pub}' >> ~/.ssh/authorized_keys; echo ok")
    _, out, err = c.exec_command(cmd)
    print(out.read().decode().strip(), err.read().decode().strip())
    c.close()


if __name__ == "__main__":
    action = sys.argv[1] if len(sys.argv) > 1 else "host"
    if action == "bootstrap-key":
        bootstrap_key()
    elif action == "host":
        e = load_env()
        print(f'{e["SERVER_USER"]}@{e["SERVER_HOST"]} -p {e.get("SERVER_PORT", "22")}')
