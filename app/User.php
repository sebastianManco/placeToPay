<?php

namespace App;

use App\Models\User as UserModel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * @deprecated Use App\Models\User instead.
 */
class User extends UserModel implements AuthenticatableContract
{
}

