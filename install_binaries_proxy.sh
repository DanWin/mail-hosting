#!/bin/sh
set -e

export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y bash-completion bind9 ca-certificates coturn curl git gnupg haveged iptables libnginx-mod-stream libnginx-mod-http-brotli-filter libsasl2-modules logrotate lsb-release nano nginx openssl postfix postfix-mysql postfix-mta-sts-resolver rng-tools5 vim wget wireguard wireguard-tools

# dhparams
for file in /etc/nginx/dh4096.pem; do
	if [ ! -e "$file" ]; then
		openssl dhparam -out "$file" 4096
	fi
done

rm -f /etc/nginx/sites-enabled/default
