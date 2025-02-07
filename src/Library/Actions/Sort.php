<?php

namespace Amondar\Sextant\Library\Actions;


use Amondar\Sextant\Contracts\SextantActionContract;
use Amondar\Sextant\Library\SextantCore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Sort
 *
 * @version 1.0.0
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class Sort extends SextantCore implements SextantActionContract
{

    /**
     * @var Collection
     */
    public $sortConditions = NULL;

    /**
     * @var string
     */
    protected $sortRequestField;


    /**
     * Sort class constructor.
     *
     * @param Builder $query
     * @param         $requestField
     * @param Request $request
     * @param array   $restrictions
     * @param array   $params
     */
    public function __construct(Builder $query, $requestField, Request $request = NULL, $restrictions = [], $params = [])
    {
        parent::__construct($query, $query->getModel(), $request, $restrictions);
        $this->sortConditions = collect([]);
        $this->sortRequestField = $requestField;
        $this->get($params);
    }

    /**
     * Set queries.
     *
     * @return mixed
     */
    public function set()
    {
        // By default ordering is simple.
        $simpleSort = true;

        $this->sortConditions->each(function ($condition) use (&$simpleSort) {
            if ( $condition->relation ) {
                $this->sortRelation($condition);

                // Mark sorting as non simple and we should add unique ordering part after all sorting.
                $simpleSort = false;
            } else {
                $this->sortModel($condition);
            }
        });


        // Add sorting inside group by table column, cuz only table columns will always be unique in each query.
        if ( ! $simpleSort ) {
            $this->query->orderBy($this->query->getModel()->getQualifiedKeyName());
        }

        return $this->query;
    }

    /**
     * Set queries as relation.
     *
     * @param Collection $sorted
     *
     * @return Builder
     */
    public function setAsRelation(Collection $sorted)
    {
        $this->query->with($this->getEagerLoadRelationsArray($sorted));

        return $this->query;
    }

    /**
     * Return eager load relations array with prepared queries.
     *
     * @param Collection $sorted
     *
     * @return array
     */
    public function getEagerLoadRelationsArray(Collection $sorted)
    {
        $result = [];
        foreach ( $this->sortConditions as $sortCondition ) {
            if ( $sortCondition->relation && ! $sorted->contains('relation', $sortCondition->relation) ) {
                $result[ $sortCondition->relation ] = function ($q) use ($sortCondition) {
                    $this->startTransition($q);
                    $this->sortModel($sortCondition);
                    $this->stopTransition();
                };
            }
        }

        return $result;
    }

    /**
     * Sort by relation field.
     *
     * @param $condition
     */
    public function sortRelation($condition)
    {
        // Make model relation join for sorting.
        $this->query->modelJoin($condition->relation, $condition->field);

        // Make simple sorting.
        $this->sortModel($condition, true);
    }

    /**
     * Sort by root model field.
     *
     * @param      $condition
     * @param bool $raw
     */
    public function sortModel($condition, $raw = false)
    {
        // Get qualified sort field.
        $qualifiedJoinReName = $this->getConditionFullPath($condition, $raw);

        // Make sorting
        if ( $raw ) {
            $this->query->orderByRaw("$qualifiedJoinReName $condition->direction");
        } else {
            $this->query->orderBy($qualifiedJoinReName, $condition->direction);
        }
    }

    /**
     * Get filtration parameters.
     *
     * @param array $params
     *
     * @return void
     */
    protected function get($params = [])
    {
        $sortParts = collect([]);
        if ( $this->request && $this->request->has($this->sortRequestField) ) {
            $sortParts = $sortParts->merge(explode(',', $this->request->get($this->sortRequestField)));
        }

        if ( isset($params[ $this->sortRequestField ]) ) {
            $sortParts = $sortParts->merge(explode(',', $params[ $this->sortRequestField ]));
        }

        // Restrict conditions to prevent using disabled.
        $sortParts = $this->restrictConditions($sortParts, $this->sortRequestField);

        // Prepare sorting conditions.
        foreach ( $sortParts as $part ) {
            $this->setSortPartConditions($part);
        }

        $this->sortConditions = $this->sortConditions->unique(function ($condition) {
            return $condition->relation . $condition->table . $condition->field;
        });

    }

    /**
     * Set filtration parameters for one block.
     *
     * @param $part
     *
     * @return bool
     */
    protected function setSortPartConditions($part)
    {
        $sign = substr(trim($part), 0, 1);
        $sort_direction = 'asc';

        if ( $sign == '-' ) {
            $sort_direction = 'desc';
        }

        [ $relation, $table_name, $field_name ] = $this->getFieldParametersWithExistsCheck(trim($part, '-'));

        // Check that operations on relation are not restricted.
        if ( ! $this->relationEnabled($relation, $this->sortRequestField) ) {
            return false;
        }

        if ( $table_name && $field_name ) {
            $this->sortConditions->push((object) [
                'table'     => $table_name,
                'field'     => $field_name,
                'direction' => $sort_direction,
                'relation'  => $relation,
            ]);
        }

        return true;
    }

}