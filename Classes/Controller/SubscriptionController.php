<?php

namespace KaufmannDigital\CleverReach\Controller;


use GuzzleHttp\Psr7\Response;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\SubscriptionService;
use Neos\Flow\Mvc\Controller\ActionController;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SubscriptionController
 *
 * @package KaufmannDigital\CleverReach\Controller
 */
class SubscriptionController extends ActionController
{

    #[Flow\Inject]
    protected SubscriptionService $subscriptionService;


    /**
     * Display the registration form
     */
    public function indexAction()
    {
        $node = $this->request->getInternalArgument('__node');
        $this->view->assign('node', $node);

        if ($node->getProperty('formAction') != '') {
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
        /** @var Node $registrationForm */
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
        /** @var Node $registrationForm */
        $registrationForm = $this->request->getInternalArgument('__node');

        $this->subscriptionService->unsubscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

        $this->view->assign('usedDOI', $registrationForm->getProperty('useDOI'));
    }
}
