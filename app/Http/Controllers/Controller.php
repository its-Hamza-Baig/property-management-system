<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Every controller authorises explicitly. Tenant isolation is not left
    // to the query scope alone.
    use AuthorizesRequests;
    use ValidatesRequests;
}
