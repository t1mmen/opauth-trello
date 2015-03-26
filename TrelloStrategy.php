<?php
/**
 * Trello strategy for Opauth
 *
 * Based on work by U-Zyn Chua (http://uzyn.com)
 * Based on work by Matt Zuba's php-trello (https://bitbucket.org/mattzuba/php-trello/overview)
 * Based on work by opauth-evernote (https://github.com/evernote/opauth-evernote)
 *
 * More information on Opauth: http://opauth.org
 *
 * @copyright    Copyright © 2015 Timm Stokke (http://timm.stokke.me)
 * @link         http://opauth.org
 * @package      Opauth.TrelloStrategy
 * @license      MIT License
 */


/**
 * @return bool
 */
function is_session_started()
{
	if ( php_sapi_name() !== 'cli' ) {
		if ( version_compare(phpversion(), '5.4.0', '>=') ) {
			return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
		} else {
			return session_id() === '' ? FALSE : TRUE;
		}
	}
	return FALSE;
}


/**
 * Trello strategy for Opauth
 * based on http://dev.evernote.com/start/core/authentication.php
 *
 * @package      Opauth.Trello
 */
class TrelloStrategy extends OpauthStrategy
{
	/**
	 * Compulsory config keys, listed as unassociative arrays
	 */
	public $expects = array('key', 'secret');

	/**
	 * Optional config keys, without predefining any default values.
	 */
	public $optionals = array('name', 'scope', 'expiration');

	/**
	 * Optional config keys with respective default values, listed as associative arrays
	 * eg. array('scope' => 'read,write,account');
	 */
	public $defaults = array(
		'oauth_callback' => '{complete_url_to_strategy}oauth_callback',
		'base_url' => 'https://trello.com/1/',
		'request_token_path' => 'OAuthGetRequestToken',
		'authorize_path'     => 'OAuthAuthorizeToken',
		'access_token_path'  => 'OAuthGetAccessToken',
	);

	public function __construct($strategy, $env) {
		parent::__construct($strategy, $env);

		$this->strategy['consumer_key'] = $this->strategy['key'];
		$this->strategy['consumer_secret'] = $this->strategy['secret'];

		$this->strategy['request_token_url'] = $this->strategy['base_url'].$this->strategy['request_token_path'];
		$this->strategy['authorize_url'] = $this->strategy['base_url'].$this->strategy['authorize_path'];
		$this->strategy['access_token_url'] = $this->strategy['base_url'].$this->strategy['access_token_path'];

		require dirname(__FILE__).'/Vendor/OAuthSimple.php';

		$this->oauth = new OAuthSimple( $this->strategy['consumer_key'], $this->strategy['consumer_secret']);
	}

	/**
	 * Auth request
	 */
	public function request() {

		if (is_session_started() === FALSE) {
			session_start();
		}

		$options = array(
			'name' => null,
			'redirect_uri' => $this->strategy['oauth_callback'],
			'expiration' => 'never',
			'scope' => array(
				'read' => true,
				'write' => false,
				'account' => false,
			),
		);

		foreach ($this->optionals as $key) {
			if ($key == 'scope') {
				foreach(explode(',',$this->strategy[$key]) as $scope) {
					$options['scope'][$scope] = true;
				}
			} elseif (!empty($this->strategy[$key])) {
				$options[$key] = $this->strategy[$key];
			}
		}

		$scope = implode(',', array_keys(array_filter($options['scope'])));

		// Get a request token from Trello
		$request = $this->oauth->sign(array(
			'path' => $this->strategy['request_token_url'],
			'parameters' => array(
				'oauth_callback' => $options['redirect_uri'],
			)
		));

		$ch = curl_init($request['signed_url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);

		// We store the token_secret for later because it's needed to get a permanent one
		parse_str($result, $returned_items);
		$request_token = $returned_items['oauth_token'];
		$_SESSION['oauth_token_secret'] = $returned_items['oauth_token_secret'];

		// Create and process a request with all of our options for Authorization
		$request = $this->oauth->sign(array(
			'path' => $this->strategy['authorize_url'],
			'parameters' => array(
				'oauth_token' => $request_token,
				'name' => $options['name'],
				'expiration' => $options['expiration'],
				'scope' => $scope,
			)
		));

		header("Location: $request[signed_url]");
		exit;
	}

	/**
	 * Receives oauth_verifier, requests for access_token and redirect to callback
	 */
	public function oauth_callback()
	{
		if (is_session_started() === FALSE) {
			session_start();
		}

		// User cancelled auth
		if (!isset($_SESSION['oauth_token_secret']) || !isset($_GET['oauth_token'])) {
			$error = array(
				'code' => 'access_denied',
				'message' => 'User denied access.',
				'raw' => $_GET
			);
			$this->errorCallback($error);
			exit;
		}


		// $_SESSION[oauth_token_secret] was stored before the Authorization redirect
		$signatures = array(
			'oauth_secret' => $_SESSION['oauth_token_secret'],
			'oauth_token' => $_GET['oauth_token'],
		);

		$request = $this->oauth->sign(array(
			'path' => $this->strategy['access_token_url'],
			'parameters' => array(
				'oauth_verifier' => $_GET['oauth_verifier'],
				'oauth_token' => $_GET['oauth_token'],
			),
			'signatures' => $signatures,
		));

		// Initiate our request to get a permanent access token
		$ch = curl_init($request['signed_url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);

		// Parse our tokens and store them
		parse_str($result, $returned_items);
		$token = $returned_items['oauth_token'];
		$secret = $returned_items['oauth_token_secret'];

		// To prevent a refresh of the page from working to re-do this step, clear out the temp
		// access token.
		unset($_SESSION['oauth_token_secret']);

		if ($token && $secret) {

			$url = $this->strategy['base_url'].'members/me';
			$data = ['key' => $this->strategy['consumer_key'], 'token' => $token];

			$results = $this->serverGet($url, $data);

			if ($results !== false && $user = json_decode($results,true)) {

				$this->auth = array(
					'uid' => $user['id'],
					'info' => array(
						'name' => $user['fullName'],
						'email' => $user['email'],
						'username' => $user['username'],
						'image' => 'http://www.gravatar.com/avatar/'.$user['gravatarHash'],
					),
					'credentials' => array(
						'token' => $token,
						'secret' => $secret
					),
					'raw' => $user
				);

				$this->callback();
			} else {
				$error = array(
					'code' => 'missing_user_details',
					'message' => 'Could not retrieve user details.',
					'raw' => $results
				);
				$this->errorCallback($error);
			}
		} else {
			$error = array(
				'code' => 'access_denied',
				'message' => 'User denied access.',
				'raw' => $_GET
			);
			$this->errorCallback($error);
		}

	}


}
