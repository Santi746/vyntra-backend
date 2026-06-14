<?php

namespace App\Enums;

use App\Models\User;

/**
 * Permisos de club en formato bitmask.
 *
 * Los valores coinciden 1:1 con `vyntra-frontend/src/shared/constants/permissions.js`.
 * Cualquier cambio aquí DEBE reflejarse en el frontend y viceversa.
 *
 * Operadores:
 *   - OR (|)  para acumular permisos de varios roles en uno solo
 *   - AND (&) para verificar si un permiso está presente
 *
 * ADMINISTRATOR (1 << 30) actúa como bypass total: cualquier permiso pasa
 * la verificación cuando está presente en el bitmask.
 *
 * @see User::hasClubPermission()
 */
class ClubPermission
{
    const VIEW_CHANNELS = 1 << 0;

    const MANAGE_CHANNELS = 1 << 1;

    const MANAGE_ROLES = 1 << 2;

    const MANAGE_CLUB = 1 << 3;

    const CREATE_INVITE = 1 << 4;

    const CHANGE_NICKNAME = 1 << 5;

    const MANAGE_NICKNAMES = 1 << 6;

    const KICK_MEMBERS = 1 << 7;

    const BAN_MEMBERS = 1 << 8;

    const SEND_MESSAGES = 1 << 9;

    const EMBED_LINKS = 1 << 10;

    const ATTACH_FILES = 1 << 11;

    const ADD_REACTIONS = 1 << 12;

    const MANAGE_MESSAGES = 1 << 13;

    const MENTION_EVERYONE = 1 << 14; // posiblemente no implementado en MVP

    const CONNECT = 1 << 15;

    const MUTE_MEMBERS = 1 << 16;

    const DEAFEN_MEMBERS = 1 << 17;

    const ADMINISTRATOR = 1 << 30;
}
