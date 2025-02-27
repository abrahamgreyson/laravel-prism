<?php

namespace Abe\Prism\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Abe\Prism\Prism
 */
class Prism extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Abe\Prism\Prism::class;
    }
}
