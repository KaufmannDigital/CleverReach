<?php

namespace KaufmannDigital\CleverReach\Controller;


use GuzzleHttp\Psr7\Response;
use KaufmannDigital\CleverReach\Exception\CleverReachException;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\SubscriptionService;
use Neos\Flow\I18n\Detector;
use Neos\Flow\I18n\Exception\IndexOutOfBoundsException;
use Neos\Flow\I18n\Exception\InvalidFormatPlaceholderException;
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

    public function initializeAction()
    {
        $acceptLanguage = $this->request->getHttpRequest()->getHeader('Accept-Language');
        $this->locale = $this->languageDetector->detectLocaleFromHttpHeader($acceptLanguage[0]);
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverNotExistsInGroup")
     * @param array $receiverData
     * @param Node $registrationForm
     */
    public function subscribeAction(array $receiverData, Node $registrationForm)
    {
        try {
            $this->subscriptionService->subscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

            $this->view->assign('success', true);
            $this->view->assign('message',
                $registrationForm->getProperty('useDOI')
                    ? $this->translateById('success-message-doi', 'RegistrationForm')
                    : $this->translateById('success-message', 'RegistrationForm')
            );
            $statusCode = 201;

        } catch (CleverReachException $e) {
            $this->view->assign('success', false);
            $this->view->assign('message', $e->getMessage());
            $statusCode = 400;
        }

        $this->view->setVariablesToRender(['success', 'message']);

        $response = $this->view->render();
        if (!$response instanceof Response) {
            $response = new Response(status: $statusCode, body: $response);
        } else {
            $response->withStatus($statusCode);
        }
        return $response;
    }

    /**
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverData")
     * @Flow\Validate(argumentName="receiverData", type="KaufmannDigital.CleverReach:ReceiverExistsInGroup")
     * @param array $receiverData
     * @param Node $registrationForm
     */
    public function unsubscribeAction(array $receiverData, Node $registrationForm)
    {
        try {
            $this->subscriptionService->unsubscribe($receiverData, $registrationForm, $this->request->getHttpRequest());

            $this->view->assign('success', true);
            $this->view->assign('message',
                $registrationForm->getProperty('useDOI')
                    ? $this->translateById('success-message-unsubscribe-double-opt-out', 'RegistrationForm')
                    : $this->translateById('success-message-unsubscribe', 'RegistrationForm')
            );
            $statusCode = 201;

        } catch (CleverReachException $e) {
            $this->view->assign('success', false);
            $this->view->assign('message', $e->getMessage());
            $statusCode = 400;
        }
        $this->view->setVariablesToRender(['success', 'message']);
        $response = $this->view->render();
        if (!$response instanceof Response) {
            $response = new Response(status: $statusCode, body: $response);
        } else {
            $response->withStatus($statusCode);
        }
        return $response;
    }


    /**
     * Displays validation errors
     */
    public function errorAction()
    {
        $flattenedErrors = $this->arguments->getValidationResults()->getFlattenedErrors();

        /** @var Error[] $argumentErrors */
        foreach ($flattenedErrors as $argumentErrors) {
            $this->view->assign(
                'message',
                $this->translateById(
                    $argumentErrors[0]->getCode(), 'ValidationErrors')
            );
        }
        $this->view->setVariablesToRender(['success', 'message']);
        $this->view->assign('success', false);

        $response = $this->view->render();
        if (!$response instanceof Response) {
            $response = new Response(status: 400, body: $response);
        } else {
            $response->withStatus(400);
        }
        return $response;
    }


    /**
     * Returns translation by $id in current locale ($this->locale)
     *
     * @param string $id
     * @param string $sourceName
     * @return string
     * @throws IndexOutOfBoundsException
     * @throws InvalidFormatPlaceholderException
     */
    protected function translateById(string $id, string $sourceName): string
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
