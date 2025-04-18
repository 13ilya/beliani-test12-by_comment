<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 26.09.2017
 * Time: 18:33
 */

namespace Model\Concerns;

require_once __DIR__ . '/Serializable.php';
require_once __DIR__ . '/Validatable.php';
require_once __DIR__ . '/Collection.php';

use CoreModel;

abstract class ExtendedModel extends CoreModel
{
    use Serializable;
    use Validatable;

    /**
     * {@inheritdoc}
     */
    protected static function findBySql($sql)
    {
        return new Collection(parent::findBySql($sql));
    }

    /**
     * @param string $field
     *
     * @return mixed
     */
    public function __get($field)
    {
        return $this->get($field);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function __set($field, $value)
    {
        return $this->set($field, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function fill($data, $convertNullableToNull = true)
    {
        $data = $this->castTypes($data);
        parent::fill($data, $convertNullableToNull);
    }

    /**
     * Cast types in the data.
     *
     * @param array $data
     *
     * @return array
     */
    protected function castTypes($data)
    {
        return (array) $data;
    }

    /**
     * Deletes all foreign models and then delete the current model.
     */
    public function deleteWithForeignModels()
    {
        $this->delete();
    }
}