<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 26.09.2017
 * Time: 13:43
 */

namespace Model\Concerns;

trait Serializable
{
    /**
     * @param array $options
     *
     * @return array
     */
    public function toArray($options = [])
    {
        $result = (array) $this->getData();

        if (isset($options['with'])) {
            foreach ((array) $options['with'] as $with) {
                list($relationName, $nestedRelations) = explode('.', $with, 2);
                $methodName = 'get' . ucfirst($relationName);
                if (method_exists($this, $methodName)) {
                    $relations = $this->{$methodName}();
                    $relationsResult = [];
                    foreach ($relations as $relation) {
                        $relationsResult[] = $relation->toArray([
                            'with' => $nestedRelations,
                        ]);
                    }
                    $result[$relationName] = $relationsResult;
                }
            }
        }

        return $result;
    }
}
