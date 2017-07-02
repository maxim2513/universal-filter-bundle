<?php
/**
 * Created by PhpStorm.
 * User: maxim
 * Date: 3/13/17
 * Time: 1:38 PM
 */

namespace MaximMV\Bundle\UniversalFilterBundle\Filter;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SearchFactory
{
    const CLASS_KEY = 'class';
    const PARENT_KEY = 'parent';
    const MAPPING_KEY = 'fields';
    const QUERY_KEY = 'query';
    const ORDER_KEY = 'order';
    const PRIVATE_KEY = 'private';

    /**
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @var SearchManager
     */
    protected $searchManager;

    /**
     * @var array
     */
    protected $searchableEntity;

    public function __construct(ObjectManager $entityManager, SearchManager $searchManager, array $entities)
    {
        $this->entityManager = $entityManager;
        $this->searchableEntity = $entities;
        $this->searchManager = $searchManager;
    }

    /**
     * @return array
     */
    public function getAvailableEntity()
    {
        $keys = [];
        foreach ($this->searchableEntity as $key => $map) {
            /** @var array $map */
            if (key_exists(self::PRIVATE_KEY, $map)) {
                if (!$map[self::PRIVATE_KEY]) {
                    $keys[] = $key;
                }
            } else {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param string $entityName
     * @param int $dept
     *
     * @return SearchManager
     */
    public function load(string $entityName, int $dept = 3)
    {
        if (!array_key_exists($entityName, $this->searchableEntity)) {
            throw new BadRequestHttpException('change type to available type: ' . implode(', ', array_keys($this->searchableEntity)));
        }

        $className = $this->loadClassName($entityName);
        $mapping = $this->loadMapping($entityName, $dept);

        $searchManager = clone $this->searchManager->load($this, $className, $mapping);

        $searchableFields = $this->searchableFields($entityName);
        $searchManager->setSearchableFields($searchableFields);

        $orderFields = $this->orderFields($entityName);
        $searchManager->setOrderFields($orderFields);

        return $searchManager;
    }

    /**
     * @param $entityName
     * @return string
     */
    private function loadClassName($entityName)
    {
        return $this->searchableEntity[$entityName][self::CLASS_KEY];
    }


    /**
     * @param string $entityName
     * @param int $dept
     * @return array
     */
    private function loadMapping(string $entityName, int $dept = 1)
    {
        $mapping = $this->searchableEntity[$entityName][self::MAPPING_KEY] ?? [];

        if (key_exists(self::PARENT_KEY, $this->searchableEntity[$entityName])) {
            $parentEntityName = $this->searchableEntity[$entityName][self::PARENT_KEY];
            $parentMapping = $this->searchableEntity[$parentEntityName][self::MAPPING_KEY];

            $mapping = array_merge($mapping, $parentMapping);
        }

        $className = $this->loadClassName($entityName);
        $entityMetadata = $this->entityManager->getClassMetadata($className);

        foreach ($mapping as $key => $type) {
            if (in_array($key, $entityMetadata->getFieldNames())) {
                if (is_null($type)) {
                    $mapping[$key] = [
                        'type' => $entityMetadata->getTypeOfField($key),
                    ];
                    continue;
                }
            }

            if (in_array($key, $entityMetadata->getAssociationNames()) && $dept > 0) {
                $mapping[$key] = [
                    'type' => 'object',
                    'search' => $this->load(is_null($type) ? $key : $type, $dept - 1),
                ];
                continue;
            }

            $mapping[$key] = ['type' => is_null($type) ? $key : $type];
        }

        return $mapping;
    }

    /**
     * @param string $entityName
     * @return array
     */
    public function searchableFields(string $entityName)
    {
        $searchableFields = $this->searchableEntity[$entityName][self::QUERY_KEY] ?? [];

        if (key_exists(self::PARENT_KEY, $this->searchableEntity[$entityName])) {
            $parentEntityName = $this->searchableEntity[$entityName][self::PARENT_KEY];
            $parentSearchableFields = $this->searchableEntity[$parentEntityName][self::QUERY_KEY] ?? [];

            $searchableFields = array_merge($searchableFields, $parentSearchableFields);
        }

        return $searchableFields;
    }

    /**
     * @param string $entityName
     * @return array
     */
    public function orderFields(string $entityName)
    {
        $orderFields = $this->searchableEntity[$entityName][self::ORDER_KEY] ?? [];

        if (key_exists(self::PARENT_KEY, $this->searchableEntity[$entityName])) {
            $parentEntityName = $this->searchableEntity[$entityName][self::PARENT_KEY];
            $parentOrderFields = $this->searchableEntity[$parentEntityName][self::ORDER_KEY] ?? [];

            $orderFields = array_merge($orderFields, $parentOrderFields);
        }

        return $orderFields;
    }

}

