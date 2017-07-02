<?php
/**
 * Created by PhpStorm.
 * User: maxim
 * Date: 3/13/17
 * Time: 11:42 AM
 */

namespace MaximMV\Bundle\UniversalFilterBundle\Filter;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SearchManager
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var SearchFactory
     */
    protected $factory;


    /**
     * @var string
     */
    protected $entityClassName;

    /**
     * @var array
     */
    protected $fieldMetadata;

    /**
     * @var array
     */
    protected $searchableFields = [];

    /**
     *
     */
    protected $orderFields = [];

    /**
     * @var string
     */
    protected $alias;

    public function __construct(ObjectManager $entityManager)
    {
        $this->entityManager = $entityManager;

    }

    public function load(SearchFactory $factory, string $className, array $filterFields)
    {
        $this->factory = $factory;
        $this->entityClassName = $className;
        $this->fieldMetadata = $filterFields;

        return $this;
    }

    /**
     * @return array
     */
    public function getAvailableFields()
    {
        $mapping = $this->fieldMetadata;
        foreach ($mapping as $field => $type) {
            if ('object' == $type['type']) {
                unset($mapping[$field]['search'], $mapping[$field]['column']);
                /** @var SearchManager $searchDept */
                $searchDept = $this->fieldMetadata[$field]['search'];
                $mapping[$field]['fields'] = $searchDept->getAvailableFields();
            }
        }

        return $mapping;
    }

    /**
     * @return array
     */
    public function getSearchableFields()
    {
        return $this->searchableFields;
    }

    /**
     * @param array $searchableFields
     * @return $this
     */
    public function setSearchableFields(array $searchableFields)
    {

        foreach ($searchableFields as $field) {
            if (!key_exists($field, $this->fieldMetadata)) {
                throw new \InvalidArgumentException('query field not available: ' . $field);
            }
        }
        $this->searchableFields = $searchableFields;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getOrderFields()
    {
        return $this->orderFields;
    }

    /**
     * @param string[] $orderFields
     * @return SearchManager
     */
    public function setOrderFields($orderFields)
    {
        $this->orderFields = $orderFields;

        return $this;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isAvailableOrderType(string $type)
    {
        return in_array($type, $this->orderFields);
    }


    /**
     * @param string $alias
     * @return SearchManager
     */
    public function setAlias(string $alias = '')
    {
        if (empty($alias)) {
            $lastPartName = end(explode('\\', $this->entityClassName));
            $this->alias = strtolower($lastPartName);
        } else {
            $this->alias = $alias;
        }

        return $this;
    }


    /**
     * @param array $filters
     * @param QueryBuilder|null $qb
     * @param string $prefix
     * @throws \InvalidArgumentException
     * @return QueryBuilder
     */
    public function getFilterQuery(array $filters = [], QueryBuilder &$qb = null, string $prefix = '')
    {
        $qb = $this->getQueryBuilder($qb);

        //  publishedAt
        if (key_exists('publishedAt', $this->fieldMetadata)) {
            $qb
                ->where($this->alias . '.publishedAt < :now')
                ->setParameter('now', (new \DateTime()));
        }

        foreach ($filters as $key => $params) {
            $aliasParameter = $this->alias . '_' . $key;

            if (!key_exists($key, $this->fieldMetadata)) {
                throw new BadRequestHttpException('not found search field :' . $key);
            }
            $columnMeta = $this->fieldMetadata[$key];
            $aliasField = $this->alias . '.' . $key;


            // time filter
            if (is_array($params) && key_exists('time', $params) && $columnMeta['type'] == 'datetime') {
                $modifyAliasField = ' time(' . $aliasField . ' ) ';
                $timeParams = $params['time'];
                $timeAliasParam = $aliasParameter . '_time';
                unset($params['time']);
                foreach ((array)$timeParams as $type => $value) {
                    $qb->andWhere($qb->expr()->$type($modifyAliasField, ':' . $timeAliasParam . '_' . $type))
                        ->setParameter($timeAliasParam . '_' . $type, $value);
                }
            }
            // string to lower
            if ('string' == $columnMeta['type']) {
                $aliasField = $qb->expr()->lower($aliasField);
            }


            if (is_array($params)
                && key_exists('isNull', $params)
            ) {
                if ($params['isNull']) {
                    $qb->andWhere($qb->expr()->isNull($aliasField));
                } else {
                    $qb->andWhere($qb->expr()->isNotNull($aliasField));
                }
                unset($params['isNull']);
                if (empty($params)) {
                    continue;
                }
            }


            if (is_array($params)
                && !in_array($columnMeta['type'], ['object', 'geopoint'])
                && !in_array($columnMeta['type'], $this->factory->getAvailableEntity())
            ) {
                // sql functions
                foreach ($params as $type => $value) {
                    $qb->andWhere($qb->expr()->$type($aliasField, ':' . $aliasParameter . '_' . $type))
                        ->setParameter($aliasParameter . '_' . $type, $value);

                }
            } else {
                // for type
                switch ($columnMeta['type']) {
                    case 'string':
                        $qb->andWhere($qb->expr()->like($aliasField, ':' . $aliasParameter))
                            ->setParameter($aliasParameter, '%' . $params . '%');
                        break;
                    case 'integer':
                    case 'boolean':
                    case 'datetime':
                        $qb->andWhere($qb->expr()->eq($aliasField, ':' . $aliasParameter))
                            ->setParameter($aliasParameter, $params);

                        break;
                    case 'geopoint':
                        if (!key_exists('lat', $this->fieldMetadata) || !key_exists('lng', $this->fieldMetadata)) {
                            throw new \InvalidArgumentException('geopoint not available for this entity' . implode(' ', array_keys($this->fieldMetadata)));
                        }
                        if (!key_exists('lat', $params) || !key_exists('lng', $params) || !key_exists('distance', $params)) {
                            throw new BadRequestHttpException('check set keys: lat , lnd , distance');
                        }

                        $aliasLatField = $this->alias . '.lat';
                        $aliasLngField = $this->alias . '.lng';
                        $aliasDistanceField = 'distance';

                        $qb->addSelect('( 6371 * acos(' .
                            $qb->expr()->sum(
                                $qb->expr()->prod(
                                    'cos( radians(:lat) )',
                                    $qb->expr()->prod(
                                        'cos( radians( ' . $aliasLatField . ' ) )',
                                        'cos(' . $qb->expr()->diff(
                                            'radians( ' . $aliasLngField . ' )',
                                            'radians(:lng)')
                                        . ' )'
                                    )
                                ),
                                $qb->expr()->prod(
                                    'sin(radians(:lat))',
                                    'sin(radians(' . $aliasLatField . '))'
                                )
                            ) . ')) AS Hidden ' . $aliasDistanceField)
                            ->having($aliasDistanceField . ' <= :distance')
                            ->setParameter('distance', $params['distance'])
                            ->setParameter('lat', $params['lat'])
                            ->setParameter('lng', $params['lng']);

                        break;

                    case 'object':
                        if (is_array($params)) {
                            $qb->join($aliasField, $prefix . $key);
                            /** @var SearchManager $searchDept */
                            $searchDept = $columnMeta['search'];
                            $searchDept->setAlias($prefix . $key)->getFilterQuery($params, $qb);
                        } else {
                            $qb->andWhere(
                                $qb->expr()->eq($aliasField, ':' . $aliasParameter)
                            )
                                ->setParameter($aliasParameter, $params);
                        }
                        break;
                    default:
                        if (in_array($columnMeta['type'], $this->factory->getAvailableEntity())) {
                            $searchDept = $this->factory->load($columnMeta['type'], 1);
                            $qb->join($aliasField, $prefix . $key . 'dept');
                            $searchDept->setAlias($prefix . $key . 'dept')->getFilterQuery($params, $qb);
                        } else {
                            throw new \InvalidArgumentException('bad type field in filter: ' . $key . '[' . $columnMeta['type'] . ']');
                        }
                }
            }
        }
        return $qb;
    }

    /**
     * @param string $query
     * @param QueryBuilder|null $qb
     * @return string
     */
    public function getSearchQuery(string $query, QueryBuilder &$qb = null)
    {
        $qb = $this->getQueryBuilder($qb);

        $expr = $qb->expr()->orX();

        foreach ($this->searchableFields as $field) {
            $expr->add($qb->expr()->like($qb->expr()->lower($this->alias . '.' . $field), ':query'));
        }
        $qb->andWhere($expr)
            ->setParameter('query', $query);

        return $query;
    }

    /**
     * @param string $type
     * @param string $order
     * @param QueryBuilder|null $qb
     * @return QueryBuilder
     */
    public function getOrderQuery(string $type, string $order, QueryBuilder &$qb = null)
    {
        $qb = $this->getQueryBuilder($qb);

        if (!$this->isAvailableOrderType($type) && !key_exists($type,$this->fieldMetadata)) {
            throw new BadRequestHttpException('order field fail');
        }
        switch ($type) {
            case 'free': {
                if (!key_exists('ticketsType', $this->fieldMetadata)) {
                    break;
                }
                $qb->OrderBy('if( ' . $qb->expr()->like($this->alias . '.ticketsType', ':free') . ' , 1 , 0 )', $order)
                    ->setParameter('free', '%free%');
                break;
            }
            default:
                $qb->addOrderBy($this->alias.'.'.$type, $order);
        }

        return $qb;
    }

    /**
     * @param QueryBuilder|null $qb
     * @return QueryBuilder
     */
    private function getQueryBuilder(QueryBuilder $qb = null)
    {
        if (!$qb) {
            $qb = $this->entityManager->createQueryBuilder()
                ->select($this->alias)
                ->from($this->entityClassName, $this->alias);
        }

        return $qb;
    }
}