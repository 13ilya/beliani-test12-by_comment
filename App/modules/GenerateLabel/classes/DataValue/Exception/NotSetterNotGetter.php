<?php
namespace label\DataValue\Exception;
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
class NotSetterNotGetter extends \Exception
{
    public function __construct($message = null, $code = null, Exception $previous = null)
    {
        parent::__construct("You can use only set or get methods.", $code, $previous);
    }

}
