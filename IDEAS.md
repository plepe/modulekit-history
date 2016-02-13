Repository
==========
Objects of a registered data_type MUST have a function called "repository_path", which returns an array with the full path, including the data type name, e.g. array("Foo", "bar"). Repository will create a file "Foo/bar.json" in the repository.

Objects MUST have a function "view" which returns the data of this object either as string (which will be written into the file as is) or a associative hash (which will be JSON pretty printed).

Example:
```php
$foobar = new Foo("bar"); // "bar" is the id of the object
$foobar->repository_path()
// returns array("Foo", $id)
$foobar->view()
// returns some data
```

constructor($path, [$options])
------------------------------
$repo = new Repository('/path/to/repo');

Available options: none

Repository::register_data_type(title, get_all_function, [$options])
-------------------------------------------------------
get_all_function is a function which returns all objects of this datatype

Example:
```php
$repo->register_data_type("Foo", "get_all_foo");
```

Available options: none

Repository::dump_all()
----------------------
clears the repository, iterates over all registered data types, calls the function to return all objects and saves them to their repository path and finally comits everything.

Repository::create_changeset($message)
------------------------------------
Returns a Changeset object (see below)

Example:
```
$changeset = $repo->create_changeset("message");
```

Repository\Changeset
====================
constructor([$message, [$options]])
-----------------------------------
Will call the hook "changeset_create" which database functions could register to, to open a database transaction.


open()
------
Collect several changes into a single commit. Needs to be closed by calling `commit()`.

add($object)
------------
Adds the specified object to the repository. If the changeset has not been opened, this will create a commit.

Example:
```
$changeset->add($foobar);
// will dump $foo1->view() as JSON into the file Foo/bar.json
```

remove($object)
---------------
Remove the specified object from the repository. If the changeset has not been opened, this will create a commit.

roll_back()
-----------
Undo changes.

Will call the hook "changeset_roll_back" which database functions could register to, to roll back a database transaction.

commit()
--------
Commit changes to the repository.

Will call the hook "changeset_commit" which database functions could register to, to commit a database transaction.

is_open()
---------
Return boolean status of the Changeset.
