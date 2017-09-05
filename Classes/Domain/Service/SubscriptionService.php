<?php

namespace KaufmannDigital\CleverReach\Domain\Service;

use KaufmannDigital\CleverReach\Domain\Model\ReceiverData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Request;
use Neos\Flow\Validation\ValidatorResolver;

/**
 * Class SubscriptionService
 *
 * @package KaufmannDigital\CleverReach\Domain\Service
 */
class SubscriptionService
{

    /**
     * @Flow\Inject
     * @var CleverReachApiService
     */
    protected $apiService;


    /**
     * @Flow\Inject
     * @var ValidatorResolver
     */
    protected $validatorResolver;


    /**
     * @param array $receiverData
     * @param NodeInterface $registrationForm
     * @param Request|null $httpRequest
     */
    public function create(array $receiverData, NodeInterface $registrationForm, Request $httpRequest = null)
    {
        $groupId = $registrationForm->getProperty('groupId');
        $formId = $registrationForm->getProperty('formId');
        $useDOI = $registrationForm->getProperty('useDOI');

        //Add user to list
        $this->apiService->addReceiver($receiverData, $groupId, !$useDOI);


        //Send confirmation mail (if Doi activated)
        if ($useDOI === true) {
            $doiData = [
                'user_ip' => $httpRequest->getClientIpAddress(),
                'referer' => $httpRequest->getHeader('Referer'),
                'user_agent' => $httpRequest->getHeader('User-Agent')
            ];

            $this->apiService->sendDoubleOptInMail($receiverData['email'], $groupId, $formId, $doiData);

        }

    }

}
