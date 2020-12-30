# ApisCP WHMCS application

This is a web application for [ApisCP](https://apiscp.com).  

## Installation

```bash
cd /usr/local/apnscp
git clone https://github.com/LithiumHosting/apiscp-webapp-whmcs config/custom/webapps/whmcs
./composer dump-autoload -o
```
Edit config/custom/boot.php, create if not exists:

```php
<?php
	\a23r::registerModule('liwhmcs', \lithiumhosting\whmcs\Whmcs_Module::class);
	\Module\Support\Webapps::registerApplication('whmcs', \lithiumhosting\whmcs\Handler::class);
```

Then restart ApisCP.

```bash
systemctl restart apiscp
```

Voila!

## Learning more
All third-party documentation is available via [docs.apiscp.com](https://docs.apiscp.com/admin/webapps/Custom/).
