<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Framework;

use Klevu\Indexing\Framework\Url;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers Url::class
 * @method Url instantiateTestObject(?array $arguments = null)
 */
class UrlTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setup();

        $this->implementationFqcn = Url::class;
        $this->interfaceFqcn = UrlInterface::class;

        $this->objectManager = ObjectManager::getInstance();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testDoesNotPreferenceUrlInterface(): void
    {
        $preferencedUrlInterface = $this->objectManager->get(UrlInterface::class);

        $this->assertNotInstanceOf(
            expected: Url::class,
            actual: $preferencedUrlInterface,
        );
    }

    /**
     * @testWith [false, false]
     *           [false, true]
     *           [true, false]
     *           [true, true]
     *
     * @param bool $secureInFrontend
     *
     * @return void
     * @throws \Exception
     */
    public function testGetBaseUrl(
        bool $secureInFrontend,
        bool $useSeoRewrites,
    ): void {
        $assertMessageForArgs = sprintf(
            ' secureInFrontend: %s, useSeoRewrites: %s',
            $secureInFrontend ? 'true' : 'false',
            $useSeoRewrites ? 'true' : 'false',
        );

        ConfigFixture::setGlobal(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/base_url',
            value: 'https://base.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/use_in_frontend',
            value: (int)$secureInFrontend,
        );
        ConfigFixture::setGlobal(
            path: 'web/seo/use_rewrites',
            value: (int)$useSeoRewrites,
        );

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_ind_url_website1',
                'code' => 'klevu_ind_url_website1',
                'name' => 'Indexing: Url Website 1',
            ],
        );
        $websiteFixture1 = $this->websiteFixturesPool->get('klevu_ind_url_website1');

        $this->createStore(
            storeData: [
                'key' => 'klevu_ind_url_store1',
                'code' => 'klevu_ind_url_store1',
                'name' => 'Indexing: Url Store 1',
                'website_id' => $websiteFixture1->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_ind_url_store1');
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_url',
            value: 'https://base.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: (int)$secureInFrontend,
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: (int)$useSeoRewrites,
            storeCode: 'klevu_ind_url_store1',
        );

        $url = $this->instantiateTestObject([]);

        $unsecureBaseUrl = $url->getBaseUrl(
            params: [
                '_secure' => 0,
                '_type' => UrlInterface::URL_TYPE_WEB,
            ],
        );
        $expected = 'http://base.domain-global.test/';
        // As a "web" link, we don't have index.php.
        // For ref: \Magento\Store\Model\Store::getBaseUrl : note that case UrlInterface::URL_TYPE_WEB:
        //  does not contain $url = $this->_updatePathUseRewrites($url);
        // As such, this is expected behaviour
        $this->assertSame(
            expected: $expected,
            actual: $unsecureBaseUrl,
            message: 'Unsecure Base URL' . $assertMessageForArgs,
        );

        $secureBaseUrl = $url->getBaseUrl(
            params: [
                '_secure' => 1,
                '_type' => UrlInterface::URL_TYPE_WEB,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://base.domain-global.test/'
            : 'http://base.domain-global.test/';
        // As a "web" link, we don't have index.php.
        // For ref: \Magento\Store\Model\Store::getBaseUrl : note that case UrlInterface::URL_TYPE_WEB:
        //  does not contain $url = $this->_updatePathUseRewrites($url);
        // As such, this is expected behaviour
        $this->assertSame(
            expected: $expected,
            actual: $secureBaseUrl,
            message: 'Secure Base URL' . $assertMessageForArgs,
        );

        $unsecureBaseLinkUrl = $url->getBaseUrl(
            params: [
                '_secure' => 0,
                '_type' => UrlInterface::URL_TYPE_LINK,
            ],
        );
        $expected = 'http://link.domain-global.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $unsecureBaseLinkUrl,
            message: 'Unsecure Base Link URL' . $assertMessageForArgs,
        );

        $secureBaseLinkUrl = $url->getBaseUrl(
            params: [
                '_secure' => 1,
                '_type' => UrlInterface::URL_TYPE_LINK,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://link.domain-global.test/'
            : 'http://link.domain-global.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $secureBaseLinkUrl,
            message: 'Secure Base Link URL' . $assertMessageForArgs,
        );

        $secureDefaultBaseUrl = $url->getBaseUrl(
            params: [
                '_secure' => 1,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://link.domain-global.test/'
            : 'http://link.domain-global.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $secureDefaultBaseUrl,
            message: 'Secure Default Base URL' . $assertMessageForArgs,
        );

        $unsecureStoreUrl = $url->getBaseUrl(
            params: [
                '_scope' => $storeFixture1->getId(),
                '_secure' => 0,
            ],
        );
        $expected = 'http://link.domain1.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $unsecureStoreUrl,
            message: 'Unsecure Store URL' . $assertMessageForArgs,
        );

        $secureStoreUrl = $url->getBaseUrl(
            params: [
                '_scope' => $storeFixture1->getId(),
                '_secure' => 1,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://link.domain1.test/'
            : 'http://link.domain1.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $secureStoreUrl,
            message: 'Secure Store URL' . $assertMessageForArgs,
        );

        $exceptionThrown = false;
        try {
            $url->getBaseUrl(
                params: [
                    '_scope' => 999999,
                    '_secure' => 0,
                ],
            );
        } catch (\Exception $exception) {
            $this->assertInstanceOf(
                expected: NoSuchEntityException::class,
                actual: $exception,
                message: 'Exception for unknown store ID' . $assertMessageForArgs,
            );
            $exceptionThrown = true;
        }
        $this->assertTrue(
            condition: $exceptionThrown,
            message: 'Exception not thrown for unknown store ID' . $assertMessageForArgs,
        );
    }

    /**
     * @testWith [false, false]
     *           [false, true]
     *           [true, false]
     *           [true, true]
     *
     * @param bool $secureInFrontend
     *
     * @return void
     * @throws \Exception
     */
    public function testGetRouteUrl(
        bool $secureInFrontend,
        bool $useSeoRewrites,
    ): void {
        $assertMessageForArgs = sprintf(
            ' secureInFrontend: %s, useSeoRewrites: %s',
            $secureInFrontend ? 'true' : 'false',
            $useSeoRewrites ? 'true' : 'false',
        );

        ConfigFixture::setGlobal(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/base_url',
            value: 'https://base.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/use_in_frontend',
            value: (int)$secureInFrontend,
        );
        ConfigFixture::setGlobal(
            path: 'web/seo/use_rewrites',
            value: (int)$useSeoRewrites,
        );

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_ind_url_website1',
                'code' => 'klevu_ind_url_website1',
                'name' => 'Indexing: Url Website 1',
            ],
        );
        $websiteFixture1 = $this->websiteFixturesPool->get('klevu_ind_url_website1');

        $this->createStore(
            storeData: [
                'key' => 'klevu_ind_url_store1',
                'code' => 'klevu_ind_url_store1',
                'name' => 'Indexing: Url Store 1',
                'website_id' => $websiteFixture1->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_ind_url_store1');
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_url',
            value: 'https://base.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/',
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: (int)$secureInFrontend,
            storeCode: 'klevu_ind_url_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: (int)$useSeoRewrites,
            storeCode: 'klevu_ind_url_store1',
        );

        $url = $this->instantiateTestObject([]);

        $unsecureBaseUrl = $url->getRouteUrl(
            routeParams: [
                '_secure' => 0,
                '_type' => UrlInterface::URL_TYPE_WEB,
            ],
        );
        $expected = 'http://base.domain-global.test/';
        // As a "web" link, we don't have index.php.
        // For ref: \Magento\Store\Model\Store::getBaseUrl : note that case UrlInterface::URL_TYPE_WEB:
        //  does not contain $url = $this->_updatePathUseRewrites($url);
        // As such, this is expected behaviour
        $this->assertSame(
            expected: $expected,
            actual: $unsecureBaseUrl,
            message: 'Unsecure Base URL' . $assertMessageForArgs,
        );

        $secureBaseUrl = $url->getRouteUrl(
            routeParams: [
                '_secure' => 1,
                '_type' => UrlInterface::URL_TYPE_WEB,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://base.domain-global.test/'
            : 'http://base.domain-global.test/';
        // As a "web" link, we don't have index.php.
        // For ref: \Magento\Store\Model\Store::getBaseUrl : note that case UrlInterface::URL_TYPE_WEB:
        //  does not contain $url = $this->_updatePathUseRewrites($url);
        // As such, this is expected behaviour
        $this->assertSame(
            expected: $expected,
            actual: $secureBaseUrl,
            message: 'Secure Base URL' . $assertMessageForArgs,
        );

        $unsecureBaseLinkUrl = $url->getRouteUrl(
            routeParams: [
                '_secure' => 0,
                '_type' => UrlInterface::URL_TYPE_LINK,
            ],
        );
        $expected = 'http://link.domain-global.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $unsecureBaseLinkUrl,
            message: 'Unsecure Base Link URL' . $assertMessageForArgs,
        );

        $secureBaseLinkUrl = $url->getRouteUrl(
            routeParams: [
                '_secure' => 1,
                '_type' => UrlInterface::URL_TYPE_LINK,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://link.domain-global.test/'
            : 'http://link.domain-global.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $secureBaseLinkUrl,
            message: 'Secure Base Link URL' . $assertMessageForArgs,
        );

        $secureDefaultBaseUrl = $url->getRouteUrl(
            routeParams: [
                '_secure' => 1,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://link.domain-global.test/'
            : 'http://link.domain-global.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $secureDefaultBaseUrl,
            message: 'Secure Default Base URL' . $assertMessageForArgs,
        );

        $unsecureStoreUrl = $url->getRouteUrl(
            routeParams: [
                '_scope' => $storeFixture1->getId(),
                '_secure' => 0,
            ],
        );
        $expected = 'http://link.domain1.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $unsecureStoreUrl,
            message: 'Unsecure Store URL' . $assertMessageForArgs,
        );

        $secureStoreUrl = $url->getRouteUrl(
            routeParams: [
                '_scope' => $storeFixture1->getId(),
                '_secure' => 1,
            ],
        );
        $expected = $secureInFrontend
            ? 'https://link.domain1.test/'
            : 'http://link.domain1.test/';
        $expected .= $useSeoRewrites ? '' : 'index.php/';
        $this->assertSame(
            expected: $expected,
            actual: $secureStoreUrl,
            message: 'Secure Store URL' . $assertMessageForArgs,
        );

        $exceptionThrown = false;
        try {
            $url->getrouteUrl(
                routeParams: [
                    '_scope' => 999999,
                    '_secure' => 0,
                ],
            );
        } catch (\Exception $exception) {
            $this->assertInstanceOf(
                expected: NoSuchEntityException::class,
                actual: $exception,
                message: 'Exception for unknown store ID' . $assertMessageForArgs,
            );
            $exceptionThrown = true;
        }
        $this->assertTrue(
            condition: $exceptionThrown,
            message: 'Exception not thrown for unknown store ID' . $assertMessageForArgs,
        );
    }
}
