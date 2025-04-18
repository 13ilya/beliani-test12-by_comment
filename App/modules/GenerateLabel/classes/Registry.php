<?php

namespace label;

use label\Registry\InterfaceClass;

/**
 * {license_notice}
 *
 * @copyright   baidush.k
 * @license     {license_link}
 */
class Registry implements InterfaceClass
{
    /** @var  Registry [] */
    private $container = array();

    public function set($key, $value)
    {
        if (!isset($this->container[$key]))
            $this->container[$key] = $value;
        else
            trigger_error('Variable ' . $key . ' already defined', E_USER_WARNING);
    }

    public function get($key)
    {
        return $this->container[$key];
    }

    public function getAll()
    {
        return $this->container;
    }

    private function __clone()
    {
    }

}
