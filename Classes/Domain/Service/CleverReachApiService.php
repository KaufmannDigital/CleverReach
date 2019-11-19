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
use Neos\Http\Factories\ServerRequestFactory;
use GuzzleHttp\Psr7\Uri;
use Neos\Http\Factories\StreamFactory;

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
     * @Flow\Inject
     * @var ServerRequestFactory
     */
    protected $serverRequestFactory;


    /**
     * @Flow\Inject
     * @var StreamFactory
     */
    protected $streamFactory;

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
                $this->settings['credentials'],
                $this->settings['credentials']['login'],
                $this->settings['credentials']['password']
            );

            //Cache the token for 30 days
            $this->cache->set('jwt', $this->apiToken, [], 2592000);
        }
    }


    /**
     * @return array
     */
    public function getGlobalAttributes(): array
    {
        try {
            $response = $this->fireRequest('GET', 'attributes');
            return $response !== false ? $response : [];
        } catch (\Exception $e) {
            return [];
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
     * @param int $groupId
     * @return mixed
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function getGroup(int $groupId)
    {
        return $this->fireRequest('GET', 'groups.json/' . $groupId);
    }

    /**
     * @param int $groupId
     * @return mixed
     */
    public function getGroupAttributes(int $groupId)
    {
        try {
            $response = $this->fireRequest('GET', 'groups.json/' . $groupId . '/attributes');
            return $response !== false ? $response : [];
        } catch (\Exception $e) {
            return [];
        }
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
     * Adds a new receiver to $groupId
     *
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
     * Updates or adds a new receiver to $groupId
     *
     * @param array $receiverData Array of receiverData. Key "email" is required (see CleverReach API Docs)
     * @param int $groupId
     * @param bool $activate
     */
    public function addOrUpdateReceiver(array $receiverData, int $groupId, bool $activate = true): void
    {
        if ($activate !== true) {
            $receiverData['deactivated'] = time();
        }

        $this->fireRequest(
            'POST',
            'groups.json/' . $groupId . '/receivers/upsert',
            $receiverData
        );
    }

    /**
     * Remove a receiver tp $groupId
     * @param $receiverIdOrEmail
     * @param int $groupId
     */
    public function removeReceiver($receiverIdOrEmail, $groupId)
    {
        $this->fireRequest(
            'DELETE',
            'groups.json/' . $groupId . '/receivers/' . $receiverIdOrEmail
        );
    }


    /**
     * Authenticate against the API to get a valid JWT
     *
     * @param array $client
     * @param string $login
     * @param string $password
     * @return string JWT
     * @throws AuthenticationFailedException
     */
    private function authenticate($client, $login, $password)
    {
        try {

            if (array_key_exists('clientSecret', $client) && !empty($client['clientSecret'])) {
                $uri = new Uri($this->settings['oauthTokenEndpoint']);

                return $this->callUri('POST', $uri, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $client['clientId'],
                    'client_secret' => $client['clientSecret'],
                ])['access_token'];
            } else {
                return $this->fireRequest('POST', 'login.json', [
                    'client_id' => $client['clientId'],
                    'login' => $login,
                    'password' => $password
                ]);
            }

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
     */
    private function fireRequest($method, $resource, array $arguments = null)
    {
        //Build uri and set GET-Arguments
        $uri = new Uri($this->settings['apiEndpoint'] . '/' . $resource);

        return $this->callUri($method, $uri, $arguments);
    }


    /**
     * General method to fire API-Requests
     *
     * @param string $method HTTP method (GET/POST/PUT...)
     * @param Uri $uri URI resource
     * @param array|null $arguments arguments for the request
     * @return mixed decoded response
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    private function callUri($method, Uri $uri, array $arguments = null)
    {
        //Build uri and set GET-Arguments
        if ($method === 'GET' && $arguments !== null) {
            $uri->setQuery(http_build_query($arguments));
        }

        //Create request and set header
        $request = $this->serverRequestFactory->createServerRequest($method, $uri)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Authorization', 'Bearer ' . $this->apiToken);

        //Set body, if needed
        $request = $method !== 'GET' && $arguments !== null
            ? $request->withBody($this->streamFactory->createStream(json_encode($arguments)))
            : $request;

        //Fire request and get response-body
        $response = $this->requestEngine->sendRequest($request);
        $decodedResponse = json_decode($response->getBody()->getContents(), true);

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
