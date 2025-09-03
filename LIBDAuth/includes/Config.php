<?php
namespace MediaWiki\Extension\LIBDAuth;

use GlobalVarConfig;

class Config extends GlobalVarConfig {
	public function __construct() {
		parent::__construct('wgLIBDAuth_');
	}

	/**
	 * Factory method for MediaWikiServices
	 * @return Config
	 */
	public static function newInstance() {
		return new self();
	}
}
