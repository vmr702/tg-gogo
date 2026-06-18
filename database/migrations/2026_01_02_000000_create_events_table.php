<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class 
{
    public function up(): void
    {
        Capsule::schema()->create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('city');
            $table->string('title');
            $table->text('description');
            $table->timestamps();

            $table->index('city');
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('events');
    }
};
