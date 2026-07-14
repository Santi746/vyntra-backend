<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ChannelMessageController;
use App\Http\Controllers\ClubCategoryController;
use App\Http\Controllers\ClubChannelController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubMemberController;
use App\Http\Controllers\ClubMemberRoleController;
use App\Http\Controllers\ClubRoleController;
use App\Http\Controllers\DmConversationController;
use App\Http\Controllers\DmMessageController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Ruta nombrada requerida por la notificación ResetPassword de Laravel (frontend SPA).
// La notificación llama route('password.reset', ['token', 'email']); redirigimos al SPA.
Route::get('/reset-password/{token}', function (Request $request, string $token) {
    $email = $request->query('email', '');

    return redirect(config('app.frontend_url', 'http://localhost:3000').'/reset-password?token='.$token.'&email='.urlencode($email));
})->name('password.reset');

// ============================================================
// RUTAS PÚBLICAS (sin autenticación)
// ============================================================
Route::prefix('auth')->group(function () {
    // ─── Registro y Login ─────────────────────────────────
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,60');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // ─── Socialite (OAuth2) ────────────────────────────────
    Route::get('/social/{provider}', [SocialiteController::class, 'redirect'])
        ->where('provider', 'google|github|discord');
    Route::get('/social/{provider}/callback', [SocialiteController::class, 'callback'])
        ->where('provider', 'google|github|discord');

    // ─── Password Reset ────────────────────────────────────
    Route::post('/forgot-password', ForgotPasswordController::class);
    Route::post('/reset-password', ResetPasswordController::class);

    // ─── Email Verification ────────────────────────────────
    // El enlace de verificación llega sin autenticación
    // (el usuario hace clic desde su email).
    // La firma de la URL (expiración + integridad) se valida
    // en el middleware 'signed', no en el controller.
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify')
        ->middleware('signed');

    // ─── 2FA Verify (token pendiente) ──────────────────────
    // Este endpoint requiere token ability '2fa_pending'
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
        ->middleware(['auth:sanctum', 'ability:2fa_pending']);
});

// ============================================================
// RUTAS PROTEGIDAS (auth:sanctum)
// ============================================================
Route::middleware(['auth:sanctum', 'ability:*'])->group(function () {

    // ============================================================
    // RUTAS DE LECTURA (GET) — Sin rate limiting
    // ============================================================

    // -- Usuario --
    Route::get('/user', [UserController::class, 'me']);
    Route::get('/user/sessions', [UserController::class, 'sessions']);
    Route::get('/users/{user}', [UserController::class, 'show']);

    // -- Amistades --
    Route::get('/user/friends', [FriendshipController::class, 'index']);
    Route::get('/user/friend-requests', [FriendshipController::class, 'pending']);

    // -- Notificaciones --
    Route::get('/notifications', [NotificationController::class, 'index']);

    // -- Clubes --
    Route::get('/user/clubs', [ClubController::class, 'index']);
    Route::get('/clubs/{club}/preview', [ClubController::class, 'preview']);
    Route::get('/clubs/{club}', [ClubController::class, 'show']);

    // -- Miembros --
    Route::get('/clubs/{club}/members', [ClubMemberController::class, 'index']);

    // -- Roles --
    Route::get('/clubs/{club}/roles', [ClubRoleController::class, 'index']);

    // -- Categorías --
    Route::get('/clubs/{club}/categories', [ClubCategoryController::class, 'index']);

    // -- Canales --
    Route::get('/clubs/{club}/channels', [ClubChannelController::class, 'index']);

    // -- Mensajes --
    Route::get('/channels/{channel}/messages', [ChannelMessageController::class, 'index']);

    // -- DM --
    Route::get('/user/dm-conversations', [DmConversationController::class, 'index']);
    Route::get('/dm-conversations/{dm_conversation}', [DmConversationController::class, 'show']);
    Route::get('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'index']);

    // -- Dashboard y Búsqueda --
    Route::get('/explore', [ExploreController::class, 'index']);
    Route::get('/search', [SearchController::class, 'index']);

    // ============================================================
    // RUTAS DE ESCRITURA (POST/PATCH/DELETE) — Rate limited
    // ============================================================
    Route::middleware('throttle:10,1')->group(function () {

        // ─── Autenticación ─────────────────────────────────
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ─── 2FA Management ────────────────────────────────
        Route::post('/auth/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('/auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/auth/2fa/disable', [TwoFactorController::class, 'disable']);

        // ─── Email Verification (reenvío) ──────────────────
        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'notification']);

        // ─── Usuario ───────────────────────────────────────
        Route::patch('/user', [UserController::class, 'updateProfile']);
        // Completar perfil tras OAuth (username/first_name/last_name null)
        Route::patch('/user/complete-profile', [UserController::class, 'completeProfile']);

        // ─── Amistades ─────────────────────────────────────
        Route::post('/user/friend-requests', [FriendshipController::class, 'store']);
        Route::patch('/user/friend-requests/{request_uuid}', [FriendshipController::class, 'respond'])
            ->whereUuid('request_uuid');

        // ─── Notificaciones ────────────────────────────────
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        // ─── Clubes ────────────────────────────────────────
        Route::post('/clubs', [ClubController::class, 'store']);
        Route::patch('/clubs/{club}', [ClubController::class, 'update']);
        Route::delete('/clubs/{club}', [ClubController::class, 'destroy']);

        // ─── Miembros ──────────────────────────────────────
        Route::post('/clubs/{club}/members', [ClubMemberController::class, 'store']);
        Route::delete('/clubs/{club}/members/{member}', [ClubMemberController::class, 'destroy'])
            ->whereUuid('member');

        // ─── Roles ─────────────────────────────────────────
        Route::post('/clubs/{club}/roles', [ClubRoleController::class, 'store']);
        Route::patch('/clubs/{club}/roles', [ClubRoleController::class, 'update']);
        Route::delete('/clubs/{club}/roles/{role}', [ClubRoleController::class, 'destroy']);
        Route::post('/clubs/{club}/members/{user}/roles', [ClubMemberRoleController::class, 'store']);

        // ─── Categorías ────────────────────────────────────
        Route::post('/clubs/{club}/categories', [ClubCategoryController::class, 'store']);
        Route::patch('/clubs/{club}/categories', [ClubCategoryController::class, 'update']);
        Route::delete('/clubs/{club}/categories/{category}', [ClubCategoryController::class, 'destroy']);

        // ─── Canales ───────────────────────────────────────
        Route::post('/clubs/{club}/channels', [ClubChannelController::class, 'store']);
        Route::patch('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'update']);
        Route::delete('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'destroy']);

        // ─── Mensajes ──────────────────────────────────────
        Route::post('/channels/{channel}/messages', [ChannelMessageController::class, 'store']);

        // ─── DM ────────────────────────────────────────────
        Route::post('/dm-conversations', [DmConversationController::class, 'store']);
        Route::post('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'store']);
    });
});
