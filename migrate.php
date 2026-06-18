<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/database.php';

use Illuminate\Database\Capsule\Manager as Capsule;

if (!Capsule::schema()->hasTable('migrations')) 
{
    Capsule::schema()->create('migrations', function ($table) {
        $table->id();
        $table->string('migration');
        $table->integer('batch');
    });
}

$executedMigrations = Capsule::table('migrations')->pluck('migration')->toArray();

$migrationsDir = __DIR__ . '/database/migrations';
$files = scandir($migrationsDir);

$newMigrations = [];
foreach ($files as $file) 
{
    if ($file === '.' || $file === '..') 
    {
        continue;
    }
    
    $migrationName = pathinfo($file, PATHINFO_FILENAME);
    
    if (!in_array($migrationName, $executedMigrations)) 
    {
        $newMigrations[$migrationName] = $migrationsDir . '/' . $file;
    }
}

if (empty($newMigrations)) 
{
    echo "All migrations already executed\n";
    exit;
}

$batch = (Capsule::table('migrations')->max('batch') ?? 0) + 1;

foreach ($newMigrations as $name => $path) 
{
    echo "Run: {$name}... ";
    
    try 
    {
        $migrationInstance = require $path;
        $migrationInstance->up();
        
        Capsule::table('migrations')->insert([
            'migration' => $name,
            'batch' => $batch
        ]);
        
        echo "[ОК]\n";
    } 
    catch (\Exception $e) 
    {
        echo "[ERR]: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migrations executed\n";
