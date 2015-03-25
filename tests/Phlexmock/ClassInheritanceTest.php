<?php 
namespace PhlexMock;

use PHPUnit_Framework_TestCase as TestCase;

class ClassInheritanceTest extends TestCase 
{
    private $phlexmock;

    public function setUp()
    {
        $this->phlexmock = new \PhlexMock\PhlexMock();
        $this->phlexmock->setClassSearchPaths([__DIR__."/../TestClass"]);
        $this->phlexmock->start();
    }

    public function testConstructorInParent()
    {
        //reopen parent class's constructor 
        \TestClass\Shape::phlexmockMethod('__construct', function(){
            self::$currentClass = 'Shape';
        });

        \TestClass\Shape::phlexmockMethod('getCurrentClass', function(){
            return self::$currentClass;
        });

        $obj = new \TestClass\Circle();

        $this->assertEquals($obj->getCurrentClass(), 'Shape');

    }
}
