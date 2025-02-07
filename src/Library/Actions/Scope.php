<?php

namespace Amondar\Sextant\Library\Actions;


use Amondar\Sextant\Contracts\SextantActionContract;
use Amondar\Sextant\Library\SextantCore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Scope
 *
 * @version 1.0.0
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class Scope extends SextantCore implements SextantActionContract
{

    /**
     * @var Collection
     */
    public $scopes = null;

    /**
     * @var string
     */
    protected $scopeRequestField;


    /**
     * Scope class constructor.
     *
     * @param Builder $query
     * @param         $requestField
     * @param Request $request
     * @param array   $restrictions
     * @param array   $params
     */
    public function __construct(Builder $query, $requestField, Request $request = null, $restrictions = [], $params = [])
    {
        parent::__construct($query, $query->getModel(), $request, $restrictions);
        $this->scopes = collect([]);
        $this->scopeRequestField = $requestField;
        $this->get($params);
    }

    /**
     * Set queries.
     *
     * @return mixed
     */
    public function set()
    {
        foreach ($this->scopes as $scope) {
            $operation = $this->getConditionParameters(null, null, null, [
                'operation'  => 'scope',
                'value'      => $scope['name'],
                'parameters' => $scope['parameters'],
            ]);

            $this->setOperation(collect([ $operation ]));
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
        if (isset($params[ $this->scopeRequestField ])) {
            $this->scopes = collect($params['scopes']);
        }

        if ($this->request && $this->request->has($this->scopeRequestField)) {
            $this->smartMerge($this->request->get($this->scopeRequestField));
        }
    }

    /**
     * Merge parameters.
     *
     * @param $scopes
     */
    protected function smartMerge($scopes)
    {
        $conditions = json_decode($scopes, true);
        if ($conditions) {
            foreach ($conditions as $condition) {
                $this->addCondition($condition);
            }
        }

        $this->scopes = $this->scopes->whereIn('name', $this->restrictConditions($this->scopes->pluck('name'), $this->scopeRequestField));
    }

    /**
     * Add condition to array.
     *
     * @param $condition
     */
    protected function addCondition($condition)
    {
        if (isset($condition['name']) && isset($condition['parameters']) && ! $this->scopes->contains('name', $condition['name'])) {
            $this->scopes->push($condition);
        }
    }
}