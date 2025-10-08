<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_id')->unique();
            $table->unsignedBigInteger('available_points')->default(0);
            $table->unsignedBigInteger('pending_points')->default(0);
            $table->unsignedBigInteger('lifetime_points')->default(0);
            $table->string('status', 32)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_accounts');
    }
};

