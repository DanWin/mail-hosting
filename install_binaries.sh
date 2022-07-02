#!/bin/sh
set -e

export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y apt-transport-tor bash-completion bind9 ca-certificates clamav-daemon clamav-freshclam curl dovecot-imapd dovecot-lmtpd dovecot-pop3d git gnupg haveged iptables libsasl2-modules locales locales-all logrotate lsb-release mariadb-server mercurial nano nginx openssl php8.1-cli php8.1-curl php8.1-fpm php8.1-gd php8.1-gmp php8.1-imap php8.1-intl php8.1-mbstring php8.1-mysql php8.1-pspell php8.1-readline postfix postfix-mysql prosody redis tor vim wget wireguard wireguard-tools
# build dependencies
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y autoconf automake cmake g++ gcc libcurl4-openssl-dev libglib2.0-dev libicu-dev libpcre3-dev libsodium-dev libsqlite3-dev libssl-dev libtool make ragel zlib1g-dev

# initial repository clones
if [ ! -e rspamd ]; then
	git clone --recurse-submodules https://github.com/rspamd/rspamd.git
fi
export PROC_LIMIT=`free -g | grep Mem | awk -v nproc=$(nproc) '{print (($2 + 1) < nproc) ? ($2 + 1) : nproc;}'`
#start build
cd rspamd
git fetch --all --recurse-submodules
git checkout 3.2 --recurse-submodules
cd ..
mkdir -p rspamd_build
cd rspamd_build
cmake ../rspamd -DENABLE_LUAJIT=ON -DCMAKE_BUILD_TYPE=Release
make -j $PROC_LIMIT
make install
cd ..
rm -rf rspamd_build
ldconfig

# install composer
curl -sSL https://github.com/composer/composer/releases/download/2.3.8/composer.phar > /usr/bin/composer
chmod +x /usr/bin/composer
composer self-update

#rspamd user
id -u _rspamd >/dev/null 2>&1 ||useradd -M -r -s /bin/false -d /var/lib/rspamd _rspamd
mkdir -p /var/lib/rspamd
chown _rspamd: /var/lib/rspamd
