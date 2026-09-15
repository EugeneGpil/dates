<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn Laravel's password-based `users` table into the one this app signs in against.
 *
 * There is no registration form and no password: a row is created the first time somebody
 * signs in with Google, so signing in *is* signing up, and the identity lives in Firebase.
 * What is left here is the profile Firebase hands over, plus the two preferences the server
 * needs when nobody is holding the phone — see below.
 *
 * Written as an alter rather than by editing the skeleton's create migration, so that a
 * database which has already run the phase-1 chain arrives at the same schema as a fresh one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Firebase's `sub` claim — the account, as far as this app is concerned. Unique
            // because it is what `auth/firebase` matches on to decide between creating a user
            // and recognising one.
            $table->string('firebase_uid')->unique()->after('id');
            $table->string('avatar')->nullable()->after('email');

            // The chosen language, kept on the account rather than on the device, because a
            // notification is worded by the scheduler at nine in the morning with no request
            // and no `Accept-Language` to read (plan D5). A full tag (`ru-RU`), which is the
            // client's own spelling round-tripping through the server.
            $table->string('locale', 16)->nullable()->after('avatar');

            // The zone the user's dates are counted in (plan D7). "9 days until" and "Today"
            // are answers about a calendar somebody is standing in, so a single server-wide
            // zone would tell a user in Vladivostok the wrong day. IANA identifiers are at
            // most 32 characters today; 64 is room for the ones that come later.
            $table->string('timezone', 64)->nullable()->after('locale');

            // Google always hands over an address, but the account is keyed on the uid, not on
            // this — so a provider that does not supply one must not be a failed sign-in.
            $table->string('email')->nullable()->change();

            $table->dropColumn(['email_verified_at', 'password']);
            $table->dropRememberToken();
        });

        // Nothing can reset a password that does not exist. Left behind by the skeleton, and a
        // table that implies an account can be reached by email is worse than no table.
        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('password')->default('')->after('email_verified_at');
            $table->rememberToken();
            $table->dropUnique(['firebase_uid']);
            $table->dropColumn(['firebase_uid', 'avatar', 'locale', 'timezone']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
