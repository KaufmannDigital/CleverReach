<?php
namespace KaufmannDigital\CleverReach\DataSource;

use KaufmannDigital\CleverReach\Exception\ApiRequestException;
use KaufmannDigital\CleverReach\Exception\NotFoundException;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
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

    #[Flow\Inject]
    protected CleverReachApiService $apiService;


    /**
     * @throws ApiRequestException
     * @throws NotFoundException
     */
    public function getData(Node $node = null, array $arguments = []): array
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
