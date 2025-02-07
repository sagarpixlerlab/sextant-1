<?php

namespace Amondar\Sextant;

use Illuminate\Support\Facades\Facade as BaseFacade;

/**
 * Class Facade
 *
 * @version 1.0.0
 * @date    2019-03-01
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class Facade extends BaseFacade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        return 'sextant';
    }
}