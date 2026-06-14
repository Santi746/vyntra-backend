<?php

use App\Http\Controllers\AuthController;
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
use Illuminate\Support\Facades\Route;

// ============================================================
// RUTAS PÚBLICAS
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ============================================================
// RUTAS PROTEGIDAS (auth:sanctum)
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

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

        // -- Autenticación --
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // -- Usuario --
        Route::patch('/user', [UserController::class, 'updateProfile']);

        // -- Amistades --
        Route::post('/user/friend-requests', [FriendshipController::class, 'store']);
        Route::patch('/user/friend-requests/{request_uuid}', [FriendshipController::class, 'respond']);

        // -- Notificaciones --
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        // -- Clubes --
        Route::post('/clubs', [ClubController::class, 'store']);
        Route::patch('/clubs/{club}', [ClubController::class, 'update']);
        Route::delete('/clubs/{club}', [ClubController::class, 'destroy']);

        // -- Miembros --
        Route::post('/clubs/{club}/members', [ClubMemberController::class, 'store']);
        Route::delete('/clubs/{club}/members/{member}', [ClubMemberController::class, 'destroy']);

        // -- Roles --
        Route::post('/clubs/{club}/roles', [ClubRoleController::class, 'store']);
        Route::patch('/clubs/{club}/roles', [ClubRoleController::class, 'update']);
        Route::delete('/clubs/{club}/roles/{role}', [ClubRoleController::class, 'destroy']);
        Route::post('/clubs/{club}/members/{user}/roles', [ClubMemberRoleController::class, 'store']);

        // -- Categorías --
        Route::post('/clubs/{club}/categories', [ClubCategoryController::class, 'store']);
        Route::patch('/clubs/{club}/categories', [ClubCategoryController::class, 'update']);
        Route::delete('/clubs/{club}/categories/{category}', [ClubCategoryController::class, 'destroy']);

        // -- Canales --
        Route::post('/clubs/{club}/channels', [ClubChannelController::class, 'store']);
        Route::patch('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'update']);
        Route::delete('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'destroy']);

        // -- Mensajes --
        Route::post('/channels/{channel}/messages', [ChannelMessageController::class, 'store']);

        // -- DM --
        Route::post('/dm-conversations', [DmConversationController::class, 'store']);
        Route::post('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'store']);
    });
});
