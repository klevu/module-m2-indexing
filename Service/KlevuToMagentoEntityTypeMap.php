<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\KlevuToMagentoEntityTypeMapInterface;

class KlevuToMagentoEntityTypeMap implements KlevuToMagentoEntityTypeMapInterface
{
    public const KEY_KLEVU_ENTITY_TYPE = 'klevu_entity_type';
    public const KEY_MAGENTO_ENTITY_TYPE_ID = 'magento_entity_type_id';
    /**
     * @var array<string, array<string, int|string>>
     */
    private array $klevuToMagentoEntityTypeMapItems = [];

    /**
     * @param array<string, array<string, int|string>> $klevuToMagentoEntityTypeMapItems
     */
    public function __construct(
        array $klevuToMagentoEntityTypeMapItems = [],
    ) {
        array_walk($klevuToMagentoEntityTypeMapItems, [$this, 'addKlevuToMagentoEntityTypeMapItem']);
    }

    /**
     * @param int $magentoEntityTypeId
     *
     * @return string|null
     */
    public function getKlevuEntityTypeForMagentoEntityTypeId(int $magentoEntityTypeId): ?string
    {
        $items = array_filter(
            array: $this->klevuToMagentoEntityTypeMapItems,
            callback: static fn (array $klevuToMagentoEntityTypeMapItem): bool => (
                $klevuToMagentoEntityTypeMapItem[self::KEY_MAGENTO_ENTITY_TYPE_ID] === $magentoEntityTypeId
            ),
        );

        $item = current($items);

        return $item
            ? $item[self::KEY_KLEVU_ENTITY_TYPE]
            : null;
    }

    /**
     * @param string $klevuEntityType
     *
     * @return int[]
     */
    public function getMagentoEntityTypeIdsForKlevuEntityType(string $klevuEntityType): array
    {
        $items = array_filter(
            array: $this->klevuToMagentoEntityTypeMapItems,
            callback: static fn (array $klevuToMagentoEntityTypeMapItem): bool => (
                $klevuToMagentoEntityTypeMapItem[self::KEY_KLEVU_ENTITY_TYPE] === $klevuEntityType
            ),
        );

        return array_column(
            array: $items,
            column_key: self::KEY_MAGENTO_ENTITY_TYPE_ID,
        );
    }

    /**
     * @param array<string, int|string>|null $klevuToMagentoEntityTypeMapItem
     * @param string $identifier
     *
     * @return void
     */
    private function addKlevuToMagentoEntityTypeMapItem(
        ?array $klevuToMagentoEntityTypeMapItem,
        string $identifier,
    ): void {
        if (null === $klevuToMagentoEntityTypeMapItem) {
            return;
        }

        if (
            empty($klevuToMagentoEntityTypeMapItem[self::KEY_KLEVU_ENTITY_TYPE])
            || !is_string($klevuToMagentoEntityTypeMapItem[self::KEY_KLEVU_ENTITY_TYPE])
        ) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'klevuToMagentoEntityType item %s does not contain a valid value for key %s',
                    $identifier,
                    self::KEY_KLEVU_ENTITY_TYPE,
                ),
            );
        }

        if (
            empty($klevuToMagentoEntityTypeMapItem[self::KEY_MAGENTO_ENTITY_TYPE_ID])
            || !is_numeric($klevuToMagentoEntityTypeMapItem[self::KEY_MAGENTO_ENTITY_TYPE_ID])
            || $klevuToMagentoEntityTypeMapItem[self::KEY_MAGENTO_ENTITY_TYPE_ID] < 1
        ) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'klevuToMagentoEntityType item %s does not contain a valid value for key %s',
                    $identifier,
                    self::KEY_MAGENTO_ENTITY_TYPE_ID,
                ),
            );
        }

        $this->klevuToMagentoEntityTypeMapItems[$identifier] = [
            self::KEY_KLEVU_ENTITY_TYPE => $klevuToMagentoEntityTypeMapItem[self::KEY_KLEVU_ENTITY_TYPE],
            self::KEY_MAGENTO_ENTITY_TYPE_ID => (int)$klevuToMagentoEntityTypeMapItem[self::KEY_MAGENTO_ENTITY_TYPE_ID],
        ];
    }
}
