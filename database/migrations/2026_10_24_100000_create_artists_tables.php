<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything MusicBrainz has on one artist, saved so it is fetched only once.
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->uuid('mbid')->unique();
            $table->string('name', 300);
            $table->json('data');
            $table->json('release_groups');
            $table->timestamp('fetched_at');
            $table->timestamps();
        });

        // A name as the radio spells it, and the artist it matched (or none).
        Schema::create('artist_names', function (Blueprint $table) {
            $table->id();
            $table->string('key', 300)->unique();
            $table->string('name', 300);
            $table->foreignId('artist_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_names');
        Schema::dropIfExists('artists');
    }
};
