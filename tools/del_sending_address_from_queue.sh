#!/bin/sh
(test "$1" != "") || (echo "Need email address to delete from queue" && exit 1)
for i in `mailq | grep "$1" | awk '{print $1;}' | sed -e 's/\*//'`; do postsuper -d $i; done
