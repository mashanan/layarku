<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical layout: cinemas -> studios -> seats.
 * This data rarely changes and is created by seeders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cinemas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('address');
            $table->timestamps();
        });

        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cinema_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['cinema_id', 'name']);
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('row_label', 3);            // A, B, C ...
            $table->unsignedSmallInteger('number');    // 1, 2, 3 ...
            // Position on the drawing grid. Gaps in grid_x are aisles, so the
            // client can draw the room without knowing anything about aisles.
            $table->unsignedSmallInteger('grid_x');
            $table->unsignedSmallInteger('grid_y');

            $table->unique(['studio_id', 'row_label', 'number']);
            $table->unique(['studio_id', 'grid_x', 'grid_y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('studios');
        Schema::dropIfExists('cinemas');
    }
};
