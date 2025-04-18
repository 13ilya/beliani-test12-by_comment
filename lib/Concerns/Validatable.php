<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 26.09.2017
 * Time: 13:45
 */

namespace Model\Concerns;


trait Validatable
{
    /**
     * @throws \ModelValidationException When the filled data is not valid
     */
    public function validation()
    {
    }
}