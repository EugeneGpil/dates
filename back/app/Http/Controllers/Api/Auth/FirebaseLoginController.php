<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FirebaseLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

/**
 * Trade a Firebase id token for a Sanctum token — the only way an account comes into being,
 * and the only endpoint that answers without one.
 *
 * Signing in is signing up: there is no registration form, so a uid Firebase vouches for and
 * this database has never seen is a new user, not an error.
 */
class FirebaseLoginController extends Controller
{
    public function __construct(private readonly Auth $auth) {}

    public function __invoke(FirebaseLoginRequest $request): JsonResponse
    {
        try {
            $verified = $this->auth->verifyIdToken($request->validated('id_token'));
        } catch (FailedToVerifyToken) {
            return ApiResponse::error(__('messages.auth.invalid_token'), 401);
        }

        $claims = $verified->claims();

        // The profile is Google's to change, so it is refreshed on every sign-in: a new
        // photograph or a married name arrives here rather than staying as it was on the day
        // the account was made.
        $user = User::updateOrCreate(
            ['firebase_uid' => $claims->get('sub')],
            [
                'name' => $claims->get('name', ''),
                'email' => $claims->get('email'),
                'avatar' => $claims->get('picture'),
            ],
        );

        $this->adoptDevicePreferences($user, $request);

        return ApiResponse::success([
            'token' => $user->createToken('app')->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * Take the device's timezone and language, but only for a user who has none yet.
     *
     * Deliberately not part of the `updateOrCreate` above. Those two columns belong to the
     * *user*, not to the provider: once they have been set — on the first sign-in, and later
     * in settings — a sign-in from a borrowed laptop in another country must not quietly move
     * somebody's reminders to a zone they are not in. Keeping a stale zone is a wrong hour;
     * overwriting a chosen one is a wrong day, and the user did not ask for either.
     *
     * The consequence, and it is the right way round: a genuine move is carried by settings
     * (plan D7), which is a request that says so rather than a side effect of signing in.
     */
    private function adoptDevicePreferences(User $user, FirebaseLoginRequest $request): void
    {
        $preferences = [];

        if ($user->timezone === null) {
            $preferences['timezone'] = $request->validated('timezone') ?? config('dates.timezone');
        }

        if ($user->locale === null && $request->validated('locale') !== null) {
            $preferences['locale'] = $request->validated('locale');
        }

        if ($preferences !== []) {
            $user->update($preferences);
        }
    }
}
