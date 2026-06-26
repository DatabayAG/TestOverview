<?php

class ilTestOverviewDataPermissionsIndex implements ArrayAccess
{
    private array $data = [];

    public function offsetExists($offset) : bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function offsetSet($offset, $value) : void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @return int[]
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

}
