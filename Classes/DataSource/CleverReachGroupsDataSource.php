<?php
namespace KaufmannDigital\CleverReach\DataSource;

use Neos\Flow\Annotations as Flow;
use KaufmannDigital\CleverReach\Domain\Service\CleverReachApiService;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * Class CleverReachGroupsDataSource
 * @package KaufmannDigital\CleverReach\DataSource
 */
class CleverReachGroupsDataSource extends AbstractDataSource
{

    /**
     * @var string
     */
    static protected $identifier = 'kaufmanndigital-cleverreach-groups';


    /**
     * @Flow\Inject
     * @var CleverReachApiService
     */
    protected $apiService;


    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        $groups = $this->apiService->getGroups();

        $data = [];
        if (is_array($groups) && count($groups) > 0) {
            foreach ($groups as $group) {
                $data[] = [
                    'value' => $group['id'],
                    'label' => $group['name']
                ];
            }
        }

        return $data;
    }
}
