<?php

namespace Amondar\Sextant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Class HasSextantOperations
 *
 * @version 1.0.0
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
trait HasSextantOperations
{

    /**
     * Return array of expandable relation for remote api calls.
     *
     * @return array
     */
    public function extraFields()
    {
        return [];
    }

    /**
     * Return array of possible scopes for remote api calls.
     *
     * @return array
     */
    public function extraScopes()
    {
        return [];
    }

    /**
     * Run sextant operations.
     *
     * @param Builder $query
     * @param Request|null $request
     * @param array $params
     *
     * @param array $restrictions
     *
     * @return mixed
     */
    public function scopeWithSextant(
        $query,
        $request = NULL,
        $params = [],
        $restrictions = []
    )
    {
        foreach ( config('sextant.drivers') as $driver ) {
            if ( isset($driver[ 'class' ]) ) {
                ( new $driver[ 'class' ]($query, $driver[ 'request_key' ], $request, $restrictions, $params) )->set();
            } elseif ( isset($driver[ 0 ]) && isset($driver[ 1 ]) ) {
                $result = ( new $driver[ 0 ][ 'class' ]($query, $driver[ 0 ][ 'request_key' ], $request, $restrictions,
                    $params) )->setAsRelation($driver[ 1 ][ 'request_key' ], $params);
                ( new $driver[ 1 ][ 'class' ]($query, $driver[ 1 ][ 'request_key' ], $request, $restrictions,
                    $params) )->setAsRelation($result);
            }
        }

        return $query;
    }

    /**
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string                             $relation_name
     * @param string                             $sortColumn
     * @param string                             $operator
     * @param string                             $type
     * @param bool                               $where
     *
     * @return void
     */
    public function scopeModelJoin($query, $relation_name, $sortColumn, $operator = '=', $type = 'left', $where = false)
    {
        if ( Str::contains($relation_name, '.') ) {
            $relationParts = explode('.', $relation_name);
            $lastIndex = count($relationParts) - 1;
            $model = $this;
            foreach ( $relationParts as $index => $relation ) {
                $model = $this->simpleModelJoin($query, $model, $relation, $index == $lastIndex ? $sortColumn : NULL,
                    $operator, $type, $where);
            }
        } else {
            $this->simpleModelJoin($query, $this, $relation_name, $sortColumn, $operator, $type, $where);
        }
    }

    /**
     * Attach a simple join to model.
     *
     * @param        $query
     * @param Model  $model
     * @param        $relation_name
     * @param        $sortColumn
     * @param string $operator
     * @param string $type
     * @param bool   $where
     *
     * @return mixed
     */
    protected function simpleModelJoin(Builder $query, Model $model, $relation_name, $sortColumn, $operator = '=', $type = 'inner', $where = false)
    {
        /** @var Relation $relation */
        $relation = $model->$relation_name();
        $table = $relation->getRelated()->getTable();

        [ $one, $two, $isCrossTable ] = $this->checkCrossTableRelation($query, $relation, $operator, $type, $where);

        if ( empty($query->columns) ) {
            $query->select($this->getTable() . ".*");
        }

        if ( ! empty($sortColumn) ) {
            $asSortColumn = "$table.$sortColumn";
            $query->addSelect(\DB::raw("GROUP_CONCAT($asSortColumn, '+') as `$asSortColumn`"));
        }

        $query->join($table, $one, $operator, $two, $type, $where);

        // Group by parent table.
        $query->groupBy(sprintf('%s.%s', $this->getTable(), $this->getKeyName()));

        return $relation->getRelated();
    }

    /**
     * Проверяет реляцию на кросстабличность, например - много к многим, и добавляет промежуточную таблицу к джоину.
     * Возвращает конечные данные подключения джоина.
     *
     * @param $query
     * @param $relation
     * @param $operator
     * @param $type
     * @param $where
     *
     * @return array
     */
    protected function checkCrossTableRelation(Builder $query, Relation $relation, $operator, $type, $where)
    {
        // Check for cross table relation
        if ( method_exists($relation, 'getTable') ) {
            $three = $relation->getQualifiedParentKeyName();

            $four = $this->filterGetForeignKeyForPivot($relation);

            $query->join($relation->getTable(), $three, $operator, $four, $type, $where);

            $one = $relation->getRelated()->getTable() . '.' . $relation->getRelated()->getKeyName();

            $two = $this->filterGetOtherKeyForPivot($relation);

            return [ $one, $two, true ];
        }

        // Check for single way relations.
        if (
            method_exists($relation, 'getQualifiedOwnerKeyName') &&
            method_exists($relation, 'getQualifiedForeignKeyName')
        ) {
            $one = $relation->getQualifiedOwnerKeyName();
            $two = $relation->getQualifiedForeignKeyName();
        } elseif ( $relation instanceof HasManyThrough ) {
            $one = $relation->getQualifiedLocalKeyName();
            $two = $this->filterGetForeignKey($relation);
        } else {
            $one = $relation->getQualifiedParentKeyName();
            $two = $this->filterGetForeignKey($relation);
        }

        return [ $one, $two, false ];
    }

    /**
     * Return foreign key for filter PIVOT join.
     *
     * @param $relation
     *
     * @fix <= Laravel 5.4 capability
     *
     * @return mixed
     */
    protected function filterGetForeignKeyForPivot($relation)
    {
        if ( method_exists($relation, 'getQualifiedForeignPivotKeyName') ) {
            return $relation->getQualifiedForeignPivotKeyName();
        } elseif ( method_exists($relation, 'getQualifiedForeignKeyName') ) {
            return $relation->getQualifiedForeignKeyName();
        }
    }

    /**
     * Return foreign key for filter join.
     *
     * @param $relation
     *
     * @fix <= Laravel 5.4 capability
     *
     * @return mixed
     */
    protected function filterGetForeignKey($relation)
    {
        if ( method_exists($relation, 'getQualifiedForeignPivotKeyName') ) {
            return $relation->getQualifiedForeignPivotKeyName();
        } elseif ( method_exists($relation, 'getQualifiedForeignKeyName') ) {
            return $relation->getQualifiedForeignKeyName();
        }
    }

    /**
     * Return other key for pivot.
     *
     * @param $relation
     *
     * @fix <= Laravel 5.4 capability
     *
     * @return mixed
     */
    protected function filterGetOtherKeyForPivot($relation)
    {
        if ( method_exists($relation, 'getQualifiedRelatedPivotKeyName') ) {
            return $relation->getQualifiedRelatedPivotKeyName();
        }
    }

}