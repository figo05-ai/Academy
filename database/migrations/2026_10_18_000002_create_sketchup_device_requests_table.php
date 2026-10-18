<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('sketchup_device_requests', function (Blueprint $table) {
            $table->id();
            $table->string('device_hash', 64)->unique();
            $table->string('code_hash', 64)->unique();
            $table->string('challenge', 64);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('remember')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sketchup_device_requests'); }
};
