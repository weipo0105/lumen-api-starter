<?php


namespace App\Providers;


use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CustomEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        if (Cache::tags(['authorization', 'user'])->has($identifier)) {
            return Cache::tags(['authorization', 'user'])->get($identifier);
        }

        $model = $this->createModel();
        $identifierName = $model->getAuthIdentifierName();

        $user = $this->newModelQuery($model)
            ->where($identifierName, $identifier)
            ->first();

        $exp = auth('api')->payload()->get('exp');
        $now = \Illuminate\Support\Carbon::now();

        Cache::tags(['authorization', 'user'])->put(
            $user->$identifierName,
            $user,
            $now->diffInSeconds(\Illuminate\Support\Carbon::createFromTimestamp($exp))
        );

        return $user;
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) ||
            (count($credentials) === 1 &&
                Str::contains($this->firstCredentialKey($credentials), 'password'))) {
            return;
        }

        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // Eloquent User "model" that will be utilized by the Guard instances.
        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (Str::contains($key, 'password')) {
                continue;
            }

            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}