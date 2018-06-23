# Moloquent
An extended [jenssegers/laravel-mongodb][1] library

## Notes and Laravel Compatibility

* This package is a wrapper around `jenssegers/mongodb`. Usages and
configurations are the same as base package, except new features and changed behaviours.
They have covered in the following sections.
* This packages uses `jenssegers/mongodb` version `3.0` and it is compatible
with laravel `5.1` and `5.2`.

## What has Added and What has Changed

[jenssegers/laravel-mongodb][1] is a great package but it does not support DBRefs
([read more][2]). The problem was that related model ID stored as
string *(value of related document ObjectID)*, and not MongoDB ObjectID itself.
This is ok and work well until you want to join related collection by using `lookup`
aggregation ([read more][3]). In this package we change the way that the related document
ID stores.

In MongoDB, relations are different than SQL RDBMS. For example a `One-to-Many` relation
can be categorized into three type; `One-to-Few`, `One-to-Many`, `One-to-Squillions`. And
each of them store related IDs with different strategy. Other factors like whether the
related entity is weak or strong entity are effective in selecting the best strategy.
In this package we provided new model relations for covering these type of relations.

You can read more about this topic in these links.
* [6 Rules of Thumb for MongoDB Schema Design: Part 1][4]
* [6 Rules of Thumb for MongoDB Schema Design: Part 2][5]
* [6 Rules of Thumb for MongoDB Schema Design: Part 3][6]

## Installation

Add moloquent package to your `compoer.json`:

```json
"require": {
  "kamva/moloquent": "~1.0"
}
```

And then do a `composer update`. 

Or run the following command on your console:

```
composer require kamva/moloquent ~1.0
```

### Registering the package
Register the service provider within the providers array found in config/app.php:

```php
'providers' => [
    // ...
    Kamva\Moloquent\MoloquentServiceProvider::class
]
```

### Configuration

Change your default database connection name in config/database.php:

```php
'default' => env('DB_CONNECTION', 'mongodb'),
```
And add a new mongodb connection:

```php
'mongodb' => [
    'driver'   => 'mongodb',
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options' => [
        'database' => 'admin' // sets the authentication database required by mongo 3
    ]
],
```
You can connect to multiple servers or replica sets with the following configuration:

```php
'mongodb' => [
    'driver'   => 'mongodb',
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE', 'local'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options'  => []
],
```

## Usage

### Models

Extend your models from `Kamva/Moloquent/Moloquent`, and your done.

```php
use Kamva/Moloquent/Moloquent;

class User extend Moloquent {}
```

### Relations

Supported relations are:

- hasOne
- hasMany
- belongsTo
- belongsToMany
- embedsOne
- embedsMany
- ContainsOne
- ContainsFew
- IncludedIn

The first 6 relations are the same as base package, with a little different that IDs store
as ObjectID instead of value of ObjectID (string)

#### ContainsOne

It is `One-to-One`'s sister. The difference is that the child ID will store in parent
document. Here is an example:

```php
use Kamva/Moloquent/Moloquent;

class User extend Moloquent {

    public function avatar() {
        return $this->containsOne('Image');
    }

}
```

And the document on mongodb users collection would be like this:

```bson
{
    "_id" : ObjectId("57bbca61551dfe007c67427e"),
    "email" : "sample@mail.com",
    "password" : "[hashed passowrd]",
    "image_id" : ObjectId("57d2b82e1284c5008032521f")
}
```

#### ContainsFew

It is a `One-to-Many` relation that children are few, and children are strong entity
(or there's a need to access them directly). So we want to save the children IDs in parent.
Here is an example:

```php
use Kamva/Moloquent/Moloquent;

class User extend Moloquent {

    public function phones() {
        return $this->containsFew('Phone');
    }

}
```

And an example document would be like this:

```bson
{
    "_id" : ObjectId("57bbca61551dfe007c67427e"),
    "email" : "sample@mail.com",
    "password" : "[hashed passowrd]",
    "phone_ids" : [
        ObjectId("57d2ba501284c500826dfbd5"),
        ObjectId("57ba1e7d551dfe007a7e8cc1")
    ]
}
```

#### IncludedIn

It is the opposite relation of `ContainsOne` and `ContainsFew` when the child (current instance) is included in
one parent.

```php
use Kamva/Moloquent/Moloquent;

class Phone extend Moloquent {

    public function owner() {
        return $this->IncludedIn('User');
    }

}
```

#### IncludedInMany

There are situations that the child is included in many instances of a model, for these situation we use
`IncludedInMany` relation. 

```php
use Kamva/Moloquent/Moloquent;

class Address extend Moloquent {

    public function order() {
        return $this->IncludedInMany('Order');
    }

}
```

[فروشگاه ساز][7]


[1]: https://github.com/jenssegers/laravel-mongodb
[2]: https://docs.mongodb.com/manual/reference/database-references/
[3]: https://docs.mongodb.com/master/reference/operator/aggregation/lookup/
[4]: http://blog.mongodb.org/post/87200945828/6-rules-of-thumb-for-mongodb-schema-design-part-1
[5]: http://blog.mongodb.org/post/87892923503/6-rules-of-thumb-for-mongodb-schema-design-part-2
[6]: http://blog.mongodb.org/post/88473035333/6-rules-of-thumb-for-mongodb-schema-design-part-3
[7]: http://kamva.ir
