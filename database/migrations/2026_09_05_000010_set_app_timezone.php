<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Marker migration documenting the timezone change to Asia/Kuala_Lumpur.
     * The actual timezone is configured in config/app.php.
     */
    public function up(): void
    {
        // No schema changes — timezone is set in config/app.php
    }

    public function down(): void
    {
        // No schema changes to revert
    }
};
