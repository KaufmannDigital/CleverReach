<?php
namespace KaufmannDigital\CleverReach\DataSource;

use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\CleverReachApiService;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * Class CleverReachFormsDataSource
 * @package KaufmannDigital\CleverReach\DataSource
 */
class CleverReachFormsDataSource extends AbstractDataSource
{

    /**
     * @var string
     */
    static protected $identifier = 'kaufmanndigital-cleverreach-forms';


    /**
     * @Flow\Inject
     * @var CleverReachApiService
     */
    protected $apiService;


    public function getData(NodeInterface $node = null, array $arguments)
    {
        $forms = $this->apiService->getForms();
        $data = [];

        if (is_array($forms) && count($forms) > 0) {
            foreach ($forms as $form) {
                $data[] = [
                    'value' => $form['id'],
                    'label' => $form['name']
                ];
            }
        }

        return $data;
    }
}
