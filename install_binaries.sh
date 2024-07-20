#!/bin/bash
set -e

export LANG=C.UTF-8
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
cd $(dirname "${BASH_SOURCE[0]}")
workingdir=$(pwd)

# install all required packages
DEBIAN_FRONTEND=noninteractive apt-get update
DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends install -y apt-transport-tor bash-completion bind9 ca-certificates clamav-daemon clamav-freshclam curl dovecot-imapd dovecot-lmtpd dovecot-mysql dovecot-pop3d git gnupg haveged iptables libnginx-mod-http-brotli-filter libsasl2-modules locales locales-all logrotate lsb-release lua-dbi-mysql lua-event lua-unbound mariadb-server mercurial nano nginx openssl php8.2-cli php8.2-curl php8.2-fpm php8.2-gd php8.2-gmp php8.2-gnupg php8.2-imap php8.2-intl php8.2-mbstring php8.2-mysql php8.2-pspell php8.2-readline php8.2-tidy php8.2-uuid php8.2-xml php8.2-zip postfix postfix-mysql prosody redis rng-tools5 rspamd tor vim wget unzip wireguard wireguard-tools

# install composer
curl -sSL https://github.com/composer/composer/releases/download/2.7.7/composer.phar > /usr/bin/composer
chmod +x /usr/bin/composer
composer self-update

# mysql encryption
if [ ! -e /etc/mysql/encryption/keyfile.enc ]; then
	mkdir -p /etc/mysql/encryption/
	openssl rand -hex 128 > /etc/mysql/encryption/keyfile.key
	echo "1;"$(openssl rand -hex 32) | openssl enc -aes-256-cbc -md sha1 -pass file:/etc/mysql/encryption/keyfile.key -out /etc/mysql/encryption/keyfile.enc
fi
# dovecot encryption
if [ ! -e /etc/dovecot/ecprivkey.pem ]; then
	mkdir -p /etc/dovecot/
	openssl ecparam -name secp521r1 -genkey | openssl pkey -out /etc/dovecot/ecprivkey.pem
	openssl pkey -in /etc/dovecot/ecprivkey.pem -pubout -out /etc/dovecot/ecpubkey.pem
fi
# postifx certificates
if [ ! -e /etc/postfix/danwin1210-mail.chain ]; then
	openssl req -x509 -nodes -days 3650 -newkey ed448 -subj "/" -keyout /etc/postfix/danwin1210-mail.key -out /etc/postfix/danwin1210-mail.crt && cat /etc/postfix/danwin1210-mail.key >> /etc/postfix/danwin1210-mail.chain && cat /etc/postfix/danwin1210-mail.crt >> /etc/postfix/danwin1210-mail.chain
fi

# dhparams
for file in /etc/nginx/dh4096.pem /etc/dovecot/dh.pem /etc/prosody/dh4096.pem; do
	if [ ! -e "$file" ]; then
		openssl dhparam -out "$file" 4096
	fi
done

# vmail user
id -u vmail > /dev/null 2>&1 || (groupadd -g 5000 -r vmail && useradd -g 5000 -M -r -s /bin/false -u 5000 vmail -d /var/mail/vmail)
mkdir -p /var/mail/vmail
chown vmail: /var/mail/vmail

#install scripts
mkdir -p /var/www/mail
mkdir -p /var/www/html
mkdir -p /var/lib/rspamd/dkim
chown _rspamd: /var/lib/rspamd/dkim
if [ ! -e /var/www/html/mail ]; then
	ln -s ../mail/www /var/www/html/mail
fi
cp -r composer.json cron.php setup.php www tools locale /var/www/mail/
cd /var/www/mail/
composer install --no-dev


# install squirrelmail
if [ ! -e /var/www/mail/www/squirrelmail ]; then
	mkdir -p /var/www/mail/www/squirrelmail
	cd /var/www/mail/www/squirrelmail
	git clone https://github.com/RealityRipple/squirrelmail .
	mkdir -p /var/local/squirrelmail/data /var/local/squirrelmail/attach
	chown www-data:www-data -R /var/local/squirrelmail
	cd $workingdir
	cp squirrelmail_config.php /var/www/mail/www/squirrelmail/config/config.php
else
	cd /var/www/mail/www/squirrelmail
	git fetch --all
	git pull
fi

if [ ! -e /var/www/mail/www/squirrelmail/plugins/check_quota/ ]; then
	cd /var/www/mail/www/squirrelmail/plugins/
	wget https://www.squirrelmail.org/plugins/check_quota-2.2-1.4.0.tar.gz
	tar -zxf check_quota-2.2-1.4.0.tar.gz
	rm check_quota-2.2-1.4.0.tar.gz
	cd $workingdir
	cp squirrelmail_plugin_hooks.php /var/www/mail/www/squirrelmail/config/plugin_hooks.php
	patch -p1 -d /var/www/html/mail/squirrelmail/plugins/check_quota/ < squirrelmail_check_quota.patch
fi

# install snappymail
mkdir -p /var/www/mail/www/snappymail
cd /var/www/mail/www/snappymail
VERSION=$(curl -s https://api.github.com/repos/the-djmaze/snappymail/releases/latest | grep tag_name | cut -d '"' -f 4)
wget https://github.com/the-djmaze/snappymail/releases/download/${VERSION}/snappymail-${VERSION:1}.zip
unzip -o snappymail-${VERSION:1}.zip
rm snappymail-${VERSION:1}.zip
mkdir -p /var/local/snappymail
chown www-data:www-data -R /var/local/snappymail
if [ ! -e include.php ]; then
	cp _include.php include.php
	echo "define('APP_DATA_FOLDER_PATH', '/var/local/snappymail/');" >> include.php
	echo "define('SNAPPYMAIL_UPDATE_PLUGINS', 1);" >> include.php
fi

# install converse.js
rm -rf /srv/conversejs
mkdir -p /srv/conversejs
cd /srv/conversejs
VERSION=$(curl -s https://api.github.com/repos/conversejs/converse.js/releases/latest | grep tag_name | cut -d '"' -f 4)
wget https://github.com/conversejs/converse.js/releases/download/${VERSION}/converse.js-${VERSION:1}.tgz
tar xzf converse.js-${VERSION:1}.tgz --strip-components=1
rm converse.js-${VERSION:1}.tgz

rm -f /etc/nginx/sites-enabled/default

# install prosody modules
if [ ! -e /srv/prosody-modules ]; then
	hg clone https://hg.prosody.im/prosody-modules/ /srv/prosody-modules
else
	cd /srv/prosody-modules
	hg pull --update
fi

# copy configuration file
cd $workingdir
if [ ! -e /var/www/mail/common_config.php ]; then
	cp common_config.php /var/www/mail/
else
	echo "The script common_config.php was not overridden. Pleas compare manually if changes are necessary."
fi
