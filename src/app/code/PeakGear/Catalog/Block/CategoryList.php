<?php
/**
 * PeakGear Catalog - CategoryList Block
 * Provides dynamic category data for header navigation, homepage, and footer
 */

declare(strict_types=1);

namespace PeakGear\Catalog\Block;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

use Magento\Catalog\Helper\Category as CategoryHelper;

class CategoryList extends Template
{
    /**
    * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Visibility
     */
    private $productVisibility;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var array|null
     */
    private $categoriesCache = null;

    /**
     * Default SVG icon used when category has no custom icon
     */
    private const DEFAULT_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>';

    /**
     * @param Context $context
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Visibility $productVisibility
     * @param StoreManagerInterface $storeManager
     * @param HttpContext $httpContext
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryRepositoryInterface $categoryRepository,
        Visibility $productVisibility,
        StoreManagerInterface $storeManager,
        HttpContext $httpContext,
        array $data = []
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->productVisibility = $productVisibility;
        $this->storeManager = $storeManager;
        $this->httpContext = $httpContext;
        parent::__construct($context, $data);
    }

    /**
     * Get top-level categories with their children (subcategories)
     * Returns array of category data for use in templates
     *
     * @return array
     */
    public function getCategories(): array
    {
        if ($this->categoriesCache !== null) {
            return $this->categoriesCache;
        }

        // Use the default catalog root category
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();
        $storeGroupId = $store->getStoreGroupId();
        $rootCategoryId = $this->storeManager->getGroup($storeGroupId)->getRootCategoryId();

        /** @var Category $rootCategory */
        $rootCategory = $this->categoryRepository->get($rootCategoryId, $store->getId());
        $collection = $rootCategory->getChildrenCategories();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'description', 'image', 'category_icon', 'name_en', 'is_active', 'include_in_menu'])
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->setOrder('position', 'ASC');

        $categories = [];
        foreach ($collection as $category) {
            $categoryData = [
                'id' => (int) $category->getId(),
                'name' => $category->getName(),
                'nameEn' => $category->getData('name_en') ?: '',
                'slug' => $category->getUrlKey(),
                'url' => $category->getUrl(),
                'description' => $this->getCategoryAttributeValue($category, 'description'),
                'image' => $category->getImageUrl() ?: '',
                'icon' => $category->getData('category_icon') ?: $this->getIconByCategoryName($category->getName()),
                'count' => $this->getProductCount($category),
                'subcategories' => $this->getSubcategories($category),
            ];
            $categories[] = $categoryData;
        }

        $this->categoriesCache = $categories;
        return $this->categoriesCache;
    }

    /**
     * Get categories for footer (limited number)
     *
     * @param int $limit
     * @return array
     */
    public function getFooterCategories(int $limit = 6): array
    {
        $categories = $this->getCategories();
        return array_slice($categories, 0, $limit);
    }

    /**
     * Get subcategories for a parent category
     *
     * @param Category $parentCategory
     * @return array
     */
    private function getSubcategories(Category $parentCategory): array
    {
        $collection = $parentCategory->getChildrenCategories();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_active'])
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('position', 'ASC');

        $subcategories = [];
        foreach ($collection as $subcat) {
            $subcategories[] = [
                'name' => $subcat->getName(),
                'slug' => $subcat->getUrlKey(),
                'url' => $subcat->getUrl(),
            ];
        }

        return $subcategories;
    }

    /**
     * Get count of visible products for a category.
     * Only counts products with visibility "Catalog", "Search", or "Catalog, Search".
     * This means configurable parents count as 1, their invisible simple children don't count.
     *
     * @param Category $category
     * @return int
     */
    private function getProductCount(Category $category): int
    {
        try {
            $productCollection = $category->getProductCollection();
            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
            $productCollection->addAttributeToFilter('visibility', ['in' => $this->productVisibility->getVisibleInSiteIds()]);
            return $productCollection->getSize();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache key info for block caching
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return [
            'PEAKGEAR_CATEGORY_LIST',
            $this->storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            'is_customer_logged_in' => $this->isCustomerLoggedIn() ? 1 : 0,
            'template' => $this->getTemplate(),
        ];
    }

    /**
     * Lightweight login flag for header rendering.
     */
    public function isCustomerLoggedIn(): bool
    {
        return (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [\Magento\Catalog\Model\Category::CACHE_TAG];
    }

    private function getCategoryAttributeValue(CategoryInterface $category, string $attributeCode): string
    {
        $attribute = $category->getCustomAttribute($attributeCode);
        $value = $attribute ? (string)$attribute->getValue() : '';
        // Strip PageBuilder/HTML wrapper tags, keep plain text only
        return trim(strip_tags($value));
    }

    /**
     * Return a keyword-matched SVG icon for a Vietnamese category name.
     * Falls back to DEFAULT_ICON if no keyword matches.
     */
    private function getIconByCategoryName(string $name): string
    {
        $n = mb_strtolower($name);

        // Boot / Shoe / Sandal
        if (str_contains($n, 'giày') || str_contains($n, 'giay') || str_contains($n, 'sandal') || str_contains($n, 'dép') || str_contains($n, 'dep')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2h4v10h5a1 1 0 0 1 1 1v1l1 3v2H3v-2l1-3v-1a1 1 0 0 1 1-1h5V2z"/><path d="M3 17h18"/><path d="M10 12H6"/></svg>';
        }

        // Socks / Compression socks
        if (str_contains($n, 'tất') || str_contains($n, 'tat') || str_contains($n, 'sock') || str_contains($n, 'vớ')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v10l-4 4a2 2 0 0 0 0 2.83l1.17 1.17a2 2 0 0 0 2.83 0L16 12V2"/><path d="M8 2h8"/><path d="M16 6H8"/></svg>';
        }

        // Backpack / Bag
        if (str_contains($n, 'ba lô') || str_contains($n, 'ba lo') || str_contains($n, 'balo') || str_contains($n, 'túi xách') || str_contains($n, 'tui xach') || str_contains($n, 'túi đeo') || str_contains($n, 'tui deo')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V8a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v12"/><path d="M4 20h16"/><path d="M10 6V4a2 2 0 0 1 2-2 2 2 0 0 1 2 2v2"/><path d="M8 20v-5h8v5"/><line x1="12" y1="11" x2="12" y2="15"/></svg>';
        }

        // Rope / Hook / Carabiner / Harness
        if (str_contains($n, 'dây') || str_contains($n, 'day') || str_contains($n, 'móc') || str_contains($n, 'moc') || str_contains($n, 'carabiner') || str_contains($n, 'đai') || str_contains($n, 'dai') || str_contains($n, 'belay') || str_contains($n, 'harness')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.5 4.7 3.5 5.8L12 22l3.5-7.2C17.5 13.7 19 11.5 19 9c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>';
        }

        // Tent / Camp
        if (str_contains($n, 'lều') || str_contains($n, 'leu') || str_contains($n, 'trại') || str_contains($n, 'trai') || str_contains($n, 'camp')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h20"/><path d="M5 20V9l7-7 7 7v11"/><path d="M9 20v-6h6v6"/></svg>';
        }

        // Sleeping Bag / Mat
        if (str_contains($n, 'túi ngủ') || str_contains($n, 'tui ngu') || str_contains($n, 'thảm') || str_contains($n, 'tham') || str_contains($n, 'đệm') || str_contains($n, 'dem')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>';
        }

        // Camp Stove / Cooking
        if (str_contains($n, 'bếp') || str_contains($n, 'bep') || str_contains($n, 'nấu') || str_contains($n, 'nau')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 0-5 5v3H5a1 1 0 0 0-1 1v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a1 1 0 0 0-1-1h-2V7a5 5 0 0 0-5-5z"/><path d="M9 7h6"/></svg>';
        }

        // Jacket / Coat / Windbreaker
        if (str_contains($n, 'áo khoác') || str_contains($n, 'ao khoac') || str_contains($n, 'áo gió') || str_contains($n, 'ao gio') || str_contains($n, 'áo lông') || str_contains($n, 'softshell') || str_contains($n, 'jacket')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2 12 6 8 2 3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23Z"/><path d="M12 10v4"/><path d="M10 12h4"/></svg>';
        }

        // General Clothing / Apparel (shirt, pants)
        if (str_contains($n, 'áo') || str_contains($n, 'quần') || str_contains($n, 'quan') || str_contains($n, 'trang phục') || str_contains($n, 'trang phuc')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2 12 6 8 2 3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23Z"/></svg>';
        }

        // Headlamp / Flashlight / Light
        if (str_contains($n, 'đèn') || str_contains($n, 'den') || str_contains($n, 'headlamp') || str_contains($n, 'flashlight')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
        }

        // Compass / GPS / Navigation
        if (str_contains($n, 'la bàn') || str_contains($n, 'la ban') || str_contains($n, 'gps') || str_contains($n, 'compass') || str_contains($n, 'navigation') || str_contains($n, 'định vị') || str_contains($n, 'dinh vi')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>';
        }

        // Water Bottle / Hydration
        if (str_contains($n, 'bình nước') || str_contains($n, 'binh nuoc') || str_contains($n, 'túi nước') || str_contains($n, 'tui nuoc') || str_contains($n, 'nước') || str_contains($n, 'nuoc') || str_contains($n, 'hydration')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v6l3 3-3 3v8"/><path d="M9 8H5v13h14V8h-4"/><path d="M9 2h6"/></svg>';
        }

        // Trekking Pole / Walking Stick
        if (str_contains($n, 'gậy') || str_contains($n, 'gay') || str_contains($n, 'pole') || str_contains($n, 'stick')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="22"/><path d="M6 6l6-4 6 4"/><path d="M10 20l2 2 2-2"/></svg>';
        }

        // Sunglasses / Eyewear
        if (str_contains($n, 'kính') || str_contains($n, 'kinh') || str_contains($n, 'mắt') || str_contains($n, 'mat') || str_contains($n, 'glasses') || str_contains($n, 'sunglasses')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="15" r="4"/><circle cx="18" cy="15" r="4"/><path d="M14 15a2 2 0 0 0-2-2 2 2 0 0 0-2 2"/><path d="M2.5 13 5 7c.7-1.3 1.4-2 3-2"/><path d="M21.5 13 19 7c-.7-1.3-1.5-2-3-2"/></svg>';
        }

        // Helmet / Hat / Head protection
        if (str_contains($n, 'nón') || str_contains($n, 'non') || str_contains($n, 'mũ') || str_contains($n, 'mu') || str_contains($n, 'helmet') || str_contains($n, 'bảo hiểm') || str_contains($n, 'bao hiem')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a9 9 0 0 1 9 9v1H3v-1a9 9 0 0 1 9-9z"/><path d="M3 12h18"/><path d="M5 16v3a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3"/></svg>';
        }

        // Gloves / Hand gear
        if (str_contains($n, 'găng') || str_contains($n, 'gang') || str_contains($n, 'glove') || str_contains($n, 'tay')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2"/><path d="M14 10V4a2 2 0 0 0-2-2 2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2v8"/><path d="M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>';
        }

        // Safety / First Aid / Protection
        if (str_contains($n, 'an toàn') || str_contains($n, 'an toan') || str_contains($n, 'sơ cứu') || str_contains($n, 'so cuu') || str_contains($n, 'bảo hộ') || str_contains($n, 'bao ho')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
        }

        // Electronics / Digital devices / Tech gear
        if (str_contains($n, 'điện tử') || str_contains($n, 'dien tu') || str_contains($n, 'electronics') || str_contains($n, 'digital') || str_contains($n, 'tech')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="1"/><path d="M7 9H4"/><path d="M7 12H4"/><path d="M7 15H4"/><path d="M17 9h3"/><path d="M17 12h3"/><path d="M17 15h3"/><path d="M9 7V4"/><path d="M12 7V4"/><path d="M15 7V4"/><path d="M9 17v3"/><path d="M12 17v3"/><path d="M15 17v3"/></svg>';
        }

        // Survival / Rescue / Emergency / Life-saving
        if (str_contains($n, 'cứu sinh') || str_contains($n, 'cuu sinh') || str_contains($n, 'cứu hộ') || str_contains($n, 'cuu ho') || str_contains($n, 'khẩn cấp') || str_contains($n, 'khan cap') || str_contains($n, 'survival') || str_contains($n, 'rescue') || str_contains($n, 'emergency')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/></svg>';
        }

        // Climbing Tools / Equipment / Gear
        if (str_contains($n, 'dụng cụ') || str_contains($n, 'dung cu') || str_contains($n, 'leo núi') || str_contains($n, 'leo nui') || str_contains($n, 'thiết bị') || str_contains($n, 'thiet bi') || str_contains($n, 'gear') || str_contains($n, 'equipment')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 3 4 8 5-5 5 15H2L8 3z"/></svg>';
        }

        // Accessories (general)
        if (str_contains($n, 'phụ kiện') || str_contains($n, 'phu kien') || str_contains($n, 'accessories')) {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        }

        return self::DEFAULT_ICON;
    }
}
