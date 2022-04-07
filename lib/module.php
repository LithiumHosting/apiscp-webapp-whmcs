<?php declare(strict_types=1);

	namespace lithiumhosting\whmcs;

	use Module\Support\Webapps;

	class Whmcs_Module extends Webapps
	{

		const APP_NAME = 'WHMCS';
		const VERSION_CHECK_URL = 'https://api1.whmcs.com/download/latest';

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
		 * @param string $mixed
		 * @param string $path
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

			return file_exists($this->domain_fs_path($mixed) . '/vendor/whmcs/whmcs-foundation/lib/License.php');
		}

		/**
		 * Install application
		 *
		 * @param string $hostname
		 * @param string $path
		 * @param array  $opts
		 *
		 * @return bool
		 */
		public function install(string $hostname, string $path = '', array $opts = []): bool
		{
			if (!$this->mysql_enabled()) {
				return error('%(what)s must be enabled to install %(app)s',
					['what' => 'MySQL', 'app' => static::APP_NAME]);
			}

			if (!$this->parseInstallOptions($opts, $hostname, $path)) {
				return false;
			}
			$version = $opts['version'];
			$release = $this->_getReleaseData()[$version];
			if (!($url = array_get($release, 'url'))) {
				return error("Failed to fetch install URL");
			}

			if (!$this->download($url, $this->getDocumentRoot($hostname, $path), true)) {
				return false;
			}

			// create database, modify config, etc

			// Apply Fortification, useful with PHP applications which run under a different UID
			// see Fortification.md
			$this->fortify($hostname, $path, 'max');

			// Send notification email
			$this->notifyInstalled($hostname, $path, $opts);

			return true;
		}

		/**
		 * An extra API method available via "demo2:hello"
		 *
		 * @return string
		 */
		public function hello(): string
		{
			return "beautiful!";
		}

		/**
		 * Remove application
		 *
		 * @param string $hostname
		 * @param string $path
		 * @param string $delete
		 *
		 * @return bool
		 */
		public function uninstall(string $hostname, string $path = '', $delete = 'all'): bool
		{
			// parent does a good job of removing all traces, you can do any last minute touch-ups, such as
			// removing Redis services or changing DNS after uninstallation
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
			if (!($versions = json_decode($contents, true))) {
				return array();
			}

			$cache->set('whmcs.versions', $versions = [$versions['version'] => $versions]);

			return $versions;
		}

		public function get_version(string $hostname, string $path = ''): ?string
		{
			$path = $this->getDocumentRoot($hostname, $path) . '/foo';

			// install file missing?
			if (!$this->file_exists($this->domain_fs_path($path))) {
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
	}
