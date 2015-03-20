# phlexmock
A tool to allow developers to redefining PHP class methods for testing purposes.

##Installation 
Add phlexmock to your composer.json 
```
{
    "require-dev": {
        "jimthunderbird/phlexmock": "*"
    }
}
```

##Basic Examples 

Let's say we have a class named User.php in our current path and it looks like the following:
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

And if we do:
```php 
$user = new \User("a-user");
$user->ok();
$user->info();
```

We will be seeing all the default messages for each method.

Now let's use PhlexMock to reopen the methods.

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
$user->info();

\User::phlexmockMethod('info', function(){
    echo "Reopened information";
});

$user->info();
```

Now we will be seeing all the output from the reopened methods! This should allow us to easily modify or mock any classes for testing.
