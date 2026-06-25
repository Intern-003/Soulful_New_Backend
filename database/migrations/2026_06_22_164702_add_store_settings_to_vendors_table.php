<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {

            $table->longText('store_about')->nullable()->after('description');

            $table->string('theme_color')->nullable();

            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('youtube_url')->nullable();

            $table->integer('followers_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {

            $table->dropColumn([
                'store_about',
                'theme_color',
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'youtube_url',
                'followers_count'
            ]);
        });
    }
};