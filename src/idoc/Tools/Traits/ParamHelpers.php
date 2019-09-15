<?php

namespace OVAC\IDoc\Tools\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait ParamHelpers
{
    /**
     * Create proper arrays from dot-noted parameter names.
     *
     * @param array $params
     *
     * @return array
     */
    protected function cleanParams(array $params)
    {
        $values = [];
        foreach ($params as $name => $details) {
            $this->cleanValueFrom($name, $details['value'], $values);
        }

        return $values;
    }

    /**
     * Converts dot notation names to arrays and sets the value at the right depth.
     *
     * @param string $name
     * @param mixed $value
     * @param array $values The array that holds the result
     *
     * @return void
     */
    protected function cleanValueFrom($name, $value, array &$values = [])
    {
        if (Str::contains($name, '[')) {
            $name = str_replace(['][', '[', ']', '..'], ['.', '.', '', '.*.'], $name);
        }
        Arr::set($values, str_replace('.*', '.0', $name), $value);
    }
}
