# phlexmock
A tool to allow developers to redefining PHP class methods for testing purposes.

##Installation 
1. Install composer
2. git clone https://github.com/jimthunderbird/phlexmock.git
3. cd phlexmock 
4. composer.phar install 

##Basic Examples 
```php 
<?php 
require_once __DIR__."/vendor/autoload.php";

$phlexmock = new \PhlexMock\PhlexMock();
$phlexmock->setClassSearchPaths([__DIR__]); #search the current directory for classes
$phlexmock->start();

/**
 * reopen constructor
 */
\User::phlexmockInstanceMethod('__construct', function($name){
    echo "user constructor is called\n";
});

/**
 * reopen method ok
 */
\User::phlexmockInstanceMethod('ok', function(){
    echo "user ok";
});

$user = new \User("a-user");
$user->ok();
$user->info();

\User::phlexmockInstanceMethod('info', function(){
    echo "This is user object";
});

$user->info();
```
