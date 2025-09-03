<?php
namespace MediaWiki\Extension\LIBDAuth;

use \MediaWiki\Auth\AuthManager;
use PluggableAuth as IPluggableAuthBase;
use MediaWiki\MediaWikiServices;
use PluggableAuthLogin;
use User;

class LIBDAuth extends IPluggableAuthBase {

    public function authenticate(&$id, &$username, &$realname, &$email, &$errorMsg) {
        // Initialize singletons
        $config = Config::newInstance();
        if ( method_exists( MediaWikiServices::class, 'getAuthManager' ) ) {
			// MediaWiki 1.35+
			$authManager = MediaWikiServices::getInstance()->getAuthManager();
		} else {
			$authManager = AuthManager::singleton();
		}
        #$authManager = AuthManager::singleton();
        $extraLoginFields = $authManager->getAuthenticationSessionData(PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY);

        $username = $extraLoginFields[ExtraLoginFields::USERNAME];
	$password = $extraLoginFields[ExtraLoginFields::PASSWORD];

        // Sanity checks
        if (!isset($username) || $username === '') {
            $errorMsg = 'Username is missing.';
            return false;
        }
	$username = str_replace('@libd.org', '', $username);
        // -- check if this is a valid local user
        //    validate local user the mediawiki way
		$user = LIBDAuth::checkLocalPassword($username, $password);
		if ($user) {
			$id = $user->getId();
			$username = $user->getName();
			return true;
		}

        // get $wgLIBDAuth_BaseUrl and $wgLIBDAuth_SecurityKey
        $baseUrl = $config->get('BaseUrl');
        #$securityKey = $config->get('SecurityKey');
        #if ($securityKey === '') {
        #    $errorMsg = 'Could not log in due to misconfigured wiki settings. Security key is missing.';
        #    return false;
        #}

        // Make cURL request to Naylor AMS ValidateAuthenticationToken endpoint
        $userArr =  LIBDAuth::postRequest($baseUrl, array('username' => $username, 'password' => $password));
        
        if (array_key_exists('error', $userArr)) {          
            $errorMsg = $userArr['error'];
            return false;
        }
        if (!array_key_exists('signed_user', $userArr)) {
            $errorMsg = 'User ID could not be retrieved.';
            return false;
        }
        
        $username=$userArr['signed_user'];
        $email = $username . '@libd.org';
        $realname = $username;
        #$realname = ((string) $userDetailsResult->FirstName) . ' ' . ((string) $userDetailsResult->LastName);
        // Check if user already exists; otherwise, leave $id invalid and make a new user
        $user = User::newFromName($username);
        if ( $user !== false && $user->getId() !== 0 ) {
          $id = $user->getId();
          $realname=$user->getRealName();
        }        
        return true;
    }

    public function saveExtraAttributes($id) {
        // do nothing
    }

    public function deauthenticate(User &$user) {
        $user = null;
    }
    
    
    protected static function wlog($msg) {
       wfErrorLog($msg, "/var/www/dev.libd.org/html/w/tmp/dbg.log");
    }

    protected static function checkLocalPassword($username, $password) {
		$user = User::newFromName( $username );
		$services = MediaWikiServices::getInstance();
		if ( $services->hasService( 'PasswordFactory' ) ) {
			$passwordFactory = $services->getPasswordFactory();
		} else {
			$passwordFactory = new \PasswordFactory();
			$passwordFactory->init( $services->getMainConfig() );
		}

		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$row = $dbr->selectRow( 'user', 'user_password', [ 'user_name' => $user->getName() ] );
		$passwordInDB = $passwordFactory->newFromCiphertext( $row->user_password );

		return $passwordInDB->verify( $password ) ? $user : null;
	}

    protected static function postRequest($url, $params) {
        // use key 'http' even if you send the request to https://...
        $options = array(
              'http' => array(
                  'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                  'method'  => 'POST',
                  'content' => http_build_query($params)
              )
        );
        $context  = stream_context_create($options);
        $validateAuthResult = file_get_contents($url, false, $context);
        if ( $validateAuthResult === FALSE) { 
            return array('error' => 'Invalid username or password.');
        }
        $responseArr = json_decode($validateAuthResult, true);
        if (! $responseArr) {
            return array('error' => 'Authentication problem.');
        }
        return $responseArr;
    }
   
}
