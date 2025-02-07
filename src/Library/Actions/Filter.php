<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 23:20
 */

namespace Amondar\Sextant\Library\Actions;

use Amondar\Sextant\Contracts\SextantActionContract;
use Amondar\Sextant\Library\SextantCore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Filter
 *
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class Filter extends SextantCore implements SextantActionContract
{

    /**
     * @var Collection
     */
    public $filterConditions = null;

    /**
     * @var string
     */
    protected $filterRequestField;

    /**
     * Filter class constructor.
     *
     * @param Builder $query
     * @param         $requestField
     * @param Request $request
     * @param array   $params
     */
    public function __construct(Builder $query, $requestField, Request $request = null, $restrictions = [], $params = [])
    {
        parent::__construct($query, $query->getModel(), $request, $restrictions);
        $this->filterConditions = collect([]);
        $this->filterRequestField = $requestField;
        $this->get($params);
    }

    /**
     * Set queries.
     */
    public function set()
    {
        $this->filterConditions->groupBy('relation')->each(function ($conditions, $relation) {
            if ($relation) {
                $this->setRelationOperation($conditions, $relation);
            } else {
                //filtration in current table. $relation = null.
                $this->setOperation($conditions);
            }
        });
    }

    /**
     * Set queries as relation.
     *
     * @param       $sortRequestField
     * @param array $params
     *
     * @return Collection
     */
    public function setAsRelation($sortRequestField, $params = [])
    {
        $sorted = collect([]);
        $sortInstance = new Sort($this->query, $sortRequestField, $this->request, $params);
        foreach ($this->filterConditions->groupBy('relation') as $relation => $conditions) {
            if ($relation) {
                $sort = $this->checkSortRelation($sortInstance, $relation);
                if ($sort) {
                    $sorted = $sorted->merge($sort);
                }
                $this->filterByRelation($relation, $conditions, $sortInstance, $sort);
            }
        }

        return $sorted;
    }

    /**
     * Filter by relation parameters.
     *
     * @param            $relation
     * @param Collection $conditions
     * @param Sort       $sortInstance
     * @param            $sort
     */
    protected function filterByRelation($relation, $conditions, Sort $sortInstance = null, $sort = null)
    {
        $this->query->with([ $relation => function ($query) use ($conditions, $sortInstance, $sort) {
            $this->setOperation($conditions, null, $query->getQuery());
            if ($sort) {
                $this->setSortModelForRelationExpand($sortInstance, $sort, $query);
            }
        } ]);
    }

    /**
     * Set storing for expanded model.
     *
     * @param Sort       $sortInstance
     * @param Collection $sort
     * @param            $query
     */
    protected function setSortModelForRelationExpand(Sort $sortInstance, Collection $sort, &$query)
    {
        $sortInstance->startTransition($query);
        $sort->each(function ($condition) use ($sortInstance) {
            $sortInstance->sortModel($condition);
        });
        $sortInstance->stopTransition();
    }


    /**
     * Get filtration parameters.
     *
     * @param $params
     *
     * @return void
     */
    protected function get($params)
    {
        $conditions = [];
        if (isset($params[ $this->filterRequestField ]) && is_array($params[ $this->filterRequestField ])) {
            $conditions = $params[ $this->filterRequestField ];
        }

        if ($this->request && $this->request->has($this->filterRequestField)) {
            $conditions = $this->mergeConditions($this->request->input($this->filterRequestField), $conditions);
        }

        $this->prepareConditions(collect($conditions));
    }

    /**
     * Сливайет вместе параметры.
     * Только данные "whereIn" операций не затираются.
     *
     * @param $requestConditions
     * @param $params
     *
     * @return mixed
     */
    public function mergeConditions($requestConditions, $params)
    {
        $conditions = json_decode($requestConditions, true);
        $filterConditions = $params;
        if (is_array($conditions)) {
            foreach ($conditions as $key => $row) {
                $hasKey = isset($filterConditions[ $key ]);
                if ( ! $hasKey) {
                    $filterConditions[ $key ] = $row;
                } elseif ($hasKey && isset($filterConditions[ $key ]['operation']) && isset($filterConditions[ $key ]['value'])) {
                    $this->mergeOperations($filterConditions, $key, $row);
                }
            }
        }

        return collect($filterConditions);
    }

    /**
     * Мержит операции с дополнением параметров.
     * TODO Если станет много операций для мерджа - использовать switch-case структуру.
     *
     * @param $conditions
     * @param $index
     * @param $row
     */
    protected function mergeOperations(&$conditions, $index, $row)
    {
        if (isset($row['operation']) && isset($row['value'])) {
            if (
                $row['operation'] == 'in' &&
                is_array($row['value'])
            ) {
                $conditions[ $index ]['value'] = array_unique(array_merge($conditions[ $index ]['value'], $row['value']));
            }
        }
    }

    /**
     * Проверяет реляцию на совпадение и существование соритровки для нее.
     *
     * @param Sort $sort
     * @param      $relation
     *
     * @return bool|Collection
     */
    protected function checkSortRelation(Sort $sort, $relation)
    {
        if ($sort->sortConditions->contains('relation', $relation)) {
            return $sort->sortConditions->where('relation', $relation);
        }

        return null;
    }

    /**
     * Prepare filter conditions to work on.
     *
     * @param Collection $conditions
     */
    protected function prepareConditions(Collection $conditions)
    {
        foreach ($conditions as $key => $condition) {
            list($relation, $table_name, $field_name) = $this->getFieldParametersWithExistsCheck($key);
            $this->addCondition($relation, $table_name, $field_name, $condition);
        }
    }

    /**
     * Add condition to array.
     *
     * @param $relation
     * @param $tableName
     * @param $fieldName
     * @param $condition
     *
     * @return bool
     */
    protected function addCondition($relation, $tableName, $fieldName, $condition)
    {
        if ($relation && ! $this->relationEnabled($relation, $this->filterRequestField)) {
            return false;
        }

        if (($tableName && $fieldName) || $relation) {
            $this->filterConditions->push($this->getConditionParameters($relation, $tableName, $fieldName, $condition));
        }

        return true;
    }
}