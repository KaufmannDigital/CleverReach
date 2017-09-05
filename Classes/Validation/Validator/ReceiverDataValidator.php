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
class ReceiverDataValidator extends AbstractValidator
{

    /**
     * @Flow\Inject
     * @var  CleverReachApiService
     */
    protected $apiService;


    /**
     * @param array $value
     */
    public function isValid($value)
    {
        if (filter_var($value['email'], FILTER_VALIDATE_EMAIL) === false) {
            $this->addError('You have to supply a valid E-Mail Address', 1504541575);

            return; //We can exit here, if there is no valid email.
        }

        if (!isset($value['groupId']) || empty($value['groupId']) || !is_numeric($value['groupId'])) {
            $this->addError('No valid list ID was supplied. Please contact Webmaster', 1504541578);

            return; //We can exit here, if there is no valid groupId.
        }

        if ($this->apiService->isReceiverInGroup($value['email'], $value['groupId'])) {
            $this->addError('This email address is already in our list', 1504541582);
        }

    }
}
