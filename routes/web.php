<?php

use App\Http\Controllers\AccurateController;
use App\Http\Controllers\DatabaseSelectionController;
use App\Http\Controllers\DataMigrateController;
use App\Http\Controllers\ModulesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemLogsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


Route::get('/', function () {
  return redirect()->route('login');
});

require __DIR__ . '/auth.php';

Route::middleware('auth')->group(function () {
  Route::get('/redirect-after-login', function () {
    $user = Auth::user();

    if ($user->hasRole(['Super Admin', 'Admin'])) {
      return redirect()->route('modules.index');
    }
    return redirect('/login');
  })->name('login.redirect');

  Route::get('/select-database', [
    DatabaseSelectionController::class,
    'showSelection',
  ])->name('database.selection');
  Route::post('/select-database', [
    DatabaseSelectionController::class,
    'selectDatabase',
  ])->name('database.select');
  Route::post('/accurate/disconnect', [
    AccurateController::class,
    'disconnect',
  ])->name('accurate.disconnect');
  Route::get('/accurate/auth', [
    AccurateController::class,
    'redirectToAccurate',
  ])->name('accurate.auth');
  Route::get('/accurate/callback', [
    AccurateController::class,
    'handleCallback',
  ])->name('accurate.callback');


  Route::get('/profile', [ProfileController::class, 'edit'])->name(
    'profile.edit',
  );
  Route::patch('/profile', [ProfileController::class, 'update'])->name(
    'profile.update',
  );
  Route::delete('/profile', [ProfileController::class, 'destroy'])->name(
    'profile.destroy',
  );
  Route::get('/settings', [SettingsController::class, 'index'])->name(
    'settings.index',
  );
  Route::get('/settings/accurate', [
    SettingsController::class,
    'accurateSettings',
  ])->name('settings.accurate');


  Route::middleware("database.selected")->group(function () {
    Route::controller(ModulesController::class)->prefix('modules')->group(function () {
      Route::get('/', 'index')->name('modules.index');
      Route::post('{module}/capture', [ModulesController::class, 'captureData'])
        ->name('modules.capture');
    });
    Route::controller(DataMigrateController::class)->prefix('migrate')->group(function () {
      Route::get('/', 'index')->name('migrate.index');
      Route::delete('/{transaction}', 'destroy')->name('migrate.destroy');
      Route::delete('/', 'destroyMultiple')->name('migrate.destroyMultiple');
    });
    Route::controller(SystemLogsController::class)->prefix('system-logs')->group(function () {
      Route::get('/', 'index')->name('system-logs.index');
    });
    Route::controller(UsersController::class)->middleware('can:manage_users')->prefix('users')->group(function () {
      Route::get('/', 'index')->name('users.index');
      Route::post('/', 'store')->name('users.store');
      Route::put('/{user}', 'update')->name('users.update');
      Route::delete('/{user}', 'destroy')->name('users.destroy');
    });

    Route::controller(SettingsController::class)->middleware('can:manage_settings')->prefix('configuration')->group(function () {
      Route::get('/', 'index')->name('configuration.index');
      Route::put('/', 'update')->name('configuration.update');
    });
  });
});



Route::get('/accurate/auth', function (Request $request) {
  $request->session()->put('state', $state = Str::random(40));
  $clientId = env('ACCURATE_CLIENT_ID');
  $query = http_build_query([
    'client_id' => $clientId,
    'response_type' => 'code',
    'redirect_uri' => route('accurate.callback'),
    'scope' => 'item_view item_save customer_save customer_view sales_order_save job_order_save sales_order_view job_order_view roll_over_save purchase_order_view',
    'state' => $state,
  ]);

  return redirect(env('ACCURATE_API_URL') . '/oauth/authorize?' . $query);
})->name('accurate.auth');

Route::get('/accurate/callback', [
  AccurateController::class,
  'handleCallback',
])->name('accurate.callback');
