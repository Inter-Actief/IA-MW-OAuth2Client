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

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$token
            ],
            GuzzleHttp\RequestOptions::JSON => [
                'method' => $endpoint,
                'id' => 'IAMediaWikiProvider'
            ]
        ];

        $request = $this->getAuthenticatedRequest(self::METHOD_POST, $url, $token, $options);
        $result = json_decode($this->getResponse($request));

        $details = [];

        if($result->error !== null){
            $details = (array) $result->result;
        }

        return array('user' => $details);
    }

    public function getResourceOwnerDetailsEndpoint()
    {
        return $this->urlResourceOwnerEndpoint;
    }

}