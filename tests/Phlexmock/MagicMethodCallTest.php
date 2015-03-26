<?php 
namespace PhlexMock;

use PHPUnit_Framework_TestCase as TestCase;

class MagicMethodCallTest extends TestCase 
{
    private $phlexmock;

    public function setUp()
    {
        $this->phlexmock = new \PhlexMock\PhlexMock();
        $this->phlexmock->setClassSearchPaths([__DIR__."/../TestClass"]);
        $this->phlexmock->start();
    }

    /**
     * test the magic method call __call
     */
    public function testDynamicMagicMethodCall()
    {
        $obj = new \TestClass\MethodMissingCatcher();
        $result = $obj->randomMethod();
        $this->assertEquals($result, "caught missing dynmaic method randomMethod");
    }

    /**
     * test the magic method call __callStatic 
     */
    public function testStaticMagicMethodCall()
    {
        $result = \TestClass\MethodMissingCatcher::randomStaticMethod();
        $this->assertEquals($result, "caught missing static method randomStaticMethod");
    }

    /**
     * test magic method call __get and __set 
     */
    public function testGetAndSet()
    {
        $obj = new \TestClass\Storage();
        $obj->value = 10;
        $this->assertEquals($obj->value, 10);
    }
}
