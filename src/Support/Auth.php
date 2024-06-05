<?php

namespace Fleetbase\Support;

use Fleetbase\Models\ApiCredential;
use Fleetbase\Models\Company;
use Fleetbase\Models\CompanyUser;
use Fleetbase\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as Authentication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class Auth extends Authentication
{
    /**
     * Create company and user.
     *
     * @param array $owner   The owner to be created
     * @param array $company The company to be created
     *
     * @return \Fleetbase\Models\User;
     */
    public static function register($owner, $company)
    {
        // email is always lowercase
        if (isset($owner['email'])) {
            $owner['email'] = strtolower($owner['email']);
        }

        if (isset($company['email'])) {
            $company['email'] = strtolower($company['email']);
        }

        $owner   = User::create($owner);
        $company = Company::create($company)
            ->setOwner($owner)
            ->saveInstance();

        $owner->assignCompany($company);

        return $owner;
    }

    /**
     * Set session variables for user.
     *
     * @param User|ApiCredential|null $user
     */
    public static function setSession($user = null, $login = false): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user instanceof ApiCredential) {
            $apiCredential = $user;
            session(['company' => $apiCredential->company_uuid, 'user' => $apiCredential->user_uuid]);
            // user couldn't be loaded, fallback with api credential if applicable
            $user = User::find($apiCredential->user_uuid);

            // Set is admin if user of api credential is admin
            if ($user) {
                session(['is_admin' => $user->isAdmin()]);
            }

<<<<<<< HEAD
            // track last usage of api credential
            $apiCredential->trackLastUsed();

=======
>>>>>>> origin/main
            return true;
        }

        session(['company' => $user->company_uuid, 'user' => $user->uuid, 'is_admin' => $user->isAdmin()]);
        if ($login) {
            Authentication::login($user);
        }

        return true;
    }

    /**
     * Set session variables for api credentials being used.
     *
     * @param ApiCredential $apiCredential
     *
     * @return bool
     */
    public static function setApiKey($apiCredential)
    {
        // If sanctum token indicate in session
        if ($apiCredential instanceof PersonalAccessToken) {
            session([
                'is_sanctum_token' => true,
                'api_credential'   => $apiCredential->id,
                'api_key'          => $apiCredential->token,
                'api_key_version'  => (string) $apiCredential->created_at,
                'api_secret'       => $apiCredential->token,
                'api_environment'  => 'live',
                'api_test_mode'    => false,
            ]);

            return true;
        }

        session([
            'api_credential'  => $apiCredential->uuid,
            'api_key'         => $apiCredential->key,
            'api_key_version' => (string) $apiCredential->created_at,
            'api_secret'      => $apiCredential->secret,
            'api_environment' => $apiCredential->test_mode ? 'test' : 'live',
            'api_test_mode'   => $apiCredential->test_mode,
        ]);

        return true;
    }

    /**
     * Get the current api key.
     */
    public static function getApiKey(): ?ApiCredential
    {
        if (!session('api_credential')) {
            return null;
        }

        return ApiCredential::where('uuid', session('api_credential'))->first();
    }

    /**
     * Checks the request header for sandbox headers if to set and switch to the sandbox database,
     * or uses the `ApiCredential` provided to set sandbox session.
     *
     * @param Request       $request
     * @param ApiCredential $apiCredential
     *
     * @return bool
     */
    public static function setSandboxSession($request, $apiCredential = null)
    {
        $isSandbox       = $request->header('Access-Console-Sandbox') ?? Utils::get($apiCredential, 'test_mode', false);
        $apiCredentialId = $request->header('Access-Console-Sandbox-Key') ?? Utils::get($apiCredential, 'uuid', false);
        $sandboxSession  = [];

        // if is sandbox environment switch to the sandbox database
        if ($isSandbox) {
            config(['database.default' => 'sandbox']);
            $sandboxSession['is_sandbox'] = (bool) $isSandbox;

            if ($apiCredentialId) {
                $sandboxSession['sandbox_api_credential'] = $apiCredentialId;
            }
        }

        session($sandboxSession);

        return true;
    }

    /**
     * Get the session company.
     */
    public static function getCompany($select = '*'): ?Company
    {
        if (!session('company')) {
            return null;
        }

        return Company::select($select)->where('uuid', session('company'))->first();
    }

    /**
     * Retrieve the currently authenticated user by checking multiple sources.
     *
     * This method extends the base Laravel Auth functionality to ensure robust user retrieval.
     * It attempts to fetch the user in the following order:
     * 1. Directly from the extended Auth class's user retrieval method.
     * 2. From the session, if the user's UUID is stored there.
     * 3. Returns null if no user can be authenticated through previous methods.
     *
     * @return User|null returns an instance of the User model if authenticated, or null if no user is authenticated
     */
    public static function getUserFromSession()
    {
        // Attempt to retrieve the user using the extended Auth class method
        $user = auth()->user();
        if ($user instanceof User) {
            return $user;
        }

        // Check if the UUID is stored in the session and fetch the user from the database
        if (session()->has('user')) {
            return User::where('uuid', session('user'))->first();
        }

        // Return null if no user could be authenticated
        return null;
    }

    /**
     * Verifies a password against a hash.
     */
    public static function checkPassword(string $pw1, string $pw2): bool
    {
        return Hash::check($pw1, $pw2);
    }

    /**
     * Checks if password is invalid.
     */
    public static function isInvalidPassword(string $pw1, string $pw2): bool
    {
        return !static::checkPassword($pw1, $pw2);
    }

    /**
     * Retrieves the associated company session for the given user.
     *
     * This method attempts to fetch a company based on the UUID stored in the user's company_uuid property.
     * If no company is found or the UUID is invalid, it falls back to the user's first associated company.
     *
     * @param User $user the user for whom to retrieve the company session
     *
     * @return Company|null the Company object if found, or null if not
     */
    public static function getCompanySessionForUser(User $user): ?Company
    {
        if (Str::isUuid($user->company_uuid)) {
            $company = Company::where('uuid', $user->company_uuid)->first();
            if ($company) {
                return $company;
            }
        }

        // fallback to get user's first company
        $userCompany = CompanyUser::where('user_uuid', $user->uuid)->first();
        if ($userCompany) {
            $company = Company::where('uuid', $userCompany->company_uuid)->first();
            if ($company) {
                return $company;
            }
        }

        return null;
    }
}
