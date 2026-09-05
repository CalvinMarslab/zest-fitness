<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Attempt safe backfill of coach_id on gym_classes from the coach name string
        $classes = DB::table('gym_classes')->whereNull('coach_id')->whereNotNull('coach')->get();
        foreach ($classes as $class) {
            $coachUser = DB::table('users')
                ->where('role', 'coach')
                ->where('name', $class->coach)
                ->first();
            if ($coachUser) {
                DB::table('gym_classes')->where('id', $class->id)->update(['coach_id' => $coachUser->id]);
            }
        }

        // Same for class_templates
        $templates = DB::table('class_templates')->whereNull('coach_id')->whereNotNull('coach')->get();
        foreach ($templates as $template) {
            $coachUser = DB::table('users')
                ->where('role', 'coach')
                ->where('name', $template->coach)
                ->first();
            if ($coachUser) {
                DB::table('class_templates')->where('id', $template->id)->update(['coach_id' => $coachUser->id]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill — no rollback
    }
};
