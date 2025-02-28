<?php

namespace App\Controllers;

class QuickMigrate extends BaseController
{
    public function index(): string
    {
        /** RULES
        * cannot drop primary key
        * all the unique key start with unique_
        * all the foreign key start with fk_
        * only one operation can be done at a time per table (create column, update column, delete column, add foreign key, add unique key, delete foreign key, delete unique key, change column type, change column name, change column order)
        * if alteration needed in fk_ then remove and add again
        * if you need to add new column then add at the end of the schema and then reorder the columns
        */ 

        $this->deleteUnwantedTables();

        // Create users table first
        $this->manageUsersTable();

        // Create user_otp table after users
        $this->manageUserOtpTable();

        return view('welcome_message');
    }

    // Function to delete unwanted tables
    private function deleteUnwantedTables()
    {
        $db = \Config\Database::connect();
        $expectedTables = ['users', 'user_otp'];
        $tables = $db->listTables();

        foreach ($tables as $table) {
            if (!in_array($table, $expectedTables)) {
                echo "Dropping unwanted table: $table\n";
                $db->query("DROP TABLE IF EXISTS `$table`");
            }
        }
    }

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

    private function manageUserOtpTable()
    {
        helper(['quickMigrate']);

        $expectedSchema = [
            'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'otp' => ['type' => 'VARCHAR', 'constraint' => 255],
            'test2' => ['type' => 'VARCHAR', 'constraint' => 255],
            'test3' => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id' => ['type' => 'INT', 'constraint' => 11],
            'created_at' => ['type' => 'DATETIME', 'null' => true],

            // Primary Key
            'primary_key' => 'id',

            // Foreign keys
            'foreign_keys' => [
                'fk_users_otp_id' => [
                    'column' => 'user_id',
                    'reference_table' => 'users',
                    'reference_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'CASCADE',
                ]
            ],
        ];

        quickMigrate('user_otp', $expectedSchema);
    }
}
