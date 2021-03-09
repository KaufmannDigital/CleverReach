<?php

namespace KaufmannDigital\CleverReach\Controller;


use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\SubscriptionService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Mvc\Controller\ActionController;

/**
 * Class SubscriptionController
 *
 * @package KaufmannDigital\CleverReach\Controller
 */
class SubscriptionController extends ActionController
{

    /**
     * @Flow\Inject
     * @var SubscriptionService
     */
    protected $subscriptionService;


    /**
     * Display the registration form
     * @return void
     */
    public function indexAction()
    {
        $node = $this->request->getInternalArgument('__node');
        $this->view->assign('node', $node);

        if($node->getProperty('formAction') != '') {
            $this->view->assign('formAction', $node->getProperty('formAction'));
        } else {
            $this->view->assign('formAction', 'subscribe');
        }

        $httpRequest = $this->request->getHttpRequest();
        $this->view->assign('sourceUrl', (string)$httpRequest->getUri());
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverNotExistsInGroup")
     * @param array $receiverData
     */
    public function subscribeAction(array $receiverData)
    {
        /** @var NodeInterface $registrationForm */
        $registrationForm = $this->request->getInternalArgument('__node');

        $this->subscriptionService->subscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

        $this->view->assign('usedDOI', $registrationForm->getProperty('useDOI'));
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverExistsInGroup")
     * @param array $receiverData
     */
    public function unsubscribeAction(array $receiverData)
    {
        /** @var NodeInterface $registrationForm */
        $registrationForm = $this->request->getInternalArgument('__node');

        $this->subscriptionService->unsubscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

        $this->view->assign('usedDOI', $registrationForm->getProperty('useDOI'));
    }

}
