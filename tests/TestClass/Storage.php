<?php 
namespace TestClass;

class Storage
{
    private $value;
    private $hash = [];

    public function __set($key, $val)
    {
        $this->hash[$key] = $val;
    }

    public function __get($key)
    {
        return $this->hash[$key];
    }
}
