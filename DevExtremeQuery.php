<?php

namespace App\IcaTeknoloji;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DevExtremeQuery
{
    protected $filter;
    protected $sort;
    protected $skip;
    protected $take;
    protected $requireTotalCount;

    public function handle(Model $model, Request $request)
    {
        $this->parseRequestParameters($request);

        $query = $model->newQuery();

        if ($this->filter) {
            $this->applyFilter($query, $this->filter);
        }

        if ($this->sort) {
            $this->applySort($query, $this->sort);
        }

        if ($this->skip || $this->take) {
            $this->applyPagination($query);
        }

        $data = $query->get();
        $result = $this->transformResults($data);

        if ($this->requireTotalCount) {
            $totalCount = $this->getTotalCount($model);
            $result['totalCount'] = $totalCount;
        }

        return $result;
    }

    protected function parseRequestParameters(Request $request)
    {
        $this->filter = $request->input('filter');
        $this->sort = $request->input('sort');
        $this->skip = $request->input('skip');
        $this->take = $request->input('take');
        $this->requireTotalCount = $request->input('requireTotalCount');
    }

    protected function applyFilter($query, $filter)
    {
        $this->applyNestedFilter($query, $filter);
    }

    protected function applyNestedFilter($query, $filter, $logicalOperator = 'and')
    {
        $query->where(function ($query) use ($filter, $logicalOperator) {
            foreach ($filter as $filterItem) {
                if (is_array($filterItem)) {
                    if ($this->isLogicalOperator($filterItem)) {
                        $this->applyNestedFilter($query, $filterItem['filters'], $filterItem['operator']);
                    } else {
                        $this->applyComparisonFilter($query, $filterItem, $logicalOperator);
                    }
                }
            }
        });
    }

    protected function isLogicalOperator($filterItem)
    {
        return isset($filterItem['operator']) && in_array($filterItem['operator'], ['and', 'or']);
    }

    protected function applyComparisonFilter($query, $filterItem, $logicalOperator)
    {
        $field = $filterItem['field'];
        $operator = $filterItem['operator'];
        $value = $filterItem['value'];

        $operator = $this->getComparisonOperator($operator);
        $query->where($field, $operator, $value, $logicalOperator);
    }

    protected function getComparisonOperator($operator)
    {
        $operators = [
            'equal' => '=',
            'notEqual' => '<>',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'contains' => 'like',
            'notContains' => 'not like',
            'startsWith' => 'like',
            'endsWith' => 'like',
        ];

        if (isset($operators[$operator])) {
            return $operators[$operator];
        }

        throw new \Exception("Invalid filter operator: $operator");
    }

    protected function applySort($query, $sort)
    {
        foreach ($sort as $criteria) {
            $field = $criteria['field'];
            $direction = $criteria['desc'] ? 'desc' : 'asc';
            $query->orderBy($field, $direction);
        }
    }

    protected function applyPagination($query)
    {
        $this->skip = (int) $this->skip;
        $this->take = (int) $this->take;
        $query->skip($this->skip)->take($this->take);
    }

    protected function transformResults($data)
    {
        return [
            'data' => $data,
        ];
    }

    protected function getTotalCount($model)
    {
        $countQuery = clone $model->getQuery();

        if ($this->filter) {
            $this->applyFilter($countQuery, $this->filter);
        }

        return $countQuery->count();
    }
}
