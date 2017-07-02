<?php

/**
 * Created by IntelliJ IDEA.
 * User: work
 * Date: 7/2/17
 * Time: 3:11 PM
 */

namespace MaximMV\Bundle\UniversalFilterBundle\Chain;


class FilterChain
{
    private $filters = [];

    public function addFilter($filter, string $type)
    {
        $this->filters[$type] = $filter;
    }

    public function getTypes()
    {
        return array_keys($this->filters);
    }

    public function getFilter(string $type)
    {
        if (key_exists($type, $this->filters)) {
            return $this->filters[$type];
        }
    }


}