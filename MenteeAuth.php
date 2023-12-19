<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Mentee;

class MenteeAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $moodle_id = $request->user->id;
        $userdata = mdl_getUserData($moodle_id, true);
        //Update or create mentee
        $match = ['moodle_id' => $moodle_id];
        $mentee = Mentee::updateOrCreate($match, [
            'moodle_id' => $moodle_id,
            'username' => $userdata->username,
            'email' => $userdata->email,
            'name' => $userdata->firstname.' '.$userdata->lastname,
            'gender' => $userdata->extra_data['user_gender']->data,
            'sector' => 'null',
            'business_nature' => $userdata->extra_data['business_type']->data,
            'business_description' => 'null',
            'assistance' => 'null',
            'mentoring_requirement' => 'null',
        ]);

        return $next($request->merge(['mentee' => $mentee]));
    }
}
