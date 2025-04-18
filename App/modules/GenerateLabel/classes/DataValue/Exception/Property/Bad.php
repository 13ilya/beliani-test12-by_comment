<?php
namespace label\DataValue\Exception\Property;

/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
class Bad extends \Exception
{
    public function __construct($message = null, $code = null, \Exception $previous = null)
    {
        parent::__construct("This property do not exist.", $code, $previous);
    }
}
