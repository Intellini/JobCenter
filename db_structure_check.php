<?php
/**
 * Database Structure Check Script
 * This script checks the database structure for machine identification and job planning
 */

// Include database configuration
require_once('config/database.php');

try {
    $db = Database::getInstance();
    
    echo "=== MACHINE TABLE STRUCTURE ===\n";
    $machineStructure = $db->getAll("DESCRIBE machine");
    if ($machineStructure) {
        foreach ($machineStructure as $column) {
            echo "Field: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}, Extra: {$column['Extra']}\n";
        }
    } else {
        echo "Machine table not found or no columns returned\n";
    }
    
    echo "\n=== MACH_PLANNING TABLE STRUCTURE ===\n";
    $machPlanningStructure = $db->getAll("DESCRIBE mach_planning");
    if ($machPlanningStructure) {
        foreach ($machPlanningStructure as $column) {
            echo "Field: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}, Extra: {$column['Extra']}\n";
        }
    } else {
        echo "mach_planning table not found or no columns returned\n";
    }
    
    echo "\n=== OPERATIONS TABLE STRUCTURE ===\n";
    $operationsStructure = $db->getAll("DESCRIBE operations");
    if ($operationsStructure) {
        foreach ($operationsStructure as $column) {
            echo "Field: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}, Extra: {$column['Extra']}\n";
        }
    } else {
        echo "operations table not found, trying 'operation' table...\n";
        $operationsStructure = $db->getAll("DESCRIBE operation");
        if ($operationsStructure) {
            foreach ($operationsStructure as $column) {
                echo "Field: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}, Extra: {$column['Extra']}\n";
            }
        } else {
            echo "operation table not found either\n";
        }
    }
    
    echo "\n=== SAMPLE MACHINE DATA ===\n";
    $sampleMachines = $db->getAll("SELECT * FROM machine LIMIT 5");
    if ($sampleMachines) {
        foreach ($sampleMachines as $machine) {
            echo "Machine: " . print_r($machine, true) . "\n";
        }
    } else {
        echo "No sample machine data found\n";
    }
    
    echo "\n=== SAMPLE MACH_PLANNING DATA ===\n";
    $samplePlanning = $db->getAll("SELECT * FROM mach_planning LIMIT 5");
    if ($samplePlanning) {
        foreach ($samplePlanning as $planning) {
            echo "Planning: " . print_r($planning, true) . "\n";
        }
    } else {
        echo "No sample mach_planning data found\n";
    }
    
    echo "\n=== TABLE RELATIONSHIPS CHECK ===\n";
    // Check for foreign key relationships
    $fkQuery = "SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = 'ptpl' 
    AND (TABLE_NAME = 'mach_planning' OR REFERENCED_TABLE_NAME IN ('machine', 'operations', 'operation'))";
    
    $relationships = $db->getAll($fkQuery);
    if ($relationships) {
        foreach ($relationships as $rel) {
            echo "FK Relationship: {$rel['TABLE_NAME']}.{$rel['COLUMN_NAME']} -> {$rel['REFERENCED_TABLE_NAME']}.{$rel['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "No formal foreign key relationships found, checking for common column names...\n";
        
        // Check for common ID columns
        $commonColumns = $db->getAll("
            SELECT 'mach_planning' as table_name, COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = 'ptpl' AND TABLE_NAME = 'mach_planning' 
            AND (COLUMN_NAME LIKE '%machine%' OR COLUMN_NAME LIKE '%operation%' OR COLUMN_NAME LIKE '%id%')
            UNION
            SELECT 'machine' as table_name, COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = 'ptpl' AND TABLE_NAME = 'machine' 
            AND COLUMN_NAME LIKE '%id%'
        ");
        
        if ($commonColumns) {
            foreach ($commonColumns as $col) {
                echo "Potential relationship column: {$col['table_name']}.{$col['COLUMN_NAME']}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>