# pdo-mysql-select-iterator
[![Latest Stable Version](https://poser.pugx.org/antevenio/pdo-mysql-select-iterator/v/stable)](https://packagist.org/packages/antevenio/pdo-mysql-select-iterator)
[![Total Downloads](https://poser.pugx.org/antevenio/pdo-mysql-select-iterator/downloads)](https://packagist.org/packages/antevenio/pdo-mysql-select-iterator)
[![License](https://poser.pugx.org/antevenio/pdo-mysql-select-iterator/license)](https://packagist.org/packages/antevenio/pdo-mysql-select-iterator)
[![Build Status](https://travis-ci.org/Antevenio/pdo-mysql-select-iterator.svg?branch=master)](https://travis-ci.org/Antevenio/pdo-mysql-select-iterator)
[![Code Climate](https://codeclimate.com/github/Antevenio/pdo-mysql-select-iterator.png)](https://codeclimate.com/github/Antevenio/pdo-mysql-select-iterator)

PHP PDO Mysql select statement iterator implemented as multiple queries using LIMIT clauses.

What is this thing
---
So, you want to iterate through millions of table rows coming as a result
of some MySQL select query because you want to do your thingie with them, but alas!, your lovely
database admin doesn't like queries that stay for too long running in his server,
or even worse, the server itself isn't able to hold them!. What do we do?, we do issue several
queries that would fetch smaller blocks of rows in place.


This is an just a PHP iterator based on this concept. Upon receiving a SELECT query and a
PDO database connection, an iterator is built so you can seamlessly traverse results without
having to worry about anything else.

Install
---

To add as a dependency using composer:

`composer require antevenio/pdo-mysql-select-iterator`

Usage example
---

```php
<?php
$pdo = new PDO('mysql:host=localhost;dbname=kidsshouting', 'myuser', 'mypass');
$iterator = (new \PdoMysqlSelectIterator\Factory())
    ->create($pdo, "select a,b from tbl order by a", 1000);
// Get a total row count from the query if needed
$total = count($iterator);
foreach ($iterator as $item) {
    // Do your stuff with $item
}
```

Notes
---
The factory will throw a InvalidQueryException on non "select" queries.

The factory will only return a LimitIterator when:
* The blockSize is > 0
* The query has an "order by" clause
* The query is not using any "rand()" functions
* The query doesn't already have a "limit" clause.

If any of the previous conditions are met, the factory will return a non limit based iterator called 
"NativePDOIterator". 

To ensure consistency among results, you might want to get the whole iteration and count inside a database transaction.
The most suitable isolation level would be REPEATABLE-READ: 

https://dev.mysql.com/doc/refman/5.7/en/innodb-transaction-isolation-levels.html#isolevel_repeatable-read
