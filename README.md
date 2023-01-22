General Information:
--------------------

This is a setup for a Tor based email hosting server. It is provided as is and before putting it into production you should make changes according to your needs. This is a work in progress and you should carefully check the commit history for changes before updating.

Installation Instructions:
--------------------------

TODO

Translating:
------------

Translations are managed in [Weblate](https://weblate.danwin1210.de/projects/DanWin/mail-hosting).
If you prefer manually submitting translations, the script `update-translations.sh` can be used to update the language template and translation files from source.
It will generate the file `locale/mail-hosting.pot` which you can then use as basis to create a new language file in `YOUR_LANG_CODE/LC_MESSAGES/mail-hosting.po` and edit it with a translation program, such as [Poedit](https://poedit.net/).
Once you are done, you can open a pull request, or [email me](mailto:daniel@danwin1210.de), to include the translation.

Live demo:
----------

If you want to see the script in action, and/or register for a free anonymous E-Mail address, you can visit my [Tor hidden service](http://danielas3rtn54uwmofdo3x2bsdifr47huasnmbgqzfrec5ubupvtpid.onion/mail/) or [my clearnet proxy](https://danwin1210.de/mail/) if you don't have Tor installed.
