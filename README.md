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
apt-get update && apt-get install git -y && git clone https://github.com/DanWin/mail-hosting && cd mail-hosting
```

Install files and programs
```
./install_binaries.sh
```

Copy (and modify according to your needs) the site files in `etc` to `/etc` after installation has finished. Then restart some services:
```
systemctl daemon-reload && systemctl restart tor@default.service
```

Replace the default .onion domain with your domain:
```
sed -i "s/danielas3rtn54uwmofdo3x2bsdifr47huasnmbgqzfrec5ubupvtpid.onion/`cat /var/lib/tor/hidden_service/hostname`/g" /etc/prosody/prosody.cfg.lua /etc/nginx/sites-enabled/mail /var/www/mail/common_config.php /etc/postfix/main.cf
```

Replace the default clearnet domain with your domain:
```
sed -i "s/danwin1210.de/YOUR_DOMAIN/g" /etc/prosody/prosody.cfg.lua /etc/postfix/main.cf /etc/dovecot/dovecot.conf /etc/nginx/sites-enabled/* /var/www/mail/common_config.php
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
GRANT SELECT ON postfix.* TO 'postfix_readonly'@'%';
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

Create a password used for your TURN server and replace all `YOUR_SECRET` in `/etc/prosody/prosody.cfg.lua` with it.

Install [acme.sh](https://github.com/acmesh-official/acme.sh) or [certbot](https://certbot.eff.org/) to obtain a free letsencrypt SSL certificate, then update the path to this new certificate in the following files:
```
nano /etc/prosody/prosody.cfg.lua /etc/dovecot/dovecot.conf /etc/postfix/main.cf /etc/nginx/nginx.conf /etc/nginx/sites-enabled/mail /etc/nginx/sites-enabled/openpgpkey
```

Create database tables, activate firewall and enable cron:
```
postmap /etc/postfix/header_checks
cd /var/www/mail && php setup.php && chmod +x /etc/rc.local && /etc/rc.local && systemctl enable mail-cron.timer
```

Final step is to reboot the server and check that everything is working.

### Proxy server:

To send emails to the regular internet, it is necessary to have a static IP to retain a reputation with an IP+Domain mapping. If you try sending via Tor, your emails will most certainly get blocked by spam filters. For this reason we need to setup a proxy server which will hold no user data itself, but simply act as a gateway to reach the less anonymous part of the internet.

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
apt-get update && apt-get install git -y && git clone https://github.com/DanWin/mail-hosting && cd mail-hosting
```

Install files and programs
```
./install_binaries_proxy.sh
```

Copy (and modify according to your needs) the site files in `etc_clearnet_proxy` to `/etc` after installation has finished.

Add the password for your TURN server you created for prosody in the main server and replace `YOUR_AUTH_SECRET` in `/etc/turnserver.conf` with it.

Install [acme.sh](https://github.com/acmesh-official/acme.sh) or [certbot](https://certbot.eff.org/) to obtain a free letsencrypt SSL certificate, then update the path to this new certificate in the following files:
```
nano /etc/postfix/main.cf /etc/nginx/nginx.conf /etc/turnserver.conf
```


### General Domain settings

Add the following DNS records to your domain, with the IPs of your proxy server:
```
@    IN    TXT    "v=spf1 ip4:your.ip.v4.address ip6:your:ip:v6:address -all"
_dmarc    IN    TXT "v=DMARC1;p=quarantine;adkim=r;aspf=r;fo=1;rua=mailto:postmaster@yourdomain;ruf=mailto:postmaster@yourdomain;rf=afrf;ri=86400;pct=100"
_adsp._domainkey	IN	TXT	"dkim=all;"
_domainkey	IN	TXT "o=-;r=postmaster@yourdomain"
*._report._dmarc	IN	TXT "v=DMARC1"
_mta-sts    IN  TXT "v=STSv1; id=2024060601"
_smtp._tls  IN  TXT "v=TLSRPTv1; rua=mailto:postmaster@yourdomain"
_imaps._tcp	IN	SRV	0 0 993 yourdomain.
_submission._tcp	IN	SRV	0 0 465 yourdomain.
@	IN	MX	0 yourdomain.
@	IN	A	your.ip.v4.address
@	IN	AAAA	your:ip:v6:address
www	IN	A	your.ip.v4.address
www	IN	AAAA	your:ip:v6:address
mta-sts	IN	A	your.ip.v4.address
mta-sts	IN	AAAA	your:ip:v6:address
conference	IN	A	your.ip.v4.address
conference	IN	AAAA	your:ip:v6:address
proxy	IN	A	your.ip.v4.address
proxy	IN	AAAA	your:ip:v6:address
upload	IN	A	your.ip.v4.address
upload	IN	AAAA	your:ip:v6:address
_xmpp-server._tcp.conference	IN	SRV	5 0 5269 yourdomain.
_xmpp-server._tcp.conference	IN	SRV	0 0 5269 your_onion_domain.
_xmpp-client._tcp	IN	SRV	5 0 5222 yourdomain.
_xmpp-client._tcp	IN	SRV	0 0 5222 your_onion_domain.
_xmpps-client._tcp	IN	SRV	5 0 5223 yourdomain.
_xmpps-client._tcp	IN	SRV	0 0 5223 your_onion_domain.
_xmpp-server._tcp	IN	SRV	5 0 5269 yourdomain.
_xmpp-server._tcp	IN	SRV	0 0 5269 your_onion_domain.
_stun._udp	IN	SRV	0 0 3478 yourdomain.
_turn._udp	IN	SRV	0 0 3478 yourdomain.
_stun._tcp	IN	SRV	0 0 3478 yourdomain.
_stuns._tcp	IN	SRV	0 0 3479 yourdomain.
_turn._tcp	IN	SRV	0 0 3478 yourdomain.
_turns._tcp	IN	SRV	0 0 5349 yourdomain.
_xmppconnect	IN	TXT	"_xmpp-client-xbosh=https://yourdomain:5281/http-bind"
_xmppconnect	IN	TXT	"_xmpp-client-websocket=wss://yourdomain:5281/xmpp-websocket"
```

Set the PTR record of your proxy servers IPs to your domain. This can usually be done from your hosting panels configuration, but may not be available with every hosting provider, where you can then request them to do it via a support ticket.

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
