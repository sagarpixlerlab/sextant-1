<?php

namespace Amondar\Sextant\Library\Operations;

use Amondar\Sextant\Library\SextantCore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class SextantOperation
 *
 * @version 1.0.0
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class SextantOperation extends SextantCore
{

    /**
     * @var Builder
     */
    public $query;

    /**
     * @var string
     */
    public $operation = '=';

    public $operationType = false;

    public $operationParameters = [];

    /**
     * Operation value.
     *
     * @var mixed
     */
    public $value;

    /**
     * Field name for operation.
     *
     * @var string
     */
    protected $field_name;

    /**
     * Possible operations.
     *
     * @var array
     */
    protected $allowedOperations = [ '=', '>', '<', '>=', '<=', '<>', 'not in', 'in', 'like', 'search', 'scope' ];

    protected $allowedDateOperations = [ '=', '>', '<', '>=', '<=', '<>' ];


    /**
     * FilterOperation class constructor.
     *
     * @param Builder $query
     * @param         $field_name
     * @param         $condition
     */
    public function __construct(Builder $query, $field_name = NULL, $condition)
    {
        $this->query = $query;
        $this->field_name = $field_name;
        $this->detectOperation($condition);
    }

    /**
     * Set queries.
     */
    public function set()
    {
        $this->addFilterOperation($this->query);
    }

    /**
     * Add filter operation queries.
     *
     * @param Builder $query
     *
     * @return mixed
     */
    protected function addFilterOperation(Builder &$query)
    {
        if ( isset($this->value[ 'isNull' ]) && is_bool($this->value[ 'isNull' ]) ) {
            $this->isNullOperation($query);
        } elseif ( $this->operationType == 'operation' && $this->isOperationValueAllowed($this->value) ) {
            $this->filterAllowedOperations($query);
        } elseif ( $this->operationType == 'date_range' ) {
            $this->filterByDateRange($query);
        } elseif ( $this->field_name ) {
            $query->where($this->field_name, $this->value);
        }

        return $query;
    }

    /**
     * Check if operation value is allowed
     *
     * @param $value
     *
     * @return bool
     */
    protected function isOperationValueAllowed($value)
    {
        return is_string($value) || is_numeric($value) || is_bool($value) || is_array($value);
    }

    /**
     * Return corrected operation for Eloquent filtration.
     *
     * @param $currentOperation
     *
     * @return string
     */
    protected function getOperationAfterCorrection($currentOperation)
    {
        if ( $currentOperation == '<>' ) {
            return '!=';
        }

        return $currentOperation;
    }

    /**
     * Filter by allowed operations.
     *
     * @param $query
     */
    protected function filterAllowedOperations(&$query)
    {
        switch ( $this->operation ) {
            case 'in':
                if ( is_array($this->value) ) {
                    $query->whereIn($this->field_name, $this->value);
                }
                break;
            case 'not in':
                if ( is_array($this->value) ) {
                    $query->whereNotIn($this->field_name, $this->value);
                }
                break;
            case 'like':
                if ( is_string($this->value) ) {
                    $searchString = addslashes(Str::lower($this->value));
                    $query->whereRaw("LOWER($this->field_name) like '%$searchString%'");
                }
                break;
            case 'search':
                if ( is_array($this->value) ) {
                    foreach ( $this->value as $searchString ) {
                        $searchString = addslashes(Str::lower($searchString));
                        $query->orWhereRaw("LOWER($this->field_name) like '%$searchString%'");
                    }
                }
                break;
            case 'scope':
                call_user_func_array(
                    [ $query, $this->value ],
                    is_array($this->operationParameters) ? $this->operationParameters : [ $this->operationParameters ]
                );
                break;
            default:
                $query->where($this->field_name, $this->operation, $this->getDateValue($this->value));

        }
    }

    /**
     * Filter by dates.
     *
     * @param Builder $query
     */
    protected function filterByDateRange(Builder &$query)
    {
        if ( $this->value[ 'from' ][ 'value' ] || $this->value[ 'from' ][ 'value' ] === 0 ) {
            $query->where($this->field_name, $this->value[ 'from' ][ 'operation' ], $this->value[ 'from' ][ 'value' ]);
        }

        if ( $this->value[ 'to' ][ 'value' ] || $this->value[ 'to' ][ 'value' ] === 0 ) {
            $query->where($this->field_name, $this->value[ 'to' ][ 'operation' ], $this->value[ 'to' ][ 'value' ]);
        }
    }

    /**
     * Filter by null operation.
     *
     * @param Builder $query
     */
    protected function isNullOperation(Builder &$query)
    {
        if ( isset($this->value[ 'isNull' ]) && $this->value[ 'isNull' ] === true ) {
            $query->whereNull($this->field_name);
        } elseif ( isset($this->value[ 'isNull' ]) && $this->value[ 'isNull' ] === false ) {
            $query->whereNotNull($this->field_name);
        }
    }


    /**
     * Detect filtration operations.
     *
     * @param      $condition
     * @param null $relation
     *
     * @return array
     */
    protected function detectOperation($condition, $relation = NULL)
    {
        $this->operation = '=';
        if ( isset($condition[ 'operation' ]) ) {
            $this->getValidOperationParameters($condition, $relation);
        } elseif ( isset($condition[ 'from' ]) || isset($condition[ 'to' ]) ) {
            $this->operationType = 'date_range';
            $this->value = [
                'from' => $this->getDateCondition(isset($condition[ 'from' ]) ? $condition[ 'from' ] : NULL, '>='),
                'to'   => $this->getDateCondition(isset($condition[ 'to' ]) ? $condition[ 'to' ] : NULL, '<=',
                    ' 23:59:59'),
            ];
        } else {
            $this->operationType = 'simple';
            $this->value = $condition;
        }

        return [
            'value'               => $this->value,
            'operation'           => $this->operation,
            'operationType'       => $this->operationType,
            'operationParameters' => $this->operationParameters,
        ];
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
        if ( in_array($operation, $this->allowedOperations) !== false && isset($condition[ 'value' ]) ) {
            switch ( $operation ) {
                case 'scope':
                    if (
                    $this->checkScopeExistence(
                        ! $relation ?
                            $this->query->getModel() :
                            $this->query->getModel()->$relation()->getModel(),
                        $condition[ 'value' ]
                    )
                    ) {
                        $this->operation = $operation;
                        $this->operationType = 'operation';
                        $this->value = $condition[ 'value' ];

                        if ( isset($condition[ 'parameters' ]) ) {
                            $this->operationParameters = $condition[ 'parameters' ];
                        }
                    }
                    break;
                default:
                    $this->operation = $this->getOperationAfterCorrection($operation);
                    $this->operationType = 'operation';
                    $this->value = $condition[ 'value' ];
            }
        }
    }

    /**
     * Check if scope exists in model class.
     *
     * @param Model $model
     * @param       $scope
     *
     * @return bool
     */
    protected function checkScopeExistence(Model $model, $scope)
    {
        if ( method_exists($model, 'extraScopes') ) {
            return in_array($scope, $model->extraScopes()) && (
                    method_exists($model, Str::camel('scope_' . $scope)) ||
                    in_array($scope, config('sextant.global_scopes', []))
                );
        } else {
            return false;
        }
    }

    /**
     * Return condition for date if it's possible.
     *
     * @param        $dateCondition
     * @param string $defaultOperation
     *
     * @param string $time
     *
     * @return array
     */
    protected function getDateCondition($dateCondition, $defaultOperation = '<=', $time = ' 00:00:00')
    {
        if ( is_array($dateCondition) ) {
            $operation = isset($dateCondition[ 'operation' ]) ? $dateCondition[ 'operation' ] : $defaultOperation;

            return [
                'value'     => $this->getDateValue(isset($dateCondition[ 'value' ]) ? $dateCondition[ 'value' ] : NULL,
                    $time),
                'operation' => $this->checkDateOperation($operation) ? $operation : $defaultOperation,
            ];
        } else {
            return [
                'value'     => $this->getDateValue($dateCondition, $time),
                'operation' => $defaultOperation,
            ];
        }
    }

    /**
     * Проверяет операцию для даты на существование.
     *
     * @param $operation
     *
     * @return bool
     */
    protected function checkDateOperation($operation)
    {
        return in_array($operation, $this->allowedDateOperations);
    }

    /**
     * Return date in defined format.
     *
     * @param $value
     * @param $time
     *
     * @return string
     */
    protected function getDateValue($value, $time = false)
    {
        if (
            ! in_array($this->getStrictFieldName($this->field_name), $this->query->getModel()->getDates()) ||
            empty($value)
        ) {
            return $value;
        }

        try {
            $timeExists = $this->checkIfTimeNeeded($value);
            $timeOnly = $this->checkIfTimeOnly($value);
            $date = Carbon::parse((string) $value);

            if ( $timeOnly ) {
                return $date->toTimeString();
            } elseif ( $timeExists || $time ) {
                if ( ! $timeExists && $time ) {
                    $date->setTimeFromTimeString($time);
                }

                return $date->toDateTimeString();
            } else {
                return $date->toDateString();
            }
        } catch ( \Exception $e ) {
            return $value;
        }
    }

    /**
     * Determine if date need time expression.
     *
     * @param $date
     *
     * @return int
     */
    protected function checkIfTimeNeeded($date)
    {
        return preg_match('/\s(\d{2}):(\d{2})(:\d{2})?$/', $date);
    }


    /**
     * Determine if date need time expression.
     *
     * @param $date
     *
     * @return int
     */
    protected function checkIfTimeOnly($date)
    {
        return preg_match('/^(\d{2}):(\d{2})(:\d{2})?$/', $date);
    }

}
