<?php
namespace label\DataValue\Exception\Property;
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
class ReadOnly extends \Exception
{
    public function __construct($message = null, $code = null, \Exception $previous = null)
    {
        parent::__construct("This property is read-only.", $code, $previous);
    }

}
