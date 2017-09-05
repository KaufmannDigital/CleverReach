<?php

namespace KaufmannDigital\CleverReach\Domain\Service;

use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Exception\ApiRequestException;
use KaufmannDigital\CleverReach\Exception\AuthenticationFailedException;
use KaufmannDigital\CleverReach\Exception\CleverReachException;
use KaufmannDigital\CleverReach\Exception\NotFoundException;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Uri;

class CleverReachApiService
{

    /**
     * @Flow\InjectConfiguration()
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var CurlEngine
     */
    protected $requestEngine;

    /**
     * @var StringFrontend
     */
    protected $cache;

    /**
     * @Flow\Inject
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * @var string
     */
    protected $apiToken;


    public function initializeObject()
    {
        $this->cache = $this->cacheManager->getCache('KaufmannDigital_CleverReach_TokenCache');

        if ($this->cache->has('jwt') === true) {
            $this->apiToken = $this->cache->get('jwt');
        } else {
            $this->apiToken = $this->authenticate(
                $this->settings['credentials']['clientId'],
                $this->settings['credentials']['login'],
                $this->settings['credentials']['password']
            );

            //Cache the token for 30 days
            $this->cache->set('jwt', $this->apiToken, [], 2592000);
        }
    }


    /**
     * Returns all groups
     *
     * @return array Groups as Array
     */
    public function getGroups()
    {
        return $this->fireRequest('GET', 'groups.json');
    }

    /**
     * Returns all forms
     *
     * @return array Forms as Array
     */
    public function getForms()
    {
        return $this->fireRequest('GET', 'forms.json');
    }


    /**
     * Checks if $email is in group with $groupId
     *
     * @param string $email email to check
     * @param int $groupId group to check
     * @return bool
     */
    public function isReceiverInGroup($email, $groupId)
    {
        try {
            $result = $this->fireRequest('GET', 'groups.json/' . $groupId . '/receivers/' . $email);

            return true;
        } catch (NotFoundException $e) {
            return false;
        }

    }

    /**
     * Sends the Double-Opt-In E-Mail configured in $formId to $email
     *
     * @param string $email
     * @param integer $formId
     * @param integer $groupId
     * @param array $doiData
     */
    public function sendDoubleOptInMail($email, $groupId, $formId, array $doiData)
    {
        $arguments = [
            'email' => $email,
            'groups_id' => $groupId,
            'doidata' => $doiData
        ];


        $this->fireRequest('POST', 'forms.json/' . $formId . '/send/activate', $arguments);
    }


    /**
     * Adds a new receiver tp $groupId
     * @param array $receiverData Array of receiverData. Key "email" is required (see CleverReach API Docs)
     * @param int $groupId
     * @param bool $activate
     */
    public function addReceiver(array $receiverData, $groupId, $activate = true)
    {
        if ($activate !== true) {
            $receiverData['deactivated'] = time();
        }

        $this->fireRequest(
            'POST',
            'groups.json/' . $groupId . '/receivers/insert',
            $receiverData
        );
    }


    /**
     * Authenticate against the API to get a valid JWT
     *
     * @param string $clientId
     * @param string $login
     * @param string $password
     * @return string JWT
     * @throws AuthenticationFailedException
     */
    private function authenticate($clientId, $login, $password)
    {
        try {

            return $this->fireRequest('POST', 'login.json', [
                'client_id' => $clientId,
                'login' => $login,
                'password' => $password
            ]);

        } catch (CleverReachException $e) {
            throw new AuthenticationFailedException('CleverReach authentication failed. Credentials correct?',
                1485944436);
        }
    }


    /**
     * General method to fire API-Requests
     *
     * @param string $method HTTP method (GET/POST/PUT...)
     * @param string $resource REST-Resource
     * @param array|null $arguments arguments for the request
     * @return mixed decoded response
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    private function fireRequest($method, $resource, array $arguments = null)
    {
        //Build uri and set GET-Arguments
        $uri = new Uri($this->settings['apiEndpoint'] . '/' . $resource);
        if ($method === 'GET' && $arguments !== null) {
            $uri->setQuery(http_build_query($arguments));
        }

        //Create request and set header
        $request = Request::create($uri, $method);
        $request->setHeader('Accept', 'application/json; charset=utf-8');
        $request->setHeader('Content-Type', 'application/json; charset=utf-8');
        $request->setHeader('Authorization', 'Bearer ' . $this->apiToken);

        //Set arguments to content, if no GET-Request
        if ($method !== 'GET' && $arguments !== null) {
            $request->setContent(json_encode($arguments));
        }

        //Send request and decode response
        $response = $this->requestEngine->sendRequest($request);
        $decodedResponse = json_decode($response->getContent(), true);

        //Success? Return data
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return $decodedResponse;
        }

        $errorMessage = isset($decodedResponse['error']) ? $decodedResponse['error']['message'] : 'No error message given';


        if ($response->getStatusCode() === 404) {
            throw new NotFoundException($errorMessage, 1485941450);
        }

        throw new ApiRequestException($errorMessage, 1485941456);

    }
}
