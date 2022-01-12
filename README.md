# PHP Action Hooks

[![Latest Version on Packagist](https://img.shields.io/packagist/v/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)
[![Tests](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)

Inspired by WordPress, action hooks are functions that you define to be triggered in specific places of your code, it helps you maintain an organized code by slicing giant code blocks into separated files and/or classes. 

<a name="installation"></a>
## Installation

You can install the package via composer:

```bash
composer require magdicom/hooks
```

<a name="usage"></a>
## Usage

<a name="quick-start"></a>
### Quick Start
```php
use Magdicom\Hooks;

$hooks = new Hooks();

# Register our functions
$hooks->register("Greetings", function($vars){
    return "Hi There,";
}, 1);

$hooks->register("Greetings", function($vars){
    return "This is the second line of greetings!";
}, 2);

# Later we run it
echo $hooks->all("Greetings")->toString("<br>");
```
The above example will output
```text
Hi There,
This is the second line of greetings!
```

<a name="output"></a>
### Output
When you call any of [`all`](#all), [`first`](#first) or [`last`](#last) methods, the corresponding hook functions will be executed and their output will be saved in a special property to be exported later using [`toString`](#tostring) or [`toArray`](#toarray) methods.

<a name="callbacks"></a>
### Callbacks

<a name="callbacks-closure"></a>
#### Closure
```php
$hooks->register("Callback", function($vars) {
    return "Closure";
});
```

<a name="callbacks-function-name"></a>
#### Function Name
```php
function simple_function_name($vars){
    //
}

$hooks->register("Callback", "simple_function_name");
```

<a name="callbacks-object-method"></a>
#### Object Method
```php
class FooBar {
    public function methodName($vars){
        //
    }
}

$object = new FooBar;

$hooks->register("Callback", [$object, 'methodName']);
```
or
```php
$hooks->register("Callback", [(new FooBar), 'methodName']);
```

<a name="callbacks-static-method"></a>
#### Static Method
```php
class FooBar {
    public static function staticMethodName($vars){
        //
    }
}

$hooks->register("Callback", ['FooBar', 'staticMethodName']);
```
in case this is not a static method, an object will be created and the provided method will be called.

<a name="parameters"></a>
### Parameters

With each of hook callback functions execution an array of parameters could be passed to it to help it perform the required action.

#### Global Parameters

Global parameters could be defined using [`setParameter`](#setparameter) and [`setParameters`](#setparameters) methods and these parameters will be available across all hook points and callbacks.

#### Scoped parameters

The opposite of global parameters, the scoped parameters only available for the specified action hook point, and could be provided as the second argument of [`all`](#all), [`first`](#first) and [`last`](#last) methods.

When you provide it as an array, the values of scoped parameters will temporarily replace (similar key entries) global parameters and passed to the [`register`](#register) methods' callback as a merged array.

When the parameter provided as a class object it will be accessible from the [`register`](#register) method as the first argument, and the global parameters will be accessible via the second argument.

```php
class FooBarBaz {
    public $id;

    public function __construct(int $id){
        $this->id = $id;
    }
}

$hooks = new Hooks();

$hooks->setParameters([
    "name" => "Bar",
]);

$hooks->register("ParameterAsObject", function ($fooBarBaz, $params) {
    return [$fooBarBaz->id, $params['name']];
});

echo $hooks->all("ParameterAsObject", (new FooBarBaz(100))->toString("\n");

// Output will be
100
Bar
```

<a name="priority"></a>
### Priority
When you need to ensure that certain hook functions should be executed in sequence order, here it comes `$priority` which is the 3rd and last argument of [`register`](#register) method.

<a name="methods"></a>
### Methods

<a name="construct"></a>
#### __construct
```php
$hooks = new Hooks(?array $parameters);
```
The class constructor method will optionally accept a name, value pair array.

<a name="register"></a>
#### register
```php
$hooks->register(string $hookName, array|callable $callback, ?int $priority): self
```
Register all your hook functions via this method:

+ `$hookName` this can be anything you want, its like a group name where all other related action hook functions will be attached to.
+ `$callback` only accepts [callable](https://www.php.net/manual/en/language.types.callable.php) functions.
+ `$priority` (optional) used to sort callbacks before being executed. 

<a name="all"></a>
#### all
```php
$hooks->all(string $hookName, array|object|null $parameters): self
```
Will execute all callback functions of the specified hook name, by default it will return the output as string, check [output](#output) section for more options.
+ `$hookName` the hook name you want to execute its callback functions.
+ `$parameters` optional key, value pair array (or object) that you want to provide for all callback functions related to the same hook point.

Please Note: parameters provided via this method will be available only in the scope of the specified hook point, to specify global parameters use [`setParameter`](#setparameter), [`setParameters`](#setparameters) methods instead.

<a name="first"></a>
#### first
```php
$hooks->first(string $hookName, array|object|null $parameters): self
```
Similar to [`all`](#all) method in every aspect with the exception that only the first callback (after sorting) will be executed.

<a name="last"></a>
#### last
```php
$hooks->last(string $hookName, array|object|null $parameters): self
```
Similar to [`all`](#all) method in every aspect with the exception that only the last callback (after sorting) will be executed.

<a name="toarray"></a>
#### toArray
```php
$hooks->toArray(): array
```
Will return output of the last executed hook name functions as an array.

<a name="tostring"></a>
#### toString
```php
$hooks->toString(?string $separator): string
```
Will return output of the last executed hook name functions as one string.
+ `$separator` could be used to separate the output as you need (e.g: "\n", "&lt;br&gt;"). 

<a name="setparameter"></a>
#### setParameter
```php
$hooks->setParameter(string $name, mixed $value): self
```
Use this method to define a parameter that will be accessible from any hook function.
+ `$name` name of the parameter.
+ `$value` value of the parameter could be string, array or even an object.

P.S: if the parameter already defined then its old value will be replaced by the value provided here.

<a name="setparameters"></a>
#### setParameters
```php
$hooks->setParameters(array $parameters): self
```
Same as [`setParameter`](#setparameter) but here it accepts a name, value pair array as its only argument.

<a name="setSourceFile"></a>
#### setSourceFile
```php
$hooks->setSourceFile(?string $path): self
```
Used in conjunction with the [`debug`](#debug) method.

<a name="debug"></a>
#### debug
```php
$hooks->debug(callable|null $callback): self
```
To enable the debug feature you need to call this method by providing a callback function, this function should accept a single argument that will be the debug info/message.

```php
$hooks->debug(function($message){
    // Will print debug message(s)
    echo $message . PHP_EOL;
});

$hooks->setSourceFile("/path/to/file/filename.php");

$hooks->register("Greetings", "FooBar::log");

$hooks->all("Greetings");
```

will output

```text
+ Added Source File: /path/to/file/filename.php
+ Hook Point: Greetings, New Callback Defined:
        -- Source: /path/to/file/filename.php
        -- Callback: FooBar::log
        -- Priority: 1
+ Hook Point: Greetings, Callback Functions Sorted!
+ Hook Point: Greetings, Output Generated For All Callback Functions!

```


<a name="testing"></a>
## Testing

```bash
composer test
```

<a name="changelog"></a>
## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

<a name="contributing"></a>
## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

<a name="credits"></a>
## Credits

- [Mohamed Magdi](https://github.com/magdicom)

<a name="license"></a>
## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
