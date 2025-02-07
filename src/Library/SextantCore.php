<?php

namespace Amondar\Sextant\Library;

use Amondar\Sextant\Library\Operations\SextantOperation;
use Amondar\Sextant\Library\Operations\SextantRelationOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class SextantCore
 *
 * @version 1.0.0
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class SextantCore
{

    /**
     * @var Builder
     */
    public $query;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var Model
     */
    public $model;

    /**
     * Restrictions array for sextant operations.
     *
     * @var array
     */
    public $restrictions;

    /**
     * Possible table fields.
     *
     * @var array
     */
    public $tableFields = [];

    /**
     * Transaction to store query env for relations queries..
     *
     * @var array
     */
    protected $transition = [
        'query'   => null,
        'request' => null,
    ];

    /**
     * Mini cache for expandable check validation.
     *
     * @var array
     */
    protected $isExpandable = [];

    /**
     * Possible expandable request keys
     *
     * @var array
     */
    protected $expandableRequestKeys = ['expand', 'filterExpand', 'sortExpand'];

    /**
     * Current sextant instance expand request key for restrictions.
     *
     * @var NULL|string
     */
    protected $expandRestrictionsRequestKey;

    /**
     * FilterAndSortingFacade class constructor.
     *
     * @param Builder $query
     * @param Model   $model
     * @param Request $request
     * @param array   $restrictions
     */
    public function __construct(Builder $query, Model $model, Request $request = null, $restrictions = [])
    {

        $this->query = $query;
        $this->request = $request;
        $this->model = $model;
        $this->restrictions = $restrictions;
        $this->expandRestrictionsRequestKey = app('sextant')->getConfigRequestKey('expand');
    }

    /**
     * Return model available fields.
     *
     * @param mixed $model
     *
     * @return array
     */
    public function getModelAvailableFields($model)
    {
        // Detect field name.
        $tableName = $model;
        if ($model instanceof Model) {
            $tableName = $model->getTable();
        }

        // Check if we already took table fields.
        if (isset($this->tableFields[ $tableName ])) {
            return $this->tableFields[ $tableName ];
        }

        // If not - take fields.
        $fields = array_keys(app('db')->getDoctrineSchemaManager()->listTableColumns($tableName));

        return $this->tableFields[ $tableName ] = array_map(function ($item) {
            return trim($item, '`');
        }, $fields);
    }

    /**
     * Determine table name by relation name.
     *
     * @param string $relationString
     *
     * @return string
     */
    public function detectTableNameFromRelation($relationString)
    {
        $keys = explode('.', $relationString);
        $relatedModel = $this->model;
        foreach ($keys as $relation) {
            if (method_exists($relatedModel, $relation)) {
                $relatedModel = $relatedModel->$relation()->getRelated();
            }
        }

        return $relatedModel->getTable();
    }

    /**
     * Check is relation can be processed for outside api.
     *
     * @param $keys
     *
     * @return bool
     */
    public function checkRelation(array $keys)
    {
        $detectRelation = null;
        $i = 0;
        $relation = $keys[ $i ];
        do {
            if (in_array($relation, $this->model->extraFields())) {
                $detectRelation = $relation;
            } elseif ($i != count($keys) - 1) {//this is not last iteration.
                return null;
            }
            $i++;
            if ( ! empty($keys[ $i ])) {
                $relation .= ".$keys[$i]";
            }
        } while ($i < count($keys));

        return $detectRelation;
    }

    /**
     * Return filed parameters. Relation, name, table etc.
     *
     * @param      $filterField
     *
     * @param bool $checkExists
     *
     * @return array
     */
    public function getFieldParameters($filterField, $checkExists = false)
    {
        $keys_array = explode('.', $filterField);
        $relation = $this->checkRelation($keys_array);
        if (count($keys_array) >= 2 && $relation) {
            $field_name = last($keys_array);
            $table_name = $this->detectTableNameFromRelation($relation);
        } else {
            $field_name = $filterField;
            $table_name = $this->model->getTable();
        }

        if ($checkExists && ! in_array($field_name, $this->getModelAvailableFields($table_name))) {
            $field_name = null;
            $table_name = null;
        }

        return [ $relation, $table_name, $field_name ];
    }

    /**
     * Return field parameters with existence checks.
     *
     * @param      $filterField
     *
     * @return array
     */
    public function getFieldParametersWithExistsCheck($filterField)
    {
        return $this->getFieldParameters($filterField, true);
    }

    /**
     * Start relation transition.
     *
     * @param  $query
     * @param  $request
     */
    public function startTransition($query = false, $request = false)
    {
        $this->transition['query'] = $this->query;
        $this->transition['request'] = $this->request;
        if ($query !== false) {
            $this->query = $query;
            $this->model = $query->getModel();
        }
        if ($request !== false) {
            $this->request = $request;
        }
    }

    /**
     * End relation transition.
     */
    public function stopTransition()
    {
        $this->request = $this->transition['request'];
        $this->query = $this->transition['query'];
        $this->model = $this->query->getModel();

    }

    /**
     * Set relation operation.
     *
     * @param Collection $conditions
     * @param            $relation
     * @param null       $query
     */
    protected function setRelationOperation(Collection $conditions, $relation, $query = null)
    {
        $this->setOperation($conditions, $relation, $query, true);
    }

    /**
     * Set operation on current table or relation.
     *
     * @param Collection $conditions
     * @param            $relation
     * @param            $query
     * @param bool       $isRelation
     */
    protected function setOperation(Collection $conditions, $relation = null, $query = null, $isRelation = false)
    {
        $query = $query ?: $this->query;

        if ( ! $isRelation) {
            foreach ($conditions as $condition) {
                (new SextantOperation($query, $this->getConditionFullPath($condition), $condition->value))->set();
            }
        } else {
            (new SextantRelationOperation($query, $relation, $conditions))->set();
        }
    }

    /**
     * Return qualified field name or simple field name by condition.
     *
     * @param $condition
     * @param bool $raw
     *
     * @return string
     */
    protected function getConditionFullPath($condition, $raw = false)
    {
        $delimiter = $raw ? '`' : '';

        if ( ! empty($condition->table)) {
            return sprintf('%s%s%s.%s%s%s', $delimiter, $condition->table, $delimiter, $delimiter, $condition->field, $delimiter);
        } elseif ( ! empty($condition->field)) {
            return sprintf('%s%s%s', $delimiter, $condition->field, $delimiter);
        }

        return null;
    }

    /**
     * Return strict field name without tables.
     *
     * @param $fieldName
     *
     * @return mixed
     */
    protected function getStrictFieldName($fieldName)
    {
        return \Arr::last(explode('.', $fieldName));
    }

    /**
     * Return condition parameters.
     *
     * @param $relation
     * @param $tableName
     * @param $fieldName
     * @param $condition
     *
     * @return object
     */
    protected function getConditionParameters($relation, $tableName, $fieldName, $condition)
    {
        return (object) [
            'relation' => $relation,
            'table'    => $tableName,
            'field'    => $fieldName,
            'value'    => $condition,
        ];
    }

    /**
     * Restrict operation conditions.
     *
     * @param Collection $conditions
     * @param            $restrictionKeyName
     *
     * @version 1.1.4
     *
     * @return Collection
     */
    protected function restrictConditions(Collection $conditions, $restrictionKeyName)
    {
        if (isset($this->restrictions['only']) && isset($this->restrictions['only'][ $restrictionKeyName ])) {
            return $conditions->intersect($this->restrictions['only'][ $restrictionKeyName ])->values();
        } elseif ( ! empty($this->restrictions['except']) && ! empty($this->restrictions['except'][ $restrictionKeyName ])) {
            return $conditions->diff($this->restrictions['except'][ $restrictionKeyName ])->values();
        }

        return $conditions;
    }

    /**
     * Check if actions on given relation(expand) are not restricted and relation accessible.
     *
     * @param $expandName
     * @param $restrictionRequestKeyName
     *
     * @return bool
     * @version 1.1.4
     */
    protected function relationEnabled($expandName, $restrictionRequestKeyName)
    {
        if($this->isRequestKeyExpandable($restrictionRequestKeyName)){
            $restrictionRequestKeyName = $this->expandRestrictionsRequestKey;
        }

        if(isset($this->restrictions['only']) && isset($this->restrictions['only'][ $restrictionRequestKeyName ])){
            // Check that expand isn't restricted by 'only' type.
            return in_array($expandName, $this->restrictions['only'][ $restrictionRequestKeyName ]);
        }elseif(! empty($this->restrictions['except']) && ! empty($this->restrictions['except'][ $restrictionRequestKeyName ])){
            // Check that expand isn't restricted by 'except' type.
            return ! in_array($expandName, $this->restrictions['except'][ $restrictionRequestKeyName ]);
        }

        return true;
    }

    /**
     * Check if given key expandable.
     *
     * @param $requestKey
     *
     * @return bool
     */
    protected function isRequestKeyExpandable($requestKey)
    {
        if(isset($this->isExpandable[$requestKey])){
            return $this->isExpandable[$requestKey];
        }

        $validSextantKey = collect(config('sextant.map', []))->filter(function($key) use($requestKey){ return $key == $requestKey; })->keys()->first();

        return $this->isExpandable[$requestKey] = in_array($validSextantKey, $this->expandableRequestKeys);
    }

}