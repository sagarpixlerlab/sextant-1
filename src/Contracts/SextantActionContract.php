<?php

namespace Amondar\Sextant\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Interface FilterActionContract
 *
 * @version 1.0.0
 * @date    2019-02-28
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
interface SextantActionContract
{

    /**
     * FilterActionContract constructor.
     *
     * @param Builder      $query
     * @param string       $requestField
     * @param Request|null $request
     * @param array        $restrictions
     * @param array        $params
     */
    public function __construct(Builder $query,  $requestField, Request $request = null, $restrictions = [], $params = []);

    /**
     * Set action parameters into query.
     *
     * @return mixed
     */
    public function set();
}