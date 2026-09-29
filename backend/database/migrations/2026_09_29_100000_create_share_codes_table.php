<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->morphs('shareable');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('code');
            // morphs() already creates the index on [shareable_type, shareable_id]
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_codes');
    }
};
