#!/bin/bash
#
# Encrypt/Decrypt/Check emails with Dovecot's mail-crpyt-plugin
# This script will encrypt/decrypt emails in-place
# Please read: https://wiki.dovecot.org/Design/Dcrypt and https://wiki2.dovecot.org/Plugins/MailCrypt
#
# Update variables with your keys and patch otherwise you will loose data!
#
# I take no responsibility for data loos this script may cause
#
# IMPORTANT:
# BEFORE USE ADD THIS MAGIC(5) TO YOUR LOCAL MAGIC DATABASE:
#/etc/magic and /etc/magic.mime:
#0	string	CRYPTED	MailCrypt
#!:mime application/mail-crypt

count=0
processed=0
tempfile=$(mktemp)

uid=5000
gid=5000
maildir_path=$(pwd)
private_key_path=/etc/dovecot/ecprivkey.pem
public_key_path=/etc/dovecot/ecpubkey.pem

if [ "$1" == "" ]; then
    echo "Missing user folder"
    exit 1
fi

case $2 in
  encrypt) mode=encrypt; text_d="Encrypting"
  ;;
  decrypt) mode=decrypt; text_d="Decrypting"
  ;;
  check) mode=check; text_d="Checking"
  ;;
  *)  echo "Unknown mode. Modes: [encrypt|decrypt|check]"; exit 1
esac

_encrypt(){
  touch -r "$mailmessage" $tempfile
  doveadm fs put compress gz:9:crypt:private_key_path=$private_key_path:public_key_path=$public_key_path:posix:prefix=$maildir_path/$userdir/ "$mailmessage" "$mailmessage"
  touch -r $tempfile "$mailmessage"
  chown $uid:$gid "$mailmessage"
}

_decrypt(){
  touch -r "$mailmessage" $tempfile
  doveadm fs get compress maybe-gz:9:crypt:private_key_path=$private_key_path:public_key_path=$public_key_path:posix:prefix=$maildir_path/$userdir/ "$mailmessage" > .tempdecrypted
  mv .tempdecrypted "$mailmessage"
  touch -r $tempfile "$mailmessage"
  chmod 0600 "$mailmessage"
  chown $uid:$gid "$mailmessage"
}

userdir="$1"

if [ ! -d $maildir_path/$userdir/ ];then
  echo "Folder do not exist: $maildir_path/$userdir/"
  exit 1
fi

totalfiles=$(find $maildir_path/$userdir/ -type f  ! -iname 'dovecot*' ! -iname 'maildirfolder' ! -iname 'subscriptions' | wc -l | xargs)
echo
echo "$text_d mails in $maildir_path/$userdir/"
echo "Found $totalfiles, processing..."
echo ". plain text" 
echo "+ gzipped "
echo "* encrypted "
echo "< encrypting"
echo "> decrypting"
echo

# operate in context
cd $maildir_path/$userdir/
for mailmessage in `find . -type f  ! -iname 'dovecot*' ! -iname 'maildirfolder' ! -iname 'subscriptions'`; do
  message=$(basename "$mailmessage")
  if [ ! -f "$mailmessage" ];then
    continue;
  fi;
  testfiletype=$(file -b --mime-type "$mailmessage")
  if [ "$testfiletype" != "application/mail-crypt" ]  ;then
      if [ "$testfiletype" != "application/gzip" ]  ;then
          echo -n "."
      else
          echo -n "+"
      fi
      if [ "$mode" == "encrypt" ];then
        _encrypt
        echo -n "<"
      fi
    else
      echo -n "*"
      if [ "$mode" == "decrypt" ];then
        _decrypt
        echo -n ">"
      fi
  fi
  count=$(($count + 1))
  processed=$(($processed + 1))
  if [ $count == 10 ];then
    echo -n "$processed/$totalfiles"
    echo -e
    count=0
  fi


done

rm -f $tempfile

echo -e "\n\nDone"
