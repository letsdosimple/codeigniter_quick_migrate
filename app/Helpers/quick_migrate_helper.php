<?php

function quickMigrate($tableName, $expectedSchema)
{
    $db = \Config\Database::connect();
    $forge = \Config\Database::forge();

    // Check if table exists
    if (!$db->tableExists($tableName)) {
        echo "Creating table: $tableName\n";
        $forge->addField($expectedSchema);

        // ✅ Add primary key
        if (isset($expectedSchema['primary_key'])) {
            $forge->addPrimaryKey($expectedSchema['primary_key']);
        }

        // ✅ Add unique keys during creation
        if (isset($expectedSchema['unique_keys'])) {
            foreach ($expectedSchema['unique_keys'] as $keyName => $columns) {
                $forge->addUniqueKey($columns, $keyName);
            }
        }

        // ✅ Create the table first before adding foreign keys
        $forge->createTable($tableName, true);

        // ✅ Add foreign keys after table creation
        if (isset($expectedSchema['foreign_keys'])) {
            foreach ($expectedSchema['foreign_keys'] as $keyName => $keyDetails) {
                echo "Adding foreign key: $keyName\n";
                $db->query("ALTER TABLE `$tableName` ADD CONSTRAINT `$keyName` FOREIGN KEY (`{$keyDetails['column']}`)
                            REFERENCES `{$keyDetails['reference_table']}`(`{$keyDetails['reference_column']}`)
                            ON DELETE {$keyDetails['on_delete']} ON UPDATE {$keyDetails['on_update']}");
            }
        }

        return;
    } else {
        // If table exists, check for column changes and updates

        // Fetch existing table structure
        $tableFields = $db->getFieldData($tableName);
        $existingColumns = [];
        foreach ($tableFields as $field) {
            $existingColumns[$field->name] = [
                'type' => strtoupper($field->type),
                'constraint' => $field->max_length ?? null
            ];
        }

        $expectedColumnNames = array_filter(array_keys($expectedSchema), function($key) {
            return !in_array($key, ['primary_key', 'unique_keys', 'foreign_keys']);
        });
        $existingColumnNames = array_keys($existingColumns);
        
        // echo "<pre>";
        // print_r($expectedSchema);
        // print_r($existingColumns);
        // print_r($expectedColumnNames);
        // print_r($existingColumnNames);
       

        // Step 1: Rename Columns
        if (count($existingColumnNames) === count($expectedColumnNames)) {
            foreach ($existingColumnNames as $existingNameKey => $existingName) {
                if (!isset($expectedSchema[$existingName])) {

                    $db->query("ALTER TABLE `$tableName` CHANGE `$existingName` `$expectedColumnNames[$existingNameKey]` {$expectedSchema[$expectedColumnNames[$existingNameKey]]['type']}({$expectedSchema[$expectedColumnNames[$existingNameKey]]['constraint']})");

                    $fields = [
                        $expectedColumnNames[$existingNameKey] => $expectedSchema[$expectedColumnNames[$existingNameKey]]
                    ];
                    $forge->modifyColumn($tableName, $fields);


                }else{
                    if(
                        $existingColumns[$existingName]['type'] !== $expectedSchema[$existingName]['type'] 
                        || (
                            isset($existingColumns[$existingName]['constraint'])
                            && isset($expectedSchema[$existingName]['constraint'])
                            &&
                            $existingColumns[$existingName]['constraint'] !== $expectedSchema[$existingName]['constraint']
                        ) 
                    ){
                        $fields = [
                            $existingName => $expectedSchema[$existingName]
                        ];
                        $forge->modifyColumn($tableName, $fields);
                    }
                }


            }
        }

        // Refresh Table Schema after Renaming
        $tableFields = $db->getFieldData($tableName);
        $existingColumns = [];
        foreach ($tableFields as $field) {
            $existingColumns[$field->name] = [
                'type' => strtoupper($field->type),
                'constraint' => $field->max_length ?? null
            ];
        }

        // Step 2: Remove Extra Columns
        foreach ($existingColumns as $colName => $attributes) {
            if (!isset($expectedSchema[$colName])) {
                echo "Removing column: $colName\n";
                try {
                    $forge->dropColumn($tableName, $colName);
                } catch (\Exception $e) {
                    echo "Error removing column: " . $e->getMessage() . "\n";
                }
            }
        }

        // Refresh Table Schema after Deleting Columns
        $tableFields = $db->getFieldData($tableName);
        $existingColumns = [];
        foreach ($tableFields as $field) {
            $existingColumns[$field->name] = [
                'type' => strtoupper($field->type),
                'constraint' => $field->max_length ?? null
            ];
        }

        // Step 3: Add Missing Columns
        foreach ($expectedSchema as $colName => $attributes) {
            if (!isset($existingColumns[$colName]) && !in_array($colName, ['primary_key', 'unique_keys', 'foreign_keys'])) {
                echo "Adding column: $colName\n";
                try {
                    $forge->addColumn($tableName, [$colName => $attributes]);
                    $existingColumns[$colName] = $attributes;
                } catch (\Exception $e) {
                    echo "Error adding column: " . $e->getMessage() . "\n";
                }
            }
        }

        // Step 4: Reorder Columns
        $previousColumn = null;
        $isCorrectOrder = true;
        $currentOrder = array_keys($existingColumns);

        // Check if the current order matches the expected order
        foreach ($expectedColumnNames as $index => $colName) {
            if ($currentOrder[$index] !== $colName) {
            $isCorrectOrder = false;
            break;
            }
        }

        // If the order is not correct, reorder the columns
        if (!$isCorrectOrder) {
            foreach ($expectedColumnNames as $colName) {
            if (isset($existingColumns[$colName])) {
                echo "Reordering column: $colName after $previousColumn\n";
                $attributes = $expectedSchema[$colName];

                try {
                $sql = "ALTER TABLE `$tableName` MODIFY COLUMN `$colName` {$attributes['type']}";
                if (!empty($attributes['constraint'])) {
                    $sql .= "({$attributes['constraint']})";
                }
                if ($previousColumn) {
                    $sql .= " AFTER `$previousColumn`";
                } else {
                    $sql .= " FIRST";
                }

                $db->query($sql);
                } catch (\Exception $e) {
                echo "Error reordering column: " . $e->getMessage() . "\n";
                }
            }
            $previousColumn = $colName;
            }
        }
    }

    // ✅ Ensure Unique Keys (for existing tables)
    if (isset($expectedSchema['unique_keys'])) {
        foreach ($expectedSchema['unique_keys'] as $keyName => $columns) {
            // Check if the unique key already exists
            $existingKey = $db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = '$keyName'")->getResultArray();
            if (empty($existingKey)) {
                echo "Adding unique key: $keyName\n";
                $db->query("ALTER TABLE `$tableName` ADD CONSTRAINT `$keyName` UNIQUE (`" . implode('`, `', $columns) . "`)");
            }
        }

        // check if any unique keys are removed
        $existingKeys = $db->query("SHOW INDEX FROM `$tableName` WHERE Non_unique = 0")->getResultArray();
        foreach ($existingKeys as $key) {
            // Skip primary key (which is always non-unique)
            if ($key['Key_name'] != 'PRIMARY' && strpos($key['Key_name'], 'fk_') !== 0 && strpos($key['Key_name'], 'unique_') === 0) {
                $isKeyInSchema = false;
                foreach ($expectedSchema['unique_keys'] as $keyName => $columns) {
                    if ($key['Key_name'] === $keyName) {
                        $isKeyInSchema = true;
                        break;
                    }
                }

                // Drop the unique key if it is not defined in the schema
                if (!$isKeyInSchema) {
                    echo "Dropping unique key: {$key['Key_name']}\n";
                    $db->query("ALTER TABLE `$tableName` DROP INDEX `{$key['Key_name']}`");
                }
            }
        }
    } else {
        // If unique keys are removed, drop them from the table
        $existingKeys = $db->query("SHOW INDEX FROM `$tableName` WHERE Non_unique = 0")->getResultArray();
        foreach ($existingKeys as $key) {
            // Skip primary key (which is always non-unique)
            if ($key['Key_name'] != 'PRIMARY') {
                $db->query("ALTER TABLE `$tableName` DROP INDEX `{$key['Key_name']}`");
                echo "Dropping unique key: {$key['Key_name']}\n";
            }
        }
    }

    // ✅ Ensure Foreign Keys (for existing tables)
    if (isset($expectedSchema['foreign_keys'])) {
        foreach ($expectedSchema['foreign_keys'] as $keyName => $keyDetails) {
            // Check if the foreign key already exists using SHOW KEYS FROM
            $existingForeignKey = $db->query("SHOW KEYS FROM `$tableName` WHERE Key_name = '$keyName'")->getResultArray();

            // If the foreign key doesn't exist, add it
            if (empty($existingForeignKey)) {
                echo "Adding foreign key: $keyName\n";
                $db->query("ALTER TABLE `$tableName` ADD CONSTRAINT `$keyName` FOREIGN KEY (`{$keyDetails['column']}`)
                        REFERENCES `{$keyDetails['reference_table']}`(`{$keyDetails['reference_column']}`)
                        ON DELETE {$keyDetails['on_delete']} ON UPDATE {$keyDetails['on_update']}");
            }
        }
    } else {
        // If foreign keys are removed, drop them from the table
        // Get all keys that are non-unique (foreign keys) for the table
        $existingForeignKeys = $db->query("SHOW KEYS FROM `$tableName` WHERE Key_name != 'PRIMARY'")->getResultArray();

        foreach ($existingForeignKeys as $foreignKey) {
            // Skip keys starting with 'unique'
            if (strpos($foreignKey['Key_name'], 'unique') === 0) {
                continue; // Skip this iteration if the key name starts with 'unique'
            }

            // Skip if the foreign key doesn't match the expected schema
            $isForeignKeyInSchema = false;

            // Check if 'foreign_keys' exists in the schema before accessing it
            if (isset($expectedSchema['foreign_keys'])) {
                foreach ($expectedSchema['foreign_keys'] as $keyName => $keyDetails) {
                    if ($foreignKey['Key_name'] === $keyName) {
                        $isForeignKeyInSchema = true;
                        break;
                    }
                }
            }

            // Drop the foreign key if it is not defined in the schema
            if (!$isForeignKeyInSchema) {
                echo "Dropping foreign key: {$foreignKey['Key_name']}\n";
                $db->query("ALTER TABLE `$tableName` DROP FOREIGN KEY `{$foreignKey['Key_name']}`");
                $db->query("ALTER TABLE `$tableName` DROP INDEX `{$foreignKey['Key_name']}`");
            }
        }
    }
}
