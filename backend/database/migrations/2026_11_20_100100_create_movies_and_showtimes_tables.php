<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->unique();
            $table->string('title');
            $table->text('overview')->nullable();
            // Only the TMDB path is stored; images are loaded from image.tmdb.org.
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->json('genres');                                   // ["Action", "Drama"]
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('age_rating', 10)->nullable();             // e.g. "13+", "PG-13"
            $table->date('release_date')->nullable();
            $table->string('status', 20)->index();                    // App\Enums\MovieStatus
            // TMDB allows caching for at most 6 months; re-running the import
            // refreshes this timestamp.
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('showtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            // DATETIME (not TIMESTAMP) so MySQL never converts with its own
            // session time zone. The app always writes UTC.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('price_idr');                     // whole rupiah
            $table->timestamps();

            $table->index(['movie_id', 'starts_at']);
            $table->index(['studio_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
        Schema::dropIfExists('movies');
    }
};
