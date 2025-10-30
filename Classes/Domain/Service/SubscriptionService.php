<?php

namespace KaufmannDigital\CleverReach\Domain\Service;

use GuzzleHttp\Psr7\ServerRequest;
use KaufmannDigital\CleverReach\Exception\ApiRequestException;
use KaufmannDigital\CleverReach\Exception\NotFoundException;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Flow\Validation\ValidatorResolver;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class SubscriptionService
 *
 * @package KaufmannDigital\CleverReach\Domain\Service
 */
class SubscriptionService
{
    #[Flow\Inject]
    protected CleverReachApiService $apiService;

    #[Flow\Inject]
    protected ValidatorResolver $validatorResolver;


    /**
     * @param array $receiverData
     * @param Node $registrationForm
     * @param ServerRequest|null $httpRequest
     * @throws NotFoundException
     * @throws ApiRequestException
     */
    public function subscribe(array $receiverData, Node $registrationForm, ServerRequestInterface $httpRequest = null): void
    {
        $groupId = $registrationForm->getProperty('groupId');
        $formId = $registrationForm->getProperty('formId');
        $useDOI = $registrationForm->getProperty('useDOI');

        //Try to get existing receiver. Only if the NotFoundException is thrown, we should create a new one.
        try {
            $receiver = $this->apiService->getReceiverFromGroup($receiverData['email'], $receiverData['groupId']);
        } catch (NotFoundException $exception) {
            //Add user to list
            $this->apiService->addReceiver($receiverData, $groupId, !$useDOI);
        }

        //Send confirmation mail (if Doi activated)
        if ($useDOI === true) {
            $doiData = [
                'user_ip' => $httpRequest->getServerParams()['X_FORWARDED_FOR'] ?? $httpRequest->getServerParams()['REMOTE_ADDR'],
                'referer' => $httpRequest->getHeader('Referer'),
                'user_agent' => $httpRequest->getHeader('User-Agent')
            ];

            $this->apiService->sendDoubleOptInMail($receiverData['email'], $formId, $doiData);
        }
    }

    /**
     * @param array $receiverData
     * @param Node $registrationForm
     * @param ServerRequest|null $httpRequest
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function unsubscribe(array $receiverData, Node $registrationForm, ServerRequestInterface $httpRequest = null): void
    {
        $groupId = $registrationForm->getProperty('groupId');
        $formId = $registrationForm->getProperty('formId');
        $useDOI = $registrationForm->getProperty('useDOI');

        //Send confirmation mail (if Doi activated)
        if ($useDOI === true) {
            $doiData = [
                'user_ip' => $httpRequest->getServerParams()['X_FORWARDED_FOR'] ?? $httpRequest->getServerParams()['REMOTE_ADDR'],
                'referer' => $httpRequest->getHeader('Referer'),
                'user_agent' => $httpRequest->getHeader('User-Agent')
            ];

            $this->apiService->sendDoubleOptOutMail($receiverData['email'], $formId, $doiData);
        } else {
            $this->apiService->removeReceiver($receiverData['email'], $groupId);
        }
    }
}
