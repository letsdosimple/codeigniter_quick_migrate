# Welcome to Codeigniter_quick_migrate!

Hi!  objective of **Codeigniter_quick_migrate** is to create migration quicker, faster and refer columns quickly at one place.

# Problem
- Not able to check all columns at one place after multiple changes on single table,
- Every time need to do SSH login to run migration.

# Solution
We created the following `quickMigrate` function in following file.
`codeigniter_quick_migrate/app/Helpers/quick_migrate_helper.php`

# Features and current support
Following operation can be done
* Create table
* Update table columns
	* Add column
	* Alter column name and data type
	* Delete column
	* Reorder the column
	* Add single or multiple unique keys
	* Delete single or multiple unique keys
	* Add single or multiple foregin keys
	* Delete single or multiple foregin keys
* Delete existing table

# Rules to know before use
* Always start with latest schema
* Cannot delete primary key
* All the unique key should start with unique_
* All the foreign key should start with fk_
* Only one operation can be done at a time per table (create column, update column, delete column, add foreign key, add unique key, delete foreign key, delete unique key, change column type, change column name, change column order)
* If alteration needed in fk_ then remove and add again
* If you need to add new column then add at the end of the schema and then change column order

# How to use
Copy and add the following
- Route `$routes->get('/quick-migrate', 'QuickMigrate::index');`
- Helper `codeigniter_quick_migrate/app/Helpers/quick_migrate_helper.php`
- Controller `codeigniter_quick_migrate/app/Controllers/QuickMigrate.php`

## Adding new table
- Create a new function and add the schema in following file
`codeigniter_quick_migrate/app/Controllers/QuickMigrate.php`
- Add table name in following function `$expectedTables = ['users', 'user_otp'];`
- Run the route

## Adding new column in table
- Operations
    - Place the latest schema and Add column
	- Reorder column [if needed]
    
example:
``` PHP
private function manageUsersTable()
{
    helper(['quickMigrate']);

    // Adding test4, test5 column
    $expectedSchema = [
        'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test2' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
        'created_at' => ['type' => 'DATETIME', 'null' => true],
        'test4' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test5' => ['type' => 'VARCHAR', 'constraint' => 255],

        // Primary Key
        'primary_key' => 'id',

        //Unique keys
        'unique_keys' => [
            'unique_email' => ['email'],
        ],
    ];

    quickMigrate('users', $expectedSchema);

    //reorderging columns
    $expectedSchema = [
        'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test2' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test4' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test5' => ['type' => 'VARCHAR', 'constraint' => 255],
        'created_at' => ['type' => 'DATETIME', 'null' => true],

        // Primary Key
        'primary_key' => 'id',

        //Unique keys
        'unique_keys' => [
            'unique_email' => ['email'],
        ],
    ];

    quickMigrate('users', $expectedSchema);
}
```

## Adding new unique key and delete column and remove unique key and alter column name
- Operations
	- Removed the old schema and Place the latest schema and Add unique key for test4
    - Delete the test5 column
    - Remove the PK from email
    - change test2 column name to test33
    
example:
``` PHP
private function manageUsersTable()
{
    helper(['quickMigrate']);

    //Add unique key for test 4
    $expectedSchema = [
        'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test2' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test4' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test5' => ['type' => 'VARCHAR', 'constraint' => 255],
        'created_at' => ['type' => 'DATETIME', 'null' => true],

        // Primary Key
        'primary_key' => 'id',

        //Unique keys
        'unique_keys' => [
            'unique_email' => ['email'],
            'unique_test4' => ['test4'],
        ],
    ];

    quickMigrate('users', $expectedSchema);

    //Delete the test5 column
    $expectedSchema = [
        'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test2' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test4' => ['type' => 'VARCHAR', 'constraint' => 255],
        'created_at' => ['type' => 'DATETIME', 'null' => true],

        // Primary Key
        'primary_key' => 'id',

        //Unique keys
        'unique_keys' => [
            'unique_email' => ['email'],
            'unique_test4' => ['test4'],
        ],
    ];

    quickMigrate('users', $expectedSchema);

    //Remove unique key from email
    $expectedSchema = [
        'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test2' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test4' => ['type' => 'VARCHAR', 'constraint' => 255],
        'created_at' => ['type' => 'DATETIME', 'null' => true],

        // Primary Key
        'primary_key' => 'id',

        //Unique keys
        'unique_keys' => [
            'unique_test4' => ['test4'],
        ],
    ];

    quickMigrate('users', $expectedSchema);

    //change column name from test2 to test33
    $expectedSchema = [
        'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test33' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
        'test4' => ['type' => 'VARCHAR', 'constraint' => 255],
        'created_at' => ['type' => 'DATETIME', 'null' => true],

        // Primary Key
        'primary_key' => 'id',

        //Unique keys
        'unique_keys' => [
            'unique_test4' => ['test4'],
        ],
    ];

    quickMigrate('users', $expectedSchema);
}
```