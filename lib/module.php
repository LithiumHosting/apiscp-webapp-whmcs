<?php

declare(strict_types=1);

namespace lithiumhosting\whmcs;

use Module\Support\Webapps;
use Module\Support\Webapps\DatabaseGenerator;
use Module\Support\Webapps\MetaManager;

class Whmcs_Module extends Webapps
{

	const APP_NAME = 'WHMCS';
	const VERSION_CHECK_URL = 'https://download.whmcs.com/assets/scripts/get-downloads.php';
	const DOWNLOAD_URL = 'https://s3.amazonaws.com/releases.whmcs.com/v2/pkgs/whmcs-{VERSION}-release.1.zip';

	public $whmcs_username = 'admin';
	public $whmcs_password;

	protected $aclList = [
		'min' => [
			'attachments',
			'downloads',
			'templates_c',
			'.htaccess',
		],
		'max' => [
			'attachments',
			'downloads',
			'templates_c',
		],
	];

	/**
	 * Installed app is type "whmcs"
	 *
	 * @param  string  $mixed
	 * @param  string  $path
	 *
	 * @return bool
	 */
	public function valid(string $mixed, string $path = ''): bool
	{
		// $mixed is passed as [hostname, path] combination
		// convert to filesystem path
		if ($mixed[0] !== '/') {
			$mixed = $this->getDocumentRoot($mixed, $path);
		}

		return file_exists($this->domain_fs_path($mixed).'/vendor/whmcs/whmcs-foundation/lib/License.php');
	}

	/**
	 * Install application
	 *
	 * @param  string  $hostname
	 * @param  string  $path
	 * @param  array  $opts
	 *
	 * @return bool
	 */
	public function install(string $hostname, string $path = '', array $opts = []): bool
	{
		if (! $this->mysql_enabled()) {
			return error(
				'%(what)s must be enabled to install %(app)s',
				['what' => 'MySQL', 'app' => static::APP_NAME]
			);
		}

		$docroot = $this->getDocumentRoot($hostname, $path);

		if (! $docroot) {
			return error("failed to detect document root for `%s'", $hostname);
		}

		if (! $this->parseInstallOptions($opts, $hostname, $path)) {
			return false;
		}

		if (! $this->crontab_permitted()) {
			return error('Task scheduling not enabled for account - admin must enable crontab,permit');
		}

		if (! $this->crontab_enabled() && ! $this->crontab_toggle_status(1)) {
			return error('Failed to enable task scheduling');
		}

		if (empty($opts['license_key'])) {
			return error('A WHMCS License Key is required.');
		}

		if (empty($opts['whmcs_username'])) {
			return error('A WHMCS Admin Username is required.');
		}

		$this->whmcs_username = $opts['whmcs_username'];
		$this->whmcs_password = $opts['whmcs_password'] = \Opcenter\Auth\Password::generate(10);

		$version = $opts['version'];
		$license = $opts['license_key'];
		$release = $this->_getReleaseData()[$version];

		if (! ($url = array_get($release, 'url'))) {
			return error("Failed to fetch install URL");
		}

		if (! $this->download($url, $docroot, true)) {
			return false;
		}

		$db = DatabaseGenerator::mysql($this->getAuthContext(), $hostname);
		$db->connectionLimit = max($db->connectionLimit, 15);

		if (! $db->create()) {
			return false;
		}

		$install = $this->run_install($docroot, $opts, $db);
		if ($install['success']) {
			$this->file_delete("${docroot}/install", true);
		}

		$owner = $this->getDocrootUser($docroot);
		if (! $this->crontab_match_job(preg_quote(' '.$docroot, '!'), $owner)) {
			$this->crontab_add_job('*/5', '*', '*', '*', '*', 'php -q '.$docroot.'/crons/cron.php', $owner);
		}

		$this->initializeMeta($docroot, $opts);

		// Apply Fortification, useful with PHP applications which run under a different UID
		// see Fortification.md
		$this->fortify($hostname, $path);

		// Send notification email
		$this->notifyInstalled($hostname, $path, $opts);

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function db_config(string $hostname, string $path = '')
	{
		$approot = $this->getAppRoot($hostname, $path);
		if (! $this->file_exists($approot.'/configuration.php')) {
			return false;
		}


		$code = 'ob_start(); include("./configuration.php"); file_put_contents("php://fd/3", serialize(["db" => $db_name, "user" => $db_username, "host" => $db_host, "prefix" => "", "password" =>  $db_password])); ';
		$cmd = 'cd %(path)s && php -d mysqli.default_socket=%(socket)s -r %(code)s 3>&1-';
		$ret = $this->pman_run($cmd, [
			'path' => $approot,
			'code' => $code,
			'socket' => ini_get('mysqli.default_socket')
		]);

		if (! $ret['success']) {
			return error("failed to obtain %(app)s configuration for `%(approot)s': %(err)s", [
				'app' => static::APP_NAME,
				'approot' => $approot,
				'err' => $ret['stderr']
			]);
		}

		return \Util_PHP::unserialize(trim($ret['stdout']));
	}


	/**
	 * Remove application
	 *
	 * @param  string  $hostname
	 * @param  string  $path
	 * @param  string  $delete
	 *
	 * @return bool
	 */
	public function uninstall(string $hostname, string $path = '', $delete = 'all'): bool
	{
		// parent does a good job of removing all traces, you can do any last minute touch-ups, such as
		// removing Redis services or changing DNS after uninstallation
		$approot = $this->getAppRoot($hostname, $path);
		$this->removeJobs($approot);

		return parent::uninstall($hostname, $path, $delete);
	}

	/**
	 * Get available versions
	 *
	 * Used to determine whether an app is eligible for updates
	 *
	 * @return array|string[]
	 */
	public function get_versions(): array
	{
		return array_keys($this->_getReleaseData());
	}

	/**
	 * Retrieve WHMCS release data from API
	 *
	 * @return array
	 */
	private function _getReleaseData(): array
	{
		$cache = \Cache_Super_Global::spawn();
		if (false !== ($versions = $cache->get('whmcs.versions'))) {
			return $versions;
		}

		$contents = file_get_contents(self::VERSION_CHECK_URL);
		if (! ($releases = json_decode($contents, true))) {
			return [];
		}

		$versions[$releases['latestVersion']['version']] = [
			'version' => $releases['latestVersion']['version'],
			'url' => str_replace('{VERSION}', $releases['latestVersion']['version'],
				'https://s3.amazonaws.com/releases.whmcs.com/v2/pkgs/whmcs-{VERSION}-release.1.zip'),
			'release_notes' => $releases['latestVersion']['releaseNotesUrl'],
		];

		foreach ($releases['ltsReleases'] as $release) {
			$versions[$release['version']] = [
				'version' => $release['version'],
				'url' => str_replace('{VERSION}', $release['version'],
					'https://s3.amazonaws.com/releases.whmcs.com/v2/pkgs/whmcs-{VERSION}-release.1.zip'),
				'release_notes' => $release['releaseNotesUrl'],
			];
		}

		ksort($versions);

		$cache->set('whmcs.versions', $versions);

		return $versions;
	}

	// Not implemented.

	public function get_version(string $hostname, string $path = ''): ?string
	{
		$path = $this->getDocumentRoot($hostname, $path).'/foo';

		// install file missing?
		if (! $this->file_exists($this->domain_fs_path($path))) {
			return null;
		}

		return strtok($this->domain_fs_path($path), ' ');
	}

	/**
	 * Get Web App handler name
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	public function getModule(): string
	{
		// fallback Web App handler name when "type" isn't specified in install
		return parent::getModule();
	}

	private function run_install($docroot, $opts, $db)
	{
		$json = json_encode([
			'admin' => [
				'username' => $this->whmcs_username,
				'password' => $this->whmcs_password,
			],
			'configuration' => [
				'license' => $opts['license_key'],
				'db_host' => $db->hostname,
				'db_username' => $db->username,
				'db_password' => $db->password,
				'db_name' => $db->database,
				'cc_encryption_hash' => \Opcenter\Auth\Password::generate(64),
				'mysql_charset' => 'utf8',
			],
		]);

		return $this->pman_run('echo %(conf)s | /usr/bin/php -f %(path)s/install/bin/installer.php -- -i -n -c', [
			'path' => $docroot,
			'conf' => $json
		]);
	}
}
