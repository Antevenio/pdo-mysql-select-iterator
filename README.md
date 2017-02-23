# pdo-mysql-select-iterator
[![Build Status](https://travis-ci.org/Antevenio/pdo-mysql-select-iterator.svg?branch=master)](https://travis-ci.org/Antevenio/pdo-mysql-select-iterator)

PHP PDO Mysql select statement iterator implemented as multiple queries using LIMIT clauses.

What is this thing
---
So, you want to iterate through millions of table rows comming as a result
of some MySQL select query because you want to do your thingie with them, but alas!, your lovely
database admin doesn't like queries that stay for too long running in his server,
or even the server itself isn't able to hold them. What do we do?, we do issue several
queries that would fetch smaller blocks of rows in place.


This is an just a PHP iterator based on this concept. Upon receiving a SELECT query and a
PDO database connection, an iterator is built so you can seamlesly traverse it wihtout
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
$iterator = new \PdoMysqlSelectIterator\Iterator($pdo, "select * from tbl", 1000);
// Get a total row count from the query if needed
$total = count($iterator);
foreach ($iterator as $item) {
    // Do your stuff with $item
}
```