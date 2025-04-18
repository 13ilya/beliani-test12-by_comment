<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 27.09.2017
 * Time: 9:50
 */

class ModelNotFoundException extends RuntimeException
{
    protected $modelClassName;
    protected $conditions;

    /**
     * ModelNotFoundException constructor.
     *
     * @param string          $modelClassName
     * @param array           $conditions
     * @param \Exception|null $previous
     */
    public function __construct($modelClassName, $conditions, \Exception $previous = null)
    {
        $this->modelClassName = $modelClassName;
        $this->conditions = $conditions;

        $message = "{$modelClassName} not found by the conditions: " . print_r($conditions, true);
        $code = 0;
        parent::__construct($message, $code, $previous);
    }

    /**
     * get class name
     * @return string
     */
    public function getModelClassName()
    {
        return $this->modelClassName;
    }

    /**
     * get conditions
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }
}