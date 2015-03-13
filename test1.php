<?php 

require_once __DIR__.'/vendor/autoload.php';

$phlexMock = new PhlexMock\PhlexMock();

$phlexMock->addClassSearchPath(__DIR__);
$phlexMock->setClassExtension(['class.php','php']);

$phlexMock->start();

$user = new \User();
