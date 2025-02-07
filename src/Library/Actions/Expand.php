<?php

namespace Amondar\Sextant\Library\Actions;


use Amondar\Sextant\Contracts\SextantActionContract;
use Amondar\Sextant\Library\SextantCore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Expand
 *
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class Expand extends SextantCore implements SextantActionContract
{
    /**
     * @var Collection
     */
    public $expands = null;

    /**
     * @var string
     */
    protected $expandRequestField;


    /**
     * Expand class constructor.
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
        $this->expands = collect([]);
        $this->expandRequestField = $requestField;
        $this->get($params);
    }

    /**
     * Set queries.
     *
     * @return mixed
     */
    public function set()
    {
        if ( ! $this->expands->isEmpty() ) {
            $this->query->with($expands = $this->expands->diff(array_keys($this->query->getEagerLoads()))->all());

            return collect($expands);
        }

        return $this->expands;
    }

    /**
     * Get filtration parameters.
     *
     * @param array $params
     *
     * @return void
     */
    protected function get($params = [ ])
    {
        if(isset($params[$this->expandRequestField])){
            $this->expands = collect(array_intersect($this->model->extraFields(), explode(',', $params[$this->expandRequestField])));
        }

        if ($this->request && $this->request->has($this->expandRequestField)) {
            $this->expands = $this->expands->merge(array_intersect($this->model->extraFields(), explode(',', $this->request->get($this->expandRequestField))));
        }

        // Restrict conditions to prevent using disabled.
        $this->expands = $this->restrictConditions($this->expands, $this->expandRequestField);
    }
}