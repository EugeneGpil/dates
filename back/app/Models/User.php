<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The root of everything in this app: every date and every device token hangs off a user, and
 * no query runs unscoped (plan §4). The identity comes from Firebase — there is no password
 * column to hash and no email to verify.
 */
#[Fillable(['firebase_uid', 'name', 'email', 'avatar', 'locale', 'timezone'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
}
