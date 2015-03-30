<?php 
namespace PhlexMock;

use PHPUnit_Framework_TestCase as TestCase;

class MethodHashResetTest extends TestCase 
{
    private $phlexmock;

    public function setUp()
    {
        $this->phlexmock = new \PhlexMock\PhlexMock();
        $this->phlexmock->setClassSearchPaths([__DIR__."/../TestClass"]);
        $this->phlexmock->start();
        $this->phlexmock->reset();
    }

    public function testResetAll()
    {
        \TestClass\Point::phlexmockMethod('getX', function(){
            return 2 * $this->x;
        });
        \TestClass\Point::phlexmockMethod('getY', function(){
            return 2 * $this->y;
        });
        $obj = new \TestClass\Point(2,3);
        $this->assertEquals($obj->getX(), 4);
        $this->assertEquals($obj->getY(), 6);

        //now reset it 
        $this->phlexmock->reset();
        $this->assertEquals($obj->getX(), 2);
        $this->assertEquals($obj->getY(), 3);
    } 
}
