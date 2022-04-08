<?php declare(strict_types=1);

	namespace lithiumhosting\whmcs;

	use Module\Support\Webapps\App\Type\Unknown\Handler as Unknown;

	class Handler extends Unknown
	{

		const NAME = 'WHMCS';
		const ADMIN_PATH = '/admin';
		const LINK = 'https://whmcs.com';
		const DEFAULT_FORTIFICATION = 'max';

		const FEAT_ALLOW_SSL = true;
		const FEAT_RECOVERY = false;

		/**
		 * Display application
		 *
		 * @return bool
		 */
		public function display(): bool
		{
			return true;
		}

		public function hasInstall(): bool
		{
			return true;
		}

		/**
		 * Wrapper to show API calls
		 *
		 * @param string $method
		 * @param null   $args
		 *
		 * @return bool
		 */
		public function __call($method, $args = null)
		{
			if (false === strpos($method, '_')) {
				return parent::__call($method, $args);
			}
			[$module, $fn] = explode('_', $method, 2);
			if ($module !== $this->getClassMapping()) {
				return parent::__call($method, $args);
			}

			if (is_debug()) {
				// optionally report all module calls on-screen as encountered
				// enable debug mode first,
				// cpcmd scope:set cp.debug true
				echo $module, ': ', $fn, "\n";
			}

			return parent::__call($method, $args);
		}

		/**
		 * API module that calls flow through
		 *
		 * @return string
		 */
		public function getClassMapping(): string
		{
			return 'whmcs';
		}

		public function handle(array $params): bool
		{
			if (!empty($params['say'])) {
				return success("Saying something nice: %s", $this->{$this->getClassMapping() . '_hello'}());
			}

			return parent::handle($params);
		}
	}
