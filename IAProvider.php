<?php

use League\OAuth2\Client\Token\AccessToken;

/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 19/02/18
 * Time: 16:24
 */

class IAProvider extends \League\OAuth2\Client\Provider\GenericProvider
{
    /**
     * @var string
     */
    public $urlResourceOwnerEndpoint;

    /**
     * @var string
     */
    public $resourceOwnerDomain;

    /**
     * Returns all options that are required.
     *
     * @return array
     */
    protected function getRequiredOptions()
    {
        return [
            'urlAuthorize',
            'urlAccessToken',
            'urlResourceOwnerDetails',
            'urlResourceOwnerEndpoint',
            'resourceOwnerDomain'
        ];
    }

    /**
     * Requests resource owner details.
     *
     * @param  AccessToken $token
     * @return mixed
     */
    protected function fetchResourceOwnerDetails(AccessToken $token)
    {
        $url = $this->getResourceOwnerDetailsUrl($token);
        $endpoint = $this->getResourceOwnerDetailsEndpoint();

        $context = stream_context_create(array(
            'http' => array(
                'header' => implode("\r\n", array(
                    "Content-Type: application/json",
                    "Authorization: Bearer ".$token,
                    "User-Agent: IA Mediawiki/1.0"
                )),
                'method' => "POST",
                'content' => json_encode([
                    'method' => $endpoint,
                    'id' => "IAMediaWikiProvider",
                ])
            )
        ));
        $result = file_get_contents($url, false, $context);
        $result = json_decode($result);

        $details = [];

        if($result->error === null){
            $details = (array) $result->result;

            // Overwrite the e-mail address with the address stored in the AD
            $details['email'] = $details['username'].'@'.getResourceOwnerDomain();
        }

        return array('user' => $details);
    }

    public function getResourceOwnerDetailsEndpoint()
    {
        return $this->urlResourceOwnerEndpoint;
    }

    public function getResourceOwnerDomain()
    {
        return $this->resourceOwnerDomain;
    }

}