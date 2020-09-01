<?php

declare(strict_types=1);

use TTBooking\ClassFactory\ClassFactoryException;
use TTBooking\ClassFactory\GenericClass;

if (! function_exists('new_class')) {
    /**
     * Create new class.
     *
     * @param string ...$dependencies
     *
     * @throws ClassFactoryException
     *
     * @return GenericClass
     */
    function new_class(string ...$dependencies): GenericClass
    {
        return new GenericClass(...$dependencies);
    }
}
