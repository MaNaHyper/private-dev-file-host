<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Mentor;

class MentorAuth
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
        //$moodle_id = $request->user->id;
        $moodle_user = $request->user;
        $mentor = Mentor::where('moodle_id', $moodle_user->id)->first();

        if(!$mentor) {
            $response = ["isVerified" => false, "isFound" => false];
            return response($response, 404);
        }

        //Update email, name if changed.
        $mentor->email = $moodle_user->email;
        $mentor->name = $moodle_user->firstname . " " . $moodle_user->lastname;
        $mentor->save();


        if($mentor->profile_status == "pending") {
            $response = ["isVerified" => false, "isFound" => true];
            return response($response, 401);
        }

        return $next($request->merge(['mentor' => $mentor]));
    }
}
