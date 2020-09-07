<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

class CheckAge
{
    /**
     * Handle an incoming request.
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $_COOKIE['token'] ?? '' ;
        $admin_id =  $_COOKIE['userId'] ?? '';
        $retoken = Redis::get('admin_token_'.$admin_id) ;
        if ($token == $retoken && !empty($retoken)){
            return $next($request);
        }
        return redirect('/login');
    }


}
