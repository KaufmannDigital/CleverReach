<?php

namespace KaufmannDigital\CleverReach\Validation\Validator;


use KaufmannDigital\CleverReach\Domain\Service\CleverReachApiService;
use KaufmannDigital\CleverReach\Exception\NotFoundException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Validation\Validator\AbstractValidator;

/**
 * Class ReceiverDataValidator
 * @package KaufmannDigital\CleverReach\Validation\Validator
 */
class ReceiverNotExistsInGroupValidator extends AbstractValidator
{
    #[Flow\Inject]
    protected CleverReachApiService $apiService;

    /**
     * @param array $value
     */
    public function isValid($value)
    {
        try {
            $receiver = $this->apiService->getReceiverFromGroup($value['email'], (int)$value['groupId']);
            if (isset($receiver['active']) && $receiver['active'] === true) {
                $this->addError('This email address is already in our list', 1504541582);
            }
        } catch (NotFoundException $exception) {
            return;
        }
    }
}
