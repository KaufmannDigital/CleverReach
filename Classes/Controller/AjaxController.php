<?php

namespace KaufmannDigital\CleverReach\Controller;


use KaufmannDigital\CleverReach\Exception\CleverReachException;
use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\SubscriptionService;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\I18n\Detector;
use Neos\Flow\I18n\Locale;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\RestController;
use Neos\Flow\Mvc\View\JsonView;

/**
 * Class AjaxController
 *
 * This controller is managing
 *
 * @package KaufmannDigital\CleverReach\Controller
 */
class AjaxController extends RestController
{

    protected $defaultViewObjectName = JsonView::class;

    /**
     * @var Locale
     */
    protected $locale;

    #[Flow\Inject]
    protected Translator $translator;

    #[Flow\Inject]
    protected Detector $languageDetector;

    #[Flow\Inject]
    protected SubscriptionService $subscriptionService;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;


    public function initializeAction()
    {
        $acceptLanguage = $this->request->getHttpRequest()->getHeaderLine('Accept-Language');
        $this->locale = $this->languageDetector->detectLocaleFromHttpHeader($acceptLanguage);
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverNotExistsInGroup")
     * @param array $receiverData
     * @param string $registrationFormAggregateId
     */
    public function subscribeAction(array $receiverData, string $registrationFormAggregateId)
    {
        $registrationForm = $this->resolveRegistrationFormNode($registrationFormAggregateId);
        try {
            $this->subscriptionService->subscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

            $this->view->assign('success', true);
            $this->view->assign('message',
                $registrationForm->getProperty('useDOI')
                    ? $this->translateById('success-message-doi', 'RegistrationForm')
                    : $this->translateById('success-message', 'RegistrationForm')
            );
            $this->response->setStatusCode(201);

        } catch (CleverReachException $e) {
            $this->view->assign('success', false);
            $this->view->assign('message', $e->getMessage());
            $this->response->setStatusCode(400);
        }

        $this->view->setVariablesToRender(['success', 'message']);
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverExistsInGroup")
     * @param array $receiverData
     * @param string $registrationFormAggregateId
     */
    public function unsubscribeAction(array $receiverData, string $registrationFormAggregateId)
    {
        $registrationForm = $this->resolveRegistrationFormNode($registrationFormAggregateId);
        try {
            $this->subscriptionService->unsubscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

            $this->view->assign('success', true);
            $this->view->assign('message',
                $registrationForm->getProperty('useDOI')
                    ? $this->translateById('success-message-unsubscribe-double-opt-out', 'RegistrationForm')
                    : $this->translateById('success-message-unsubscribe', 'RegistrationForm')
            );
            $this->response->setStatusCode(201);

        } catch (CleverReachException $e) {
            $this->view->assign('success', false);
            $this->view->assign('message', $e->getMessage());
            $this->response->setStatusCode(400);
        }

        $this->view->setVariablesToRender(['success', 'message']);
    }


    /**
     * Displays validation errors
     */
    public function errorAction()
    {
        $this->response->setStatusCode(400);
        $this->view->setVariablesToRender(['success', 'message']);
        $this->view->assign('success', false);

        $flattenedErrors = $this->arguments->getValidationResults()->getFlattenedErrors();

        /** @var Error[] $argumentErrors */
        foreach ($flattenedErrors as $argumentErrors) {
            $this->view->assign(
                'message',
                $this->translateById(
                    $argumentErrors[0]->getCode(), 'ValidationErrors')
            );
        }

    }


    /**
     * Returns translation by $id in current locale ($this->locale)
     *
     * @param string $id
     * @param string $sourceName
     * @return string
     */
    protected function translateById(string $id, string $sourceName)
    {
        return $this->translator->translateById(
            $id,
            [],
            null,
            $this->locale,
            $sourceName,
            'KaufmannDigital.CleverReach'
        );
    }

    private function resolveRegistrationFormNode(string $aggregateId): Node
    {
        $cr = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $contentGraph = $cr->getContentGraph(WorkspaceName::forLive());
        $aggregate = $contentGraph->findNodeAggregateById(NodeAggregateId::fromString($aggregateId));
        $dsp = array_values($aggregate->coveredDimensionSpacePoints->points)[0] ?? null;
        return $cr->getContentSubgraph(WorkspaceName::forLive(), $dsp)
            ->findNodeById($aggregate->nodeAggregateId);
    }

}
