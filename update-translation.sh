#!/bin/bash
xgettext --from-code UTF-8 -o locale/mail-hosting.pot `find . -iname '*.php'`
for translation in `find locale -iname '*.po'`; do msgmerge -U "$translation" locale/mail-hosting.pot; msgfmt -o ${translation:0:-2}mo "$translation"; done
