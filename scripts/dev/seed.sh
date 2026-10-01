#!/bin/bash
# Seed a Hestia development server with representative test data.
# Usage (on the dev server, as root):  TEST_PASSWORD=... bash seed.sh
# The test-user password is read from the environment; every other secret is random.
set -u
BIN=/usr/local/hestia/bin
rnd() { openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 18; }
: "${TEST_PASSWORD:?TEST_PASSWORD must be set}"

ensure_user() { # user email package name
	$BIN/v-list-user "$1" >/dev/null 2>&1 || $BIN/v-add-user "$1" "${5:-$(rnd)}" "$2" "$3" "$4"
}

ensure_user jdemo jdemo@example.com default "Jamie Demo" "$TEST_PASSWORD"
ensure_user acme ops@acme-demo.org default "Acme Corp"
ensure_user oldsite owner@oldsite-demo.org default "Old Site Ltd"

for d in atlas-demo.com shop-demo.net; do
	$BIN/v-list-web-domain jdemo $d >/dev/null 2>&1 || $BIN/v-add-domain jdemo $d
done
$BIN/v-list-web-domain jdemo blog.atlas-demo.com >/dev/null 2>&1 || $BIN/v-add-web-domain jdemo blog.atlas-demo.com
$BIN/v-list-web-domain acme acme-demo.org >/dev/null 2>&1 || $BIN/v-add-domain acme acme-demo.org
$BIN/v-list-web-domain oldsite oldsite-demo.org >/dev/null 2>&1 || $BIN/v-add-domain oldsite oldsite-demo.org

for a in info sales support; do
	$BIN/v-list-mail-account jdemo atlas-demo.com $a >/dev/null 2>&1 || $BIN/v-add-mail-account jdemo atlas-demo.com $a "$(rnd)" 1024
done
$BIN/v-list-mail-account jdemo shop-demo.net orders >/dev/null 2>&1 || $BIN/v-add-mail-account jdemo shop-demo.net orders "$(rnd)" unlimited

$BIN/v-list-database jdemo jdemo_wp >/dev/null 2>&1 || $BIN/v-add-database jdemo wp wp "$(rnd)" mysql
$BIN/v-list-database jdemo jdemo_shop >/dev/null 2>&1 || $BIN/v-add-database jdemo shop shop "$(rnd)" mysql

if [ "$($BIN/v-list-cron-jobs jdemo plain | wc -l)" -lt 2 ]; then
	$BIN/v-add-cron-job jdemo 0 3 '*' '*' '*' "php /home/jdemo/web/atlas-demo.com/public_html/cron.php >/dev/null 2>&1"
	$BIN/v-add-cron-job jdemo '*/15' '*' '*' '*' '*' "curl -s https://shop-demo.net/queue >/dev/null"
	$BIN/v-add-cron-job jdemo 30 2 '*' '*' 0 "/home/jdemo/bin/cleanup.sh"
fi

$BIN/v-list-user oldsite plain | grep -q 'yes' || $BIN/v-suspend-user oldsite >/dev/null 2>&1

# One backup for the backup list (runs in the background; takes a minute)
if [ -z "$($BIN/v-list-user-backups jdemo plain)" ]; then
	nohup $BIN/v-backup-user jdemo >/dev/null 2>&1 &
fi
echo "seed complete"
