<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 28.09.2017
 * Time: 18:23
 */

namespace Model\Concerns;

use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;

class Collection implements IteratorAggregate, ArrayAccess
{
    /**
     * @var object[]
     */
    protected $items;

    /**
     * Collection constructor.
     *
     * @param object[] $items
     */
    public function __construct($items = array())
    {
        $this->items = $items;
    }

    /**
     * Returns the iterator by the objects.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param string $field
     * @param string|null $glue
     *
     * @return string
     */
    public function implode($field, $glue = null)
    {
        $values = array_map(
            function($object) use ($field) {
                return $object->{$field};
            },
            $this->items
        );

        return implode($glue, $values);
    }

    /**
     * Returns a list of the given key / value pairs from the objects.
     * You may also specify how you wish the resulting list to be keyed.
     *
     * @param string $field
     * @param string|null $fieldKey
     *
     * @return array
     */
    public function pluck($field, $fieldKey = null)
    {
        $result = [];
        foreach ($this->items as $objectNumber => $object) {
            $key = is_null($fieldKey) ? $objectNumber : $object->{$fieldKey};
            $value = $object->{$field};
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Get an item from the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }
        return $default;
    }

    /**
     * Get the first item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        $array = $this->items;

        if (is_null($callback)) {
            if (empty($array)) {
                return $default;
            }
            foreach ($array as $item) {
                return $item;
            }
        }
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof Serializable) {
                return $value->toArray();
            } else {
                return $value;
            }
        }, $this->items);
    }
}