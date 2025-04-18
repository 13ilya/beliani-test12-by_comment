<?php
namespace label\DataValue\Property;
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
interface PropertyInterface
{

    /**
     * @return mixed
     */
    public function getValue();

    /**
     * @param mixed $value
     * @return DataValue_Property_PropertyInterface
     */
    public function setValue($value);


    /**
     * @return DataValue_Property_PropertyInterface
     */
    public function setReadOnly();

    /** @return  boolean */
    public function isValueSet();

    /**
     * @return DataValue_Property_PropertyInterface
     */
    public function setRequired();

    /** @return string */
    public function getPropertyName();
}
