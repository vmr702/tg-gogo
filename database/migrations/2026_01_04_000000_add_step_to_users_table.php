<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class 
{
    public function up(): void
    {
        Capsule::schema()->table('users', function (Blueprint $table) {
            $table->string('step')->default('idle');
            $table->text('draft_event')->nullable();
        });
    }

    public function down(): void
    {
        Capsule::schema()->table('users', function (Blueprint $table) {
            $table->dropColumn(['step', 'draft_event']);
        });
    }
};
