<?php

namespace Amondar\Sextant\Library\Operations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Class SextantRelationOperation
 *
 * @version 1.0.0
 * @date    13.03.17
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class SextantRelationOperation extends SextantOperation
{

    const OPERATION_NEGATIVE_CORRECTION        = 1;
    const OPERATION_SEARCH_CORRECTION          = 2;
    const OPERATION_NEGATIVE_SEARCH_CORRECTION = 3;

    /**
     * Реляция для операции.
     *
     * @var null
     */
    protected $relation;

    /**
     * @var bool
     */
    public $globalCorrection = false;

    /**
     * @var Collection
     */
    protected $conditions;

    protected $globalConditions;

    protected $negativeOperationsMap = [
        '<>'     => '=',
        '!='     => '=',
        'not in' => 'in',
    ];

    /**
     * Exists operation parameters.
     *
     * @var string
     */
    protected $hasOperationCondition = '>=';

    protected $hasOperationValue = 1;

    protected $globalRelationOperations = [ 'has', 'doesnthave' ];

    /**
     * FilterRelationOperation class constructor.
     *
     * @param Builder    $query
     * @param            $relation
     * @param            $conditions
     */
    public function __construct(Builder $query, $relation, $conditions)
    {
        // Extend relations operations.
        $this->allowedOperations = array_merge($this->allowedOperations, $this->globalRelationOperations);

        $this->query = $query;
        $this->relation = $relation;
        $this->detectRelationOperations($conditions);
    }


    /**
     * Set queries.
     *
     * @return void
     */
    public function set()
    {
        if ( $this->conditions->count() || $this->globalConditions->count() ) {
            switch ( $this->globalCorrection ) {
                case static::OPERATION_NEGATIVE_CORRECTION:
                    $this->query->whereDoesntHave($this->relation, function ($qu) {
                        $this->setRelationFilter($qu);
                    });
                    break;
                case static::OPERATION_SEARCH_CORRECTION:
                    $this->query->orWhereHas($this->relation, function ($qu) {
                        $qu->where(function ($q) {
                            $this->setRelationFilter($q);
                        });
                    });
                    break;
                case static::OPERATION_NEGATIVE_SEARCH_CORRECTION:
                    $this->query->orWhereDoesntHave($this->relation, function ($qu) {
                        $qu->where(function ($q) {
                            $this->setRelationFilter($q);
                        });
                    });
                    break;
                default:
                    $this->query->whereHas($this->relation, function ($qu) {
                        $this->setRelationFilter($qu);
                    }, $this->hasOperationCondition, $this->hasOperationValue);
            }
        }
    }

    /**
     * Set relation filter operations.
     *
     * @param $query
     */
    protected function setRelationFilter($query)
    {
        foreach ( $this->conditions as $condition ) {
            $this->setFilterOperationCLassEnvironment($condition);
            $this->addFilterOperation($query);
        }
    }

    /**
     * Set class environment.
     *
     * @param $environment
     */
    protected function setFilterOperationCLassEnvironment($environment)
    {
        $this->value = $environment->value;
        $this->field_name = $environment->field_name;
        $this->operation = $environment->operation;
        $this->operationType = $environment->operationType;
        $this->operationParameters = $environment->operationParameters;
    }

    /**
     * Detect relation operations.
     *
     * @param $conditions
     */
    protected function detectRelationOperations($conditions)
    {
        $this->conditions = collect([]);
        foreach ( $conditions as $condition ) {
            $this->conditions->push((object) array_merge([
                'field_name' => $this->getConditionFullPath($condition),
            ], $this->detectOperation($condition->value, $this->relation)));
        }

        $this->conditions = $this->processNegativeCorrection($this->conditions);

        //check for search correction.
        if ( $this->checkForSearchCorrection($this->conditions) ) {
            $this->globalCorrection = $this->globalCorrection == static::OPERATION_NEGATIVE_CORRECTION ?
                static::OPERATION_NEGATIVE_SEARCH_CORRECTION : static::OPERATION_SEARCH_CORRECTION;
        }

        $this->globalConditions = $this->conditions->where('field_name', '=', NULL);
        $this->conditions = $this->conditions->where('field_name', '!=', NULL)
                                             ->whereNotIn('operation', $this->globalRelationOperations);
    }

    /**
     * Return processed negative operations.
     *
     * @param \Illuminate\Support\Collection $conditions
     *
     * @return \Illuminate\Support\Collection
     */
    protected function processNegativeCorrection(Collection $conditions)
    {
        foreach ( $conditions as $index => $condition ) {
            if ( Arr::has($this->negativeOperationsMap, $condition->operation)){
                $conditions[$index]->operation = $this->negativeOperationsMap[$condition->operation];

                $this->globalCorrection = static::OPERATION_NEGATIVE_CORRECTION;
            }
        }

        return $conditions;
    }

    /**
     * Check if we need to set search correction.
     *
     * @param Collection $conditions
     *
     * @return bool
     */
    protected function checkForSearchCorrection(Collection $conditions)
    {
        $count = $conditions->count();
        $checker = 0;
        foreach ( $conditions as $condition ) {
            if ( $condition->operation == 'search' ) {
                $checker++;
            }
        }

        return $count == $checker;
    }

    /**
     * Return valid operation parameters.
     *
     * @param      $condition
     * @param null $relation
     */
    protected function getValidOperationParameters($condition, $relation = NULL)
    {
        $operation = strtolower($condition[ 'operation' ]);
        if ( in_array($operation, $this->allowedOperations) !== false ) {
            switch ( $operation ) {
                // Possible only on
                case 'doesnthave':
                    $this->operation = $operation;
                    $this->globalCorrection = self::OPERATION_NEGATIVE_CORRECTION;
                    break;
                case 'has':
                    $this->operation = $operation;
                    $this->hasOperationValue = $condition[ 'value' ] ?? $this->hasOperationValue;
                    if ( isset($condition[ 'condition' ]) && $this->checkDateOperation($condition[ 'condition' ]) ) {
                        $this->hasOperationCondition = $this->getOperationAfterCorrection($condition[ 'condition' ]);
                    }
                    break;
            }

            parent::getValidOperationParameters($condition, $relation);
        }
    }

}