<?php
namespace Niks\LayeredNavigation\Model\Layer\Filter;
use Magento\CatalogSearch\Model\Layer\Filter\Category as CoreCategory;
use Magento\Framework\App\ObjectManager;

/**
 * Layer attribute filter
 */
class Category extends CoreCategory
{
    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var CategoryDataProvider
     */
    private $dataProvider;

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Framework\Escaper $escaper
     * @param CategoryManagerFactory $categoryManager
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Framework\Escaper $escaper,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory $categoryDataProviderFactory,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $escaper,
            $categoryDataProviderFactory,
            $data
        );
        $this->escaper = $escaper;
        $this->dataProvider = $categoryDataProviderFactory->create(['layer' => $this->getLayer()]);
    }

    /**
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }

    /**
     * Apply category filter to product collection
     *
     * @param   \Magento\Framework\App\RequestInterface $request
     * @return  $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        $this->_request = $request;
        if (empty($request->getParam($this->_requestVar))) {
            return $this;
        }

        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()
            ->getProductCollection();
        $this->applyToCollection($productCollection);

        $values = $this->getValueAsArray();

        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection */
        $categoryCollection = ObjectManager::getInstance()
            ->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
        $categoryCollection->addAttributeToFilter('entity_id', ['in' => $values])->addAttributeToSelect('name');
        $categoryItems = $categoryCollection->getItems();

        foreach ($values as $value) {
            if (isset($categoryItems[$value])) {
                $category = $categoryItems[$value];
                $label = $category->getName();
                $this->getLayer()
                    ->getState()
                    ->addFilter($this->_createItem($label, $value));
            }
        }
        return $this;
    }

    /**
     * Get data array for building category filter items
     *
     * @return array
     */
    protected function _getItemsData()
    {
        /** @var \Niks\LayeredNavigation\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();

        /** @var \Niks\LayeredNavigation\Model\ResourceModel\Fulltext\Collection $collection */
        $collection = $this->getLayer()->getCollectionProvider()->getCollection($this->getLayer()->getCurrentCategory());
        $collection->updateSearchCriteriaBuilder();
        $this->getLayer()->prepareProductCollection($collection);
        foreach ($productCollection->getAddedFilters() as $field => $condition) {
            if ($field === 'category_ids') {
                $collection->addFieldToFilter($field, $this->getLayer()->getCurrentCategory()->getId());
                continue;
            }
            $collection->addFieldToFilter($field, $condition);
        }

        $optionsFacetedData = $collection->getFacetedData('category');
        $category = $this->dataProvider->getCategory();
        $categories = $category->getChildrenCategories();
        $usedOptions = $this->getValueAsArray();
        if ($category->getIsActive()) {
            foreach ($categories as $category) {
                if ($category->getIsActive()
                    && isset($optionsFacetedData[$category->getId()])
                    && !in_array($category->getId(), $usedOptions)
                ) {
                    $this->itemDataBuilder->addItemData(
                        $this->escaper->escapeHtml($category->getName()),
                        $category->getId(),
                        isset($optionsFacetedData[$category->getId()]['count']) ? '+' . $optionsFacetedData[$category->getId()]['count'] : 0
                    );
                }
            }
        }
        return $this->itemDataBuilder->build();
    }

    /**
     * Apply current filter to collection
     *
     * @param \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $collection
     * @return $this
     */
    public function applyToCollection($collection)
    {
        $values = $this->getValueAsArray();
        if (empty($values)) {
            return $this;
        }
        $collection->addCategoriesFilter(['in' => $values]);
        return $this;
    }

    /**
     * Get filter values
     *
     * @return array
     */
    public function getValueAsArray()
    {
        $paramValue = $this->_getRequest()->getParam($this->_requestVar);
        if (!$paramValue) {
            return [];
        }
        $requestValue = $this->_getRequest()->getParam($this->_requestVar);
        return array_filter(explode('_', $requestValue), function ($value) {return (string)(int)$value === $value;});
    }

    /**
     * Get filter value for reset current filter state
     *
     * @param string $value
     * @return string
     */
    public function getResetOptionValue($value)
    {
        $attributeValues = $this->getValueAsArray();
        $key = array_search($value, $attributeValues);
        unset($attributeValues[$key]);
        return implode('_', $attributeValues);
    }
}
