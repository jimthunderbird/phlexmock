<?php 

namespace TestClass;

class MethodMissingCatcher 
{
    public function __call($name, $args)
    {
        return "caught missing dynmaic method $name";
    }

    public static function __callStatic($name, $args)
    {
        return "caught missing static method $name";
    }

}
