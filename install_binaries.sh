#!/bin/bash
set -e

export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
cd $(dirname "${BASH_SOURCE[0]}")
workingdir=$(pwd)

# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y apt-transport-tor bash-completion bind9 ca-certificates clamav-daemon clamav-freshclam curl dovecot-imapd dovecot-lmtpd dovecot-pop3d git gnupg haveged iptables libsasl2-modules locales locales-all logrotate lsb-release mariadb-server mercurial nano nginx openssl php8.1-cli php8.1-curl php8.1-fpm php8.1-gd php8.1-gmp php8.1-gnupg php8.1-imap php8.1-intl php8.1-mbstring php8.1-mysql php8.1-pspell php8.1-readline postfix postfix-mysql prosody redis tor vim wget unzip wireguard wireguard-tools
# build dependencies
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y autoconf automake cmake g++ gcc libcurl4-openssl-dev libglib2.0-dev libicu-dev libluajit-5.1-dev libpcre3-dev libsodium-dev libsqlite3-dev libssl-dev libtool make ragel zlib1g-dev

# initial repository clones
if [ ! -e rspamd ]; then
	git clone --recurse-submodules https://github.com/rspamd/rspamd.git
fi
export PROC_LIMIT=`free -g | grep Mem | awk -v nproc=$(nproc) '{print (($2 + 1) < nproc) ? ($2 + 1) : nproc;}'`
# start build
cd rspamd
git fetch --all --recurse-submodules
git checkout 3.8.4 --recurse-submodules
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

# rspamd user
id -u _rspamd >/dev/null 2>&1 ||useradd -M -r -s /bin/false -d /var/lib/rspamd _rspamd
mkdir -p /var/lib/rspamd
chown _rspamd: /var/lib/rspamd

# mysql encryption
if [ ! -e /etc/mysql/encryption/keyfile.enc ]; then
	mkdir -p /etc/mysql/encryption/
	openssl rand -hex 128 > /etc/mysql/encryption/keyfile.key
	echo "1;"$(openssl rand -hex 32) | openssl enc -aes-256-cbc -md sha1 -pass file:/etc/mysql/encryption/keyfile.key -out /etc/mysql/encryption/keyfile.enc
fi

#install scripts
mkdir -p /var/www/mail
mkdir -p /var/www/html
if [ ! -e /var/www/html/mail ]; then
	ln -s ../mail/www /var/www/html/mail
fi
cp -r composer.json cron.php setup.php www /var/www/mail/
cd /var/www/mail/
composer install --no-dev


# install squirrelmail
if [ ! -e /var/www/mail/www/squirrelmail ]; then
	mkdir -p /var/www/mail/www/squirrelmail
	cd /var/www/mail/www/squirrelmail
	git clone https://github.com/RealityRipple/squirrelmail .
	mkdir -p /var/local/squirrelmail/data /var/local/squirrelmail/attach
	chown www-data:www-data -R /var/local/squirrelmail
else
	cd /var/www/mail/www/squirrelmail
	git fetch --all
	git pull
fi

# copy configuration file
cd $workingdir
if [ ! -e /var/www/mail/common_config.php ]; then
	cp common_config.php /var/www/mail/
else
	echo "The script common_config.php was not overridden. Pleas compare manually if changes are necessary."
fi
