<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Support\Icons;
use App\Support\Menu;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        Paginator::defaultView('pagination.erapor');
        Paginator::defaultSimpleView('pagination.erapor');

        Blade::directive('icon', fn ($expr) => "<?php echo \\App\\Support\\Icons::svg($expr); ?>");
        Blade::if('perm', fn (...$perms) => auth()->check() && auth()->user()->hasAnyPermission($perms));
        Blade::if('role', fn (...$roles) => auth()->check() && auth()->user()->hasRole(...$roles));

        View::composer(['layouts.app', 'dashboard.index'], function ($view) {
            $user = auth()->user();
            $view->with([
                'menuGroups' => $user ? Menu::for($user) : [],
                'school' => SchoolProfile::current(),
                'activeYear' => AcademicYear::active(),
                'activeSemester' => Semester::active(),
                'unreadNotifications' => $user ? $user->unreadNotifications()->latest()->limit(8)->get() : collect(),
            ]);
        });
    }
}
