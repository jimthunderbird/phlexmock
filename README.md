# phlexmock
A tool to allow developers to redefine PHP class methods for testing purposes.

Traditionally when we do unit test in PHP using tools like PHPUnit, we would need to create mock classes or mock objects
in order to simulate the testing context. Since PHP, unlike other scripting languages for example Ruby, does not support method reopenning, 
this makes the object and class mocking a bit cumbersome. Phlexmock aims to bring the method reopenning power to PHP.

Phlexmock uses the power of spl_autoload_register function in PHP together with static code analysis tool PHP-Parser to achieve the method reopenning feature.

Warning: While method reopening is great, we recommend using phlexmock for unit testing and mocking only, using in production code will have a performance hit.

##Installation 
Add phlexmock to your composer.json 
```
{
    "require-dev": {
        "jimthunderbird/phlexmock": "*"
    }
}
```

##Examples 

+ [Reopenning methods in a class](#example-01)
+ [Use this keyword in the reopened methods](#example-02)

###Example 01 
####Reopenning methods in a class
####Let's say we have a class named User.php in our current path and it looks like the following:
```php 
<?php 
class User 
{
    public function __construct($name)
    {
        echo "Default message for user constructor\n";
    }

    public function ok()
    {
        echo "Default message for ok method\n";
    }

    public function info()
    {
        echo "Default message for information\n";
    }
}
```

####And if we do:
```php 
$user = new \User("a-user");
$user->ok();
$user->info();
```
####We will be seeing all the default messages for each method.

####Now let's use PhlexMock to reopen the methods.

```php 
<?php 
require_once __DIR__."/vendor/autoload.php";

$phlexmock = new \PhlexMock\PhlexMock();
$phlexmock->setClassSearchPaths([__DIR__]); #search the current directory for classes
$phlexmock->start();

/**
 * reopen constructor
 */
\User::phlexmockMethod('__construct', function($name){
    echo "Reopened constructor\n";
});

/**
 * reopen method ok
 */
\User::phlexmockMethod('ok', function(){
    echo "Reopened ok method\n";
});

$user = new \User("a-user");
$user->ok();

\User::phlexmockMethod('info', function(){
    echo "Reopened information";
});

$user->info();
```

####Now we will be seeing all the output from the reopened methods! This should allow us to easily modify or mock any classes for testing. 

###Example 02 
####Using this keyword in the reopened methods

####Let's say we have a class named Circle.php in our current directory and it looks like the following:
```php 
class Circle 
{
    private $x;
    private $y;

    public function setX($x)
    {
        $this->x = $x; 
    }

    public function getX()
    {
        return $this->x;
    }
}
```

####And we would like to reopen the setX method in Circle class so that the instance property x will be doulbe of the value of the x value passed in, aka
```php 
$this->x = 2 * $x
```

####Let's do that with phlexmock 
```php 
require_once __DIR__."/vendor/autoload.php";

$phlexmock = new \PhlexMock\PhlexMock();
$phlexmock->setClassSearchPaths([__DIR__]);
$phlexmock->start();

\Circle::phlexmockMethod('setX', function($x){
    $this->x = 2 * $x;
});

$c = new \Circle();
$c->setX(2);
echo $c->getX();
``` 

####Now the setX method is reopened and in the code above, we will be seeing 4 printed on the screen. 
