<?php
/**
 * SpecialIAOAuth2Client.php
 *
 * Based on OAuth2Client by Joost de Keijzer and Nischai Nahata, which is based on TwitterLogin by David Raison,
 * which is based on the guideline published by Dave Challis at http://blogs.ecs.soton.ac.uk/webteam/2010/04/13/254/
 * @license: LGPL (GNU Lesser General Public License) http://www.gnu.org/licenses/lgpl.html
 *
 * @file SpecialIAOAuth2Client.php
 * @ingroup OAuth2Client
 *
 * @author Kevin Alberts
 * @author Joost de Keijzer
 * @author Nischay Nahata for Schine GmbH
 *
 * Uses the OAuth2 library https://github.com/vznet/oauth_2.0_client_php
 *
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

class SpecialIAOAuth2Client extends SpecialPage {

	private $_provider;

	/**
	 * Required settings in global $wgOAuth2Client
	 *
	 * $wgOAuth2Client['client']['id']
	 * $wgOAuth2Client['client']['secret']
	 * //$wgOAuth2Client['client']['callback_url'] // extension should know
	 *
	 * $wgOAuth2Client['configuration']['authorize_endpoint']
	 * $wgOAuth2Client['configuration']['access_token_endpoint']
	 * $wgOAuth2Client['configuration']['http_bearer_token']
	 * $wgOAuth2Client['configuration']['query_parameter_token']
	 * $wgOAuth2Client['configuration']['api_endpoint']
	 */
	public function __construct() {

		parent::__construct('OAuth2Client'); // ???: wat doet dit?
		global $wgOAuth2Client, $wgScriptPath;
		global $wgServer, $wgArticlePath;

		require __DIR__ . '/vendors/oauth2-client/vendor/autoload.php';

		$this->_provider = new IAProvider([
			'clientId'                => $wgOAuth2Client['client']['id'],    // The client ID assigned to you by the provider
			'clientSecret'            => $wgOAuth2Client['client']['secret'],   // The client password assigned to you by the provider
			'redirectUri'             => $wgOAuth2Client['configuration']['redirect_uri'],
			'urlAuthorize'            => $wgOAuth2Client['configuration']['authorize_endpoint'],
			'urlAccessToken'          => $wgOAuth2Client['configuration']['access_token_endpoint'],
			'urlResourceOwnerDetails' => $wgOAuth2Client['configuration']['api_endpoint'],
            'urlResourceOwnerEndpoint' => $wgOAuth2Client['configuration']['api_user_endpoint'],
			'scopes'                  => $wgOAuth2Client['configuration']['scopes'],
            'resourceOwnerDomain'     => $wgOAuth2Client['configuration']['domain']
		]);

	}

	// default method being called by a specialpage
	public function execute( $parameter ){
		$this->setHeaders();
		switch($parameter){
			case 'redirect':
				$this->_redirect();
			break;
			case 'callback':
				$this->_handleCallback();
			break;
			default:
				$this->_default();
			break;
		}

	}

	private function _redirect() {
		global $wgRequest, $wgOut;
		$wgRequest->getSession()->persist();
		$wgRequest->getSession()->set('returnto', $wgRequest->getVal( 'returnto' ));

		// Fetch the authorization URL from the provider; this returns the
		// urlAuthorize option and generates and applies any necessary parameters
		// (e.g. state).
		$authorizationUrl = $this->_provider->getAuthorizationUrl();

		// Get the state generated for you and store it to the session.
		$wgRequest->getSession()->set('oauth2state', $this->_provider->getState());
		$wgRequest->getSession()->save();

		// Redirect the user to the authorization URL.
		$wgOut->redirect( $authorizationUrl );
	}

	private function _handleCallback(){
        global $wgOut, $wgRequest;
        $wgRequest->getSession()->persist();

        // Check if there is already an access token or refresh token in the session, if so, use that one
        // to try and login.
        if($wgRequest->getSession()->exists('ia-oauth2-accesstoken')) {
            $aToken = $wgRequest->getSession()->get('ia-oauth2-accesstoken');
            $expires = $wgRequest->getSession()->get('ia-oauth2-expires');
            $rToken = $wgRequest->getSession()->get('ia-oauth2-refreshtoken');
            $resourceOwnerId = $wgRequest->getSession()->get('ia-oauth2-resourceownerid');
            $details = array(
                'access_token' => $aToken,
                'expires' => $expires,
                'refresh_token' => $rToken,
                'resource_owner_id' => $resourceOwnerId
            );
            print("Token found in session! Details: ");
            print_r($details);
            $token = new \League\OAuth2\Client\Token\AccessToken($details);

            // Check if the access token is not expired
            if ($expires < time()) {
                // Expired. Try to get an access token using the refresh token grant.
                print("Token is expired! Getting new one using refresh token");

                try {

                    // Try to get an access token using the authorization code grant.
                    $token = $this->_provider->getAccessToken('authorization_code', [
                        'code' => $_GET['code']
                    ]);
                } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                    // Failed to get the access token or user details. Remove the token from the session and quit
                    $wgRequest->getSession()->remove('ia-oauth2-accesstoken');
                    $wgRequest->getSession()->remove('ia-oauth2-expires');
                    $wgRequest->getSession()->remove('ia-oauth2-refreshtoken');
                    $wgRequest->getSession()->remove('ia-oauth2-resourceownerid');

                    // Failed to get the access token or user details.
                    exit($e->getMessage());

                }

                print("New token get success! Saving in session");

                // Save the new access token and details in the session
                $wgRequest->getSession()->set('ia-oauth2-accesstoken', $token->getToken());
                $wgRequest->getSession()->set('ia-oauth2-expiration', $token->getExpires());
                $wgRequest->getSession()->set('ia-oauth2-refreshtoken', $token->getRefreshToken());
                $wgRequest->getSession()->set('ia-oauth2-resourceownerid', $token->getResourceOwnerId());
            }

            // Not expired (any more). Use token to login.
            print("Logging in with saved token");

        // Else, a token does not exist, request a new token.
        }else{
            print("No access token in session. Getting it.");
            try {

                // Try to get an access token using the authorization code grant.
                $token = $this->_provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                // Failed to get the access token or user details.
                exit($e->getMessage());

            }
            print("New token get success! Saving in session");
            // Save the new access token and details in the session
            $wgRequest->getSession()->set('ia-oauth2-accesstoken', $token->getToken());
            $wgRequest->getSession()->set('ia-oauth2-expiration', $token->getExpires());
            $wgRequest->getSession()->set('ia-oauth2-refreshtoken', $token->getRefreshToken());
            $wgRequest->getSession()->set('ia-oauth2-resourceownerid', $token->getResourceOwnerId());
        }

        // Save session before redirect
        $wgRequest->getSession()->save();

		$title = $this->_loginWithToken($token);

		$wgOut->redirect( $title->getFullURL() );
		return true;
	}

	private function _loginWithToken($accessToken){
        $resourceOwner = $this->_provider->getResourceOwner($accessToken);
        $user = $this->_userHandling( $resourceOwner->toArray() );
        $user->setCookies();

        global $wgRequest;
        $title = null;
        $wgRequest->getSession()->persist();
        if( $wgRequest->getSession()->exists('returnto') ) {
            $title = Title::newFromText( $wgRequest->getSession()->get('returnto') );
            $wgRequest->getSession()->remove('returnto');
            $wgRequest->getSession()->save();
        }

        if( !$title instanceof Title || 0 > $title->mArticleID ) {
            $title = Title::newMainPage();
        }

        return $title;
    }

	private function _default(){
		global $wgOAuth2Client, $wgOut, $wgUser, $wgScriptPath, $wgExtensionAssetsPath;
		$service_name = ( isset( $wgOAuth2Client['configuration']['service_name'] ) && 0 < strlen( $wgOAuth2Client['configuration']['service_name'] ) ? $wgOAuth2Client['configuration']['service_name'] : 'OAuth2' );

		$wgOut->setPagetitle( wfMessage( 'oauth2client-login-header', $service_name)->text() );
		if ( !$wgUser->isLoggedIn() ) {
			$wgOut->addWikiMsg( 'oauth2client-you-can-login-to-this-wiki-with-oauth2', $service_name );
			$wgOut->addWikiMsg( 'oauth2client-login-with-oauth2', $this->getTitle( 'redirect' )->getPrefixedURL(), $service_name );

		} else {
			$wgOut->addWikiMsg( 'oauth2client-youre-already-loggedin' );
		}
		return true;
	}

	protected function _userHandling( $response ) {
		global $wgOAuth2Client, $wgAuth, $wgRequest;

		$username = $response['user'][$wgOAuth2Client['configuration']['username']];
		$email = $response['user'][$wgOAuth2Client['configuration']['email']];

		$user = User::newFromName($username, 'creatable');
		if (!$user) {
			throw new MWException('Could not create user with username:' . $username);
			die();
		}
		$user->setRealName($username);
		$user->setEmail($email);
		$user->load();
		if ( !( $user instanceof User && $user->getId() ) ) {
			$user->addToDatabase();
			// MediaWiki recommends below code instead of addToDatabase to create user but it seems to fail.
			// $authManager = MediaWiki\Auth\AuthManager::singleton();
			// $authManager->autoCreateUser( $user, MediaWiki\Auth\AuthManager::AUTOCREATE_SOURCE_SESSION );
			$user->confirmEmail();
		}
		$user->setToken();

		// Setup the session
		$wgRequest->getSession()->persist();
		$user->setCookies();
		$this->getContext()->setUser( $user );
		$user->saveSettings();
		global $wgUser;
		$wgUser = $user;
		$sessionUser = User::newFromSession($this->getRequest());
		$sessionUser->load();

		// Fire the PluggableAuthPopulateGroups to let the AD plugin know a user has logged in.
        Hooks::run('PluggableAuthPopulateGroups', [$this->getUser()]);

		return $user;
	}

}
