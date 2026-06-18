<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class 
{
    public function up(): void
    {
        Capsule::schema()->table('events', function (Blueprint $table) {
            $table->dateTime('event_date')->nullable();
            $table->integer('views_count')->default(0);
            $table->boolean('is_free')->default(true);
            $table->integer('price')->nullable();

            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table('events', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'views_count', 'is_free', 'price']);
        });
    }
};
