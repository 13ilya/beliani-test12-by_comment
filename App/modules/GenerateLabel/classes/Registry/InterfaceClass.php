<?php

namespace label\Registry;

/**
 * {license_notice}
 *
 * @copyright   baidush.k
 * @license     {license_link}
 */
interface InterfaceClass
{
    /**
     * @param string $key
     * @param string $value
     * @return void
     * @throws Exception
     */
    public function set($key, $value);

    /**
     * @param string $key , string $value
     * @return void
     */
    public function get($key);
}
