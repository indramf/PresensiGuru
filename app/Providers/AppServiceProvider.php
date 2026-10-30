<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\ClassReport;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([User::class, Teacher::class, SchoolClass::class, Subject::class, Schedule::class, Attendance::class, ClassReport::class] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
