<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('wikipedia_title', 300)->default('');
            $table->string('wikipedia_url', 500)->default('');
            $table->text('wikipedia_summary')->nullable();
            $table->string('wikipedia_image', 500)->default('');
            $table->json('apple_albums')->nullable();
            $table->timestamp('extras_checked_at')->nullable();
        });

        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 400)->unique();
            $table->string('title', 300);
            $table->string('artist_name', 300);
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('album', 300)->default('');
            $table->string('artwork_url', 500)->default('');
            $table->string('apple_preview_url', 500)->default('');
            $table->string('apple_url', 500)->default('');
            $table->unsignedBigInteger('deezer_track_id')->nullable();
            $table->string('deezer_url', 500)->default('');
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->string('station', 40);
            $table->string('artist_name', 300);
            $table->string('title', 300);
            $table->string('song_key', 400)->index();
            $table->timestamp('played_at')->index();
            $table->unique(['station', 'played_at']);
        });

        Schema::create('spotify_accounts', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Searches now include aliases, so names that found nothing deserve another try.
        DB::table('artist_names')->whereNull('artist_id')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('spotify_accounts');
        Schema::dropIfExists('plays');
        Schema::dropIfExists('songs');
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['wikipedia_title', 'wikipedia_url', 'wikipedia_summary', 'wikipedia_image', 'apple_albums', 'extras_checked_at']);
        });
    }
};
