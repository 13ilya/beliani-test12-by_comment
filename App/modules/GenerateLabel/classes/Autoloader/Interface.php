<?php

/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
interface Autoloader_Interface
{
    /**
     * @param string $className
     * @return boolean
     * @throws Exception
     */
    public function loadClass($className);
}
