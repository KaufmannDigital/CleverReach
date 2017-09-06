<?php

namespace KaufmannDigital\CleverReach\Controller;


use KaufmannDigital\CleverReach\Exception\CleverReachException;
use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\SubscriptionService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
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

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * @Flow\Inject
     * @var Detector
     */
    protected $languageDetector;

    /**
     * @Flow\Inject
     * @var SubscriptionService
     */
    protected $subscriptionService;


    public function initializeAction()
    {
        $acceptLanguage = $this->request->getHttpRequest()->getHeader('Accept-Language');
        $this->locale = $this->languageDetector->detectLocaleFromHttpHeader($acceptLanguage);
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @param array $receiverData
     * @param NodeInterface $registrationForm
     */
    public function subscribeAction(array $receiverData, NodeInterface $registrationForm)
    {
        try {
            $this->subscriptionService->create($receiverData, $registrationForm, $this->request->getHttpRequest());

            $this->view->assign('success', true);
            $this->view->assign('message',
                $registrationForm->getProperty('useDOI')
                    ? $this->translateById('success-message-doi', 'RegistrationForm')
                    : $this->translateById('success-message', 'RegistrationForm')
            );
            $this->response->setStatus(201);

        } catch (CleverReachException $e) {
            $this->view->assign('success', false);
            $this->view->assign('message', $e->getMessage());
            $this->response->setStatus(400);
        }

        $this->view->setVariablesToRender(['success', 'message']);
    }


    /**
     * Displays validation errors
     */
    public function errorAction()
    {
        $this->response->setStatus(400);
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

}
