<?php
namespace MediaWiki\Extension\LIBDAuth;

use OutputPage;
use Skin;

class Setup {
	/**
	 * @SuppressWarnings( SuperGlobals )
	 */
	public static function init() {
        global $wgPluggableAuth_ExtraLoginFields;
        $config = Config::newInstance();
        $wgPluggableAuth_ExtraLoginFields = (array) new ExtraLoginFields($config);
	}
    
    public static function onBeforePageDisplay( $out, $skin ) {
		/**
		 * When this extension is enabled we will end up with two "Login"
		 * buttons, as `PluggableAuth` will leave the regular
		 * `LocalPasswordPrimaryAuthenticationProvider` enabled. As there is unfortunately no
		 * other way to remove it - besides implementing subclass of
		 * `LocalPasswordPrimaryAuthenticationProvider`, we just hide it
		 */
		#$config = new Config();
		#wfErrorLog("hooking onBeforePageDisplay\n", "/var/www/dev.libd.org/html/w/tmp/dbg.log");
		if ( $out->getTitle()->isSpecial( 'Userlogin' ) ) {  # && $config->get( 'AllowLocalLogin' ) ) {
			$out->addInlineStyle( '#wpLoginAttempt { display: none; }' );
		}
	}
}
