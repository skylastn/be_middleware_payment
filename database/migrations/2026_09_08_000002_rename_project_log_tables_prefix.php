<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'log__%'"))
                ->pluck('name');
        } else {
            $tables = collect(DB::select('SHOW TABLES'))
                ->map(fn ($t) => array_values((array) $t)[0])
                ->filter(fn ($name) => str_starts_with($name, 'log__'))
                ->values();
        }

        foreach ($tables as $oldTable) {
            $newTable = 'z__' . $oldTable;
            if (! Schema::hasTable($newTable)) {
                Schema::rename($oldTable, $newTable);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'z__log__%'"))
                ->pluck('name');
        } else {
            $tables = collect(DB::select('SHOW TABLES'))
                ->map(fn ($t) => array_values((array) $t)[0])
                ->filter(fn ($name) => str_starts_with($name, 'z__log__'))
                ->values();
        }

        foreach ($tables as $newTable) {
            $oldTable = substr($newTable, 3); // drop 'z__'
            if (! Schema::hasTable($oldTable)) {
                Schema::rename($newTable, $oldTable);
            }
        }
    }
};
