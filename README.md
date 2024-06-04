General Information:
--------------------

This is a setup for a Tor based email hosting server. It is provided as is and before putting it into production you should make changes according to your needs. This is a work in progress and you should carefully check the commit history for changes before updating.

Installation Instructions:
--------------------------

### Primary mail server with Tor:

Uninstall packages that may interfere with this setup:
```
DEBIAN_FRONTEND=noninteractive apt-get purge -y apache2* dnsmasq* eatmydata exim4* imagemagick-6-common mysql-client* mysql-server* nginx* libnginx-mod* php7* resolvconf && systemctl disable systemd-resolved.service && systemctl stop systemd-resolved.service
```

If you have problems resolving hostnames after this step, temporarily switch to a public nameserver like 1.1.1.1 (from CloudFlare) or 8.8.8.8 (from Google)

```
rm /etc/resolv.conf && echo "nameserver 1.1.1.1" > /etc/resolv.conf
```

Install git and clone this repository

```
apt-get update && apt-get install git && git clone https://github.com/DanWin/mail-hosting && cd mail-hosting
```

Install files and programs
```
./install_binaries.sh
```

Copy (and modify according to your needs) the site files in `etc` to `/etc` after installation has finished. Then restart some services:
```
systemctl daemon-reload && systemctl restart bind9.service && systemctl restart tor@default.service
```

Replace the default .onion domain with your domain:
```
sed -i "s/danielas3rtn54uwmofdo3x2bsdifr47huasnmbgqzfrec5ubupvtpid.onion/`cat /var/lib/tor/hidden_service/hostname`/g" /etc/prosody/prosody.cfg.lua /etc/nginx/sites-enabled/mail /var/www/mail/common_config.php /etc/postfix/main.cf
```

Replace the default clearnet domain with your domain:
```
sed -i "s/danwin1210.de/YOUR_DOMAIN/g" /etc/prosody/prosody.cfg.lua /etc/postfix/main.cf /etc/dovecot/dovecot.conf /etc/nginx/sites-enabled/mail /etc/nginx/sites-enabled/openpgpkey /var/www/mail/common_config.php
```

Create a mysql users and databases:
```
mysql
CREATE DATABASE postfix;
CREATE DATABASE prosody;
CREATE USER 'postfix'@'%' IDENTIFIED BY 'MY_PASSWORD';
CREATE USER 'postfix_readonly'@'%' IDENTIFIED BY 'MY_PASSWORD';
CREATE USER 'prosody'@'%' IDENTIFIED BY 'MY_PASSWORD';
GRANT ALL PRIVILEGES ON postfix.* TO 'postfix'@'%';
GRANT SELECT ON postifx.* TO 'postfix_readonly'@'%';
GRANT ALL PRIVILEGES ON prosody.* TO 'prosody'@'%';
FLUSH PRIVILEGES;
quit
```

Then update the passwords you've set in your configuration files:
```
nano /etc/dovecot/dovecot-dict-sql.conf.ext /etc/dovecot/dovecot-sql.conf.ext /etc/postfix/sql/mysql_* /etc/prosody/prosody.cfg.lua /var/www/mail/common_config.php
```

Generate a keypair for rspamd with `rspamadm keypair gen` and add it to /etc/rspamd/local.d/worker-fuzzy.inc, add the public encryption key to /etc/rspamd/override.d/fuzzy_check.conf

Set a password for the web interface with `rspamadm pw` and add the hash for it to /etc/rspamd/override.d/worker-controller.inc

Generate DKIM signing keys and add them to /etc/rspamd/local.d/arc.conf /etc/rspamd/local.d/dkim_signing.conf, then add the printed DNS records to your domain:
```
rspamadm dkim_keygen -d YOUR_DOMAIN -s $(date +"%Y%m%d")-rsa -b 4096 -t rsa -k /var/lib/rspamd/dkim/YOUR_DOMAIN-rsa
rspamadm dkim_keygen -d YOUR_DOMAIN -s $(date +"%Y%m%d")-ed25519 -t ed25519 -k /var/lib/rspamd/dkim/YOUR_DOMAIN-ed25519
```

Install [acme.sh](https://github.com/acmesh-official/acme.sh) or [certbot](https://certbot.eff.org/) to obtain a free letsencrypt SSL certificate, then update the path to this new certificate in the following files:
```
nano /etc/prosody/prosody.cfg.lua /etc/dovecot/dovecot.conf /etc/postfix/main.cf /etc/nginx/nginx.conf /etc/nginx/sites-enabled/mail /etc/nginx/sites-enabled/openpgpkey
```
After copying (and modifying) the posfix configuration, you need to create databases out of the mapping files (also each time you update those files) (DanWin will have to add these commands, I didn't find any more (DEAM0)):
```
postmap /etc/postfix/helo_checks
```

Create database tables, activate firewall and enable cron:
```
cd /var/www/mail && php setup.php && chmod +x /etc/rc.local && /etc/rc.local && systemctl enable mail-cron.timer
```

To send emails to the regular internet, it is necessary to have a static IP to retain a reputation with an IP+Domain mapping. If you try sending via Tor, your emails will most certainly get blocked by spam fitlers. For this reason we need to setup a proxy server which will hold no user data itself, but simply act as a gateway to reach the less anonymous part of the internet.

### Proxy server:

TODO

### General Domain settings

Add the following DNS records to your domain, with the IPs of your proxy server:
```
@    IN    TXT    "v=spf1 ip4:your.ip.v4.address ip6:your:ip:v6:address -all"
_dmarc    IN    TXT "v=DMARC1;p=quarantine;adkim=r;aspf=r;fo=1;rua=mailto:postmaster@yourdomain;ruf=mailto:postmaster@yourdomain;rf=afrf;ri=86400;pct=100"
@	IN	MX	0 yourdomain.
```

Set the PTR record of your servers IPs to your domain. This can usually be done from your hosting panels configuration, but may not be available with every hosting provider, where you can then request them to do it via a support ticket.

Consider registering your domain with [DNSWL](https://www.dnswl.org/), [SNDS](https://sendersupport.olc.protection.outlook.com/snds/), [Google Postmaster Tools](https://postmaster.google.com/) and [YahooCFL](https://senders.yahooinc.com/complaint-feedback-loop/) for valuable insights into your delivery.


Translating:
------------

Translations are managed in [Weblate](https://weblate.danwin1210.de/projects/DanWin/mail-hosting).
If you prefer manually submitting translations, the script `update-translations.sh` can be used to update the language template and translation files from source.
It will generate the file `locale/mail-hosting.pot` which you can then use as basis to create a new language file in `YOUR_LANG_CODE/LC_MESSAGES/mail-hosting.po` and edit it with a translation program, such as [Poedit](https://poedit.net/).
Once you are done, you can open a pull request, or [email me](mailto:daniel@danwin1210.de), to include the translation.

Live demo:
----------

If you want to see the script in action, and/or register for a free anonymous E-Mail address, you can visit my [Tor hidden service](http://danielas3rtn54uwmofdo3x2bsdifr47huasnmbgqzfrec5ubupvtpid.onion/mail/) or [my clearnet proxy](https://danwin1210.de/mail/) if you don't have Tor installed.
