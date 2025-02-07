<?php

namespace Amondar\Sextant;

use Amondar\Sextant\Models\HasSextantOperations;
use Amondar\Sextant\Models\SextantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Class SextantWrapper
 *
 * @version 1.0.0
 * @date    2019-02-28
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class SextantWrapper
{

    use HasSextantOperations;

    /**
     * Filtrate model with wrapper.
     *
     * @param Model        $model
     * @param Request|null $request
     * @param array        $params
     *
     * @return Builder
     */
    public function filtrate(Model $model, Request $request = null, $params = [])
    {
        if (method_exists($model, 'scopeWithSextant')) {
            return $this->callFilterAsScope($model, $request, $params);
        } else {
            return $this->callFilterAsFunction($model, $request, $params);
        }
    }

    /**
     * Return current sextant config key name from map.
     *
     * @param $key
     *
     * @return string|null
     */
    public function getConfigRequestKey($key)
    {
        return config('sextant.map', [])[$key] ?? $key;
    }

    /**
     * Call filtration on model as scope.
     *
     * @param Model   $model
     * @param Request $request
     * @param         $params
     *
     * @return mixed
     */
    protected function callFilterAsScope(Model $model, Request $request, $params)
    {
        return $model->withSextant($request, $params);
    }

    /**
     * Model can be filtered by table only. Extra fields are empty.
     *
     * @param Model   $model
     * @param Request $request
     * @param         $params
     *
     * @return mixed
     */
    protected function callFilterAsFunction(Model $model, Request $request, $params)
    {
        $newModel = new SextantModel();
        $newModel->setTable($model->getTable());
        $newModel->setKeyName($model->getKeyName());
        $newModel->setKeyType($model->getKeyType());

        return $newModel->withSextant($request, $params);
    }
}