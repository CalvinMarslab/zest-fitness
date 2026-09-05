<?php

namespace App\Console\Commands;

use App\Models\ClassTemplate;
use App\Models\GymClass;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateClassInstances extends Command
{
    protected $signature = 'classes:generate {--weeks=8 : Number of weeks ahead to generate}';

    protected $description = 'Generate gym class instances from active templates';

    public function handle(): int
    {
        $weeks = (int) $this->option('weeks');
        $today = Carbon::today();
        $until = $today->copy()->addWeeks($weeks);
        $templates = ClassTemplate::where('is_active', true)->get();
        $created = 0;

        foreach ($templates as $tpl) {
            [$h, $m] = explode(':', $tpl->start_time);

            for ($d = $today->copy(); $d->lt($until); $d->addDay()) {
                if ($d->dayOfWeek !== $tpl->day_of_week) {
                    continue;
                }

                $startTime = $d->copy()->setTime((int) $h, (int) $m);

                $exists = GymClass::where('template_id', $tpl->id)
                    ->whereDate('start_time', $d->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                GymClass::create([
                    'template_id' => $tpl->id,
                    'name' => $tpl->name,
                    'coach' => $tpl->coach,
                    'start_time' => $startTime->toDateTimeString(),
                    'capacity' => $tpl->capacity,
                    'exercises' => [],
                ]);
                $created++;
            }
        }

        $this->info("Generated {$created} class instances from {$templates->count()} templates (next {$weeks} weeks).");

        return self::SUCCESS;
    }
}
