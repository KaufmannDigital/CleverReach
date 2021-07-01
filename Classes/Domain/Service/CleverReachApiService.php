<?php

namespace KaufmannDigital\CleverReach\Domain\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
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
                $this->settings['credentials']
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
     * @throws ApiRequestException
     * @throws NotFoundException
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
     * @throws ApiRequestException
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
     * @param string $email
     * @param int $groupId
     * @return array
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function getReceiverFromGroup(string $email, int $groupId)
    {
        return $this->fireRequest('GET', 'groups.json/' . $groupId . '/receivers/' . $email);
    }

    /**
     * Sends the Double-Opt-In E-Mail configured in $formId to $email
     *
     * @param string $email
     * @param integer $formId
     * @param array $doiData
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function sendDoubleOptInMail($email, $formId, array $doiData)
    {
        $arguments = [
            'email' => $email,
            'doidata' => $doiData
        ];


        $this->fireRequest('POST', 'forms.json/' . $formId . '/send/activate', $arguments);
    }

    /**
     * Sends the Double-Opt-Out E-Mail configured in $formId to $email
     *
     * @param string $email
     * @param integer $formId
     * @param array $doiData
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function sendDoubleOptOutMail($email, $formId, array $doiData)
    {
        $arguments = [
            'email' => $email,
            'doidata' => $doiData
        ];

        $this->fireRequest('POST', 'forms.json/' . $formId . '/send/deactivate', $arguments);
    }

    /**
     * Adds a new receiver to $groupId
     *
     * @param array $receiverData Array of receiverData. Key "email" is required (see CleverReach API Docs)
     * @param int $groupId
     * @param bool $activate
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function addReceiver(array $receiverData, $groupId, $activate = true)
    {
        if ($activate !== true) {
            $receiverData['deactivated'] = time();
        }

        $this->fireRequest(
            'POST',
            'groups.json/' . $groupId . '/receivers/insert',
            [$receiverData]
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
     * @param string $receiverIdOrEmail
     * @param int $groupId
     */
    public function removeReceiver(string $receiverIdOrEmail, int $groupId): void
    {
        $this->fireRequest(
            'DELETE',
            'groups.json/' . $groupId . '/receivers/' . $receiverIdOrEmail
        );
    }

    /**
     * Deactivate a receiver in a group
     * @param string $receiverIdOrEmail
     * @param int $groupId
     */
    public function deactivateReceiver(string $receiverIdOrEmail, int $groupId): void
    {
        $this->fireRequest(
            'PUT',
            'groups.json/' . $groupId . '/receivers/' . $receiverIdOrEmail . '/deactivate'
        );
    }

    /**
     * Authenticate against the API to get a valid JWT
     *
     * @param array $client
     * @return string JWT
     * @throws AuthenticationFailedException|GuzzleException
     */
    private function authenticate($client)
    {
        try {
            if (array_key_exists('clientSecret', $client) && !empty($client['clientSecret'])) {
                $uri = new Uri($this->settings['oauthTokenEndpoint']);

                return $this->callUri('POST', $uri, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $client['clientId'],
                    'client_secret' => $client['clientSecret'],
                ])['access_token'];
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
     * @throws ApiRequestException
     * @throws NotFoundException
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
     * @throws NotFoundException|GuzzleException
     */
    private function callUri($method, Uri $uri, array $arguments = null)
    {
        //Build uri and set GET-Arguments
        if ($method === 'GET' && $arguments !== null) {
            $uri->setQuery(http_build_query($arguments));
        }

        $request = new Request(
            $method,
            $uri,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->apiToken
            ],
            $method !== 'GET' ? \GuzzleHttp\json_encode($arguments) : null
        );

        //Fire request and get response-body
        $client = new Client();
        $response = $client->send($request, ['http_errors' => false]);
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
