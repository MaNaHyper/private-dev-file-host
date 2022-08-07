<?php

namespace App\Http\Controllers;

use App\Models\Mentor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class MentorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'preferred_languages' => 'required|string',
            'sectors' => 'required|string',
            'expertise' => 'required|string',
            'previous_experience' => 'required|string',
            'industry_experience' => 'required|string',
            'aboutme' => 'required|string',
            'minimum_work_hours' => 'required|string',
            'user_cv' => 'required|mimes:csv,txt,xlx,xls,pdf,doc,docx|max:2048',
            'contact_number' => 'sometimes|nullable|string',
        ]);
        
        $username = $request->user->username;
        $uid = $request->user->id;
        $email = $request->user->email;
        $name = $request->user->firstname.' '.$request->user->lastname;

        $mentor = DB::table('mentors')->where('username', [$username])->first();

        if($mentor) {
            return response("user_exists", 422);
        }

        $cvFileName = time().'_'.$request->user_cv->getClientOriginalName();
        //http://localhost:8000/storage/uploads/usercvs/1631055540_1629395891_UAL%20CA%201.0V.docx
        $cvFilePath = $request->user_cv->storeAs('uploads/usercvs', $cvFileName, 'public');
        $preferred_languages = explode(":", $request->preferred_languages);
        $sectors = explode(":", $request->sectors);
        $expertise = explode(":", $request->expertise);

        return Mentor::create([
            'moodle_id' => $uid,
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'preferred_languages' => serialize($preferred_languages),
            'sectors' => serialize($sectors),
            'expertise' => serialize($expertise),
            'previous_experience' => $request->previous_experience,
            'industry_experience' => $request->industry_experience,
            'about' => $request->aboutme,
            'minimum_work_hours' => $request->minimum_work_hours,
            'cv_path' => $cvFilePath,
            'contact_number' => $request->contact_number,
        ]);


    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        
    }

    public function update_settings(Request $request) 
    {
        $request->validate([
            'preferred_languages' => 'required|string',
            'sectors' => 'required|string',
            'expertise' => 'required|string',
            'aboutme' => 'required|string',
            'make_public' => 'required|boolean',
            'accept_requests' => 'required|boolean',
            'contact_number' => 'sometimes|nullable|string',
        ]); 

        //$mentor_id = $request->mentor->id;
        $mentor = Mentor::find($request->mentor->id);

        if(!$mentor) {
            $response = ["isSaved" => false, "message" => "Mentor not found"];
            return response($response, 404);
        }

        $preferred_languages = explode(":", $request->preferred_languages);
        $sectors = explode(":", $request->sectors);
        $expertise = explode(":", $request->expertise);

        //print_r($preferred_languages);

        $mentor->preferred_languages = serialize($preferred_languages);
        $mentor->sectors = serialize($sectors);
        $mentor->expertise = serialize($expertise);
        $mentor->about = $request->aboutme;
        $mentor->make_public = $request->make_public;
        $mentor->accept_requests = $request->accept_requests;

        if($request->contact_number) {
            $mentor->contact_number = $request->contact_number;
        }


        if(!$mentor->save()){
            $response = ["isSaved" => false, "message" => "Error occurd while saving data."];
            return response($response, 500);
        }

        $response = ["isSaved" => true, "message" => "", "data" => $mentor];
        return response($response, 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function data(Request $request)
    {
    
        $mentor = $request->mentor;

        //Returning data to client
        $unserialize_out = unserialize_out($mentor, ['sectors', 'preferred_languages', 'expertise']);
        $response = ["isVerified" => true, "isFound" => true, "data" => $unserialize_out];
        return response($response, 200);
    }

    public function public_profile($username) {
        // $request->validate([
        //     "username" => "required|string"
        // ]);

        $where_matches = ["username" => $username, "profile_status" => "verified", "make_public" => true];
        $mentor = Mentor::where($where_matches)->first();

        if(!$mentor) {
            $response = ["isFound" => false, "message" => "Profile not found."];
            return response($response, 404);
        } 

        //Unsetting mentor related sensitive fileds.
        $safe_profile_out = filterMentorData($mentor);
        $unserialize_out = unserialize_out($safe_profile_out, ['sectors', 'preferred_languages', 'expertise']);

        $response = ["isFound" => true, "data" => $unserialize_out];
        return response($response, 200);

    }

    public function verify(Request $request)
    {
        $request->validate([
            'mentor_id' => 'required|integer'
        ]);

        $mentor = Mentor::find($request->mentor_id);
        if(!$mentor) {
            return response('mentor_not_found', 404);
        }
        $profile_status = $mentor->profile_status;

        if($profile_status === "verified") {
            return response('already_verified', 200);
        }

        $mentor->profile_status = 'verified';

        Mail::to($mentor->email)->send(new \App\Mail\MentorConfirmation($mentor->name));

        if(!$mentor->save()) {

            $response = ["isVerified" => true, "message"=> "Unknown error occurd while updating record."];
            return response($response, 422);
        }

       
    
        $response = ["isVerified" => true, "data"=> $mentor];
        return response($response, 200);
    }

    public function reject(Request $request) {

        $request->validate([
            'mentor_id' => 'required|integer',
            'reason' =>  'required|string'
        ]);

        $mentor = Mentor::find($request->mentor_id);
        if(!$mentor) {
            return response('mentor_not_found', 404);
        }

        $email = $mentor->email;
        $name = $mentor->name;

        if($mentor->profile_status == "verified") {
            $response = ["isRejected" => false, "message" => "Can't delete a verified account."];
            return response($response, 403);
        } 

        if(!$mentor->delete()) {
            $response = ["isRejected" => false, "message" => "Error occurd while deleting the record."];
            return response($response, 500);
        }

        Mail::to($mentor->email)->send(new \App\Mail\MentorRejection($mentor->name));
        $response = ["isRejected" => true];
        return response($response, 200);

    }

    public function pending_mentor_profiles(Request $request) {

        $where_matches = ["profile_status" => "pending"];
        $pending_mentor_profiles = [];

        

        foreach (Mentor::where($where_matches)->cursor() as $pending_mentor) { 
            $unserialize_out = unserialize_out($pending_mentor, ['sectors', 'preferred_languages', 'expertise']);
            array_push($pending_mentor_profiles, $unserialize_out);
        }


        $response = ["isFetched" => true, "data"=> $pending_mentor_profiles];
        
        return response($response, 200);

    }

    public function getTimeSlots(Request $request) {
        $request->validate([
            "mentor_id" => "required|integer",
            "date" => "required|date"
        ]);

        return response(get_available_timeslots($request->date, $request->mentor_id));
    }

     /**
     * Filter mentors by langs and areas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function filter(Request $request) 
    {
        $request->validate([
            "name" => "sometimes|string",
            "lang" => "sometimes|string",
            "expert_area" => "sometimes|string"
        ]);

        $req_lang = $request->lang;
        $req_area = $request->area_slug;

        $where_matches = ['profile_status' => 'verified', 'accept_requests' => true];

        $filterd_mentors = [];

        foreach (Mentor::where($where_matches)->cursor() as $verified_mentor) {  

            $langs = unserialize($verified_mentor->preferred_languages);
            $areas = unserialize($verified_mentor->expertise);

            if(in_array($req_lang, $langs) && in_array($req_area, $areas)) {
                $mentor = unserialize_out($verified_mentor, ['preferred_languages', 'sectors', 'expertise']);
                $mentor->cv_path = "";
                array_push($filterd_mentors, $mentor);
            }

        }  

        return response($filterd_mentors, 200);
    }

    public function advfilter(Request $request) 
    {
        // $request->validate([
        //     "name" => "sometimes|string",
        //     "lang" => "sometimes|string",
        //     "expert_area" => "sometimes|string"
        // ]);

        $where_matches = ['profile_status' => 'verified', 'make_public' => true];

        $mentors = Mentor::where($where_matches);

        if($request->has('name')) {
            $mentors = $mentors->where('name', 'like', '%'.$request->name.'%');
        } 

        if($request->has('lang')) {
            $mentors = $mentors->where('preferred_languages', 'like', '%'.$request->lang.'%');
        }

        if($request->has('expert_area')) {
            $mentors = $mentors->where('expertise', 'like', '%'.$request->expert_area.'%');
        }

        if($request->has('sector')) {
            $mentors = $mentors->where('sectors', 'like', '%'.$request->sector.'%');
        }

        $mentors = $mentors->cursor();

        $filterd_mentors = [];

        foreach ($mentors as $mentor) {  
            $mentor = unserialize_out($mentor, ['preferred_languages', 'sectors', 'expertise']);
            $mentor->cv_path = "";
            array_push($filterd_mentors, $mentor);
        }

        //Paginate the results
        $paginator = new LengthAwarePaginator($filterd_mentors, count($filterd_mentors), 8, $request->page);
        return response($paginator, 200);
    }

    public function random() {
        $mentors = Mentor::where('profile_status', 'verified')->where('accept_requests', true)->inRandomOrder()->limit(5)->get();

        $final_mentors = [];
        foreach ($mentors as $mentor) {
            $mentor = unserialize_out($mentor, ['preferred_languages', 'sectors', 'expertise']);
            $mentor->cv_path = "";
            array_push($final_mentors, $mentor);
        }
        return response(array_values($final_mentors), 200);
    }

    
}
