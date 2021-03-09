<?php

namespace KaufmannDigital\CleverReach\Validation\Validator;


use KaufmannDigital\CleverReach\Domain\Model\ReceiverData;
use KaufmannDigital\CleverReach\Domain\Service\CleverReachApiService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Validation\Validator\AbstractValidator;

/**
 * Class ReceiverDataValidator
 * @package KaufmannDigital\CleverReach\Validation\Validator
 */
class ReceiverNotExistsInGroupValidator extends AbstractValidator
{
    /**
     * @Flow\Inject
     * @var CleverReachApiService
     */
    protected $apiService;

    /**
     * @param array $value
     */
    public function isValid($value)
    {
        if ($this->apiService->isReceiverInGroup($value['email'], $value['groupId'])) {
            $this->addError('This email address is already in our list', 1504541582);
        }
    }
}
