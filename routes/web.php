<?php

use App\Http\Controllers\OAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\IngestionController;
use App\Http\Controllers\ApiConsumerOAuthController;
use App\Http\Controllers\ApiV1Controller;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DeveloperClientController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\PrivacyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard.index');
Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware('admin.user')
    ->name('admin.dashboard.index');

Route::get('/{provider}/authorize/start', [OAuthController::class, 'start'])->name('oauth.start');
Route::get('/{provider}/authorize/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
Route::get('/{provider}/authorize/status', [OAuthController::class, 'status'])->name('oauth.status');
Route::post('/{provider}/authorize/revoke', [OAuthController::class, 'revoke'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('oauth.revoke');

Route::post('/{provider}/ingestion/run', [IngestionController::class, 'run'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/{provider}/ingestion/jobs', [IngestionController::class, 'jobs']);
Route::get('/{provider}/ingestion/jobs/{jobId}', [IngestionController::class, 'showJob']);

Route::post('/embeds', [EmbedController::class, 'store'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/embeds', [EmbedController::class, 'index']);
Route::get('/embeds/{embedId}', [EmbedController::class, 'show']);
Route::patch('/embeds/{embedId}', [EmbedController::class, 'update'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::delete('/embeds/{embedId}', [EmbedController::class, 'destroy'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/embeds/{embedId}/token', [EmbedController::class, 'issueToken'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/embed/{embedId}', [EmbedController::class, 'runtime']);

Route::get('/developer-clients', [DeveloperClientController::class, 'index']);
Route::post('/developer-clients', [DeveloperClientController::class, 'store'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::patch('/developer-clients/{clientId}', [DeveloperClientController::class, 'update'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::delete('/developer-clients/{clientId}', [DeveloperClientController::class, 'destroy'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/developer-clients/{clientId}/rotate-secret', [DeveloperClientController::class, 'rotateSecret'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/developer-clients/{clientId}/revoke', [DeveloperClientController::class, 'revoke'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/oauth/token', [ApiConsumerOAuthController::class, 'token'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/oauth/token/refresh', [ApiConsumerOAuthController::class, 'refresh'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/oauth/token/revoke', [ApiConsumerOAuthController::class, 'revoke'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/billing/plans', [BillingController::class, 'createPlan'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/billing/subscriptions', [BillingController::class, 'subscribe'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/billing/subscriptions/{tenantId}', [BillingController::class, 'showSubscription']);

Route::post('/privacy/data-export', [PrivacyController::class, 'export'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/privacy/deauthorize', [PrivacyController::class, 'deauthorize'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/privacy/data-deletion', [PrivacyController::class, 'deleteData'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware('api.consumer')->prefix('v1')->group(function (): void {
    Route::get('/media', [ApiV1Controller::class, 'media']);
    Route::get('/media/{itemId}', [ApiV1Controller::class, 'mediaItem']);
    Route::get('/metrics', [ApiV1Controller::class, 'metrics']);
    Route::get('/usage', [ApiV1Controller::class, 'usage']);
});
