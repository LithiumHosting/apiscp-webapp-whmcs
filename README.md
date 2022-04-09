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
	\a23r::registerModule('whmcs', \lithiumhosting\whmcs\Whmcs_Module::class);
	\Module\Support\Webapps::registerApplication('whmcs', \lithiumhosting\whmcs\Handler::class);
```

Then restart ApisCP.

```bash
systemctl restart apiscp
```

Voila!

## Testing
To install WHMCS to `whmcs.domain.test` for the domain `domain.test` run the following:
```bash
cpcmd -d domain.test whmcs:install whmcs.domain.test '' '[license_key:DEV123XYZ]'
```
Or to install WHMCS to a subfolder of `whmcs` on the domain `domain.test` run the following:
```bash
cpcmd -d domain.test whmcs:install domain.test 'whmcs' '[license_key:DEV123XYZ]'
```
WHMCS installation requires a License Key, be sure to pass it as license_key.

```bash
cpcmd -d domain.test whmcs:uninstall domain.test 'whmcs' all
```
passing "all" as the 3rd parameter will remove the database and files

## Learning more
All third-party documentation is available via [docs.apiscp.com](https://docs.apiscp.com/admin/webapps/Custom/).
