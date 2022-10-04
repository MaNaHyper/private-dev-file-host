<?php

namespace App\Http\Controllers;

use App\Models\MentoringSession;
//use App\Models\MentorTimeSlots;
use App\Models\Mentor;
use App\Models\Mentee;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Mail;

use App\Classes\MentoringScheduler;


class MentoringSessionController extends Controller
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

    public function stats() 
    {
        $mentor_count = Mentor::count();
        $mentee_count = Mentee::count();
        $session_count = MentoringSession::where(["status" => "completed"])->get()->count();

        $response = ["mentor_count" => $mentor_count, "mentee_count" => $mentee_count, "session_count" => $session_count ];
        return response($response, 200);
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
            'mentor_id' => 'required|integer'
        ]);

        $where_matches = ['id' => $request->mentor_id, 'profile_status' => 'verified', 'accept_requests' => true];
        $mentor = Mentor::where($where_matches)->first();

        if(!$mentor) {
            $response = ['isCreated' => false, 'message' => 'Mentor not found'];
            return response($response, 422);
        }

        if($mentor->moodle_id == $request->mentee->moodle_id) {
            $response = ['isCreated' => false, 'message' => "You can't select yourself as the mentor."];
            return response($response, 422);
        }

        // Looking for maximum concurrent pending/active requests from mentee. Only 2 allowed.
        $mentee_active_session_count = MentoringSession::where(["mentee_id" => $request->mentee->id, "status" => "active"])->get()->count();
        $mentee_pending_session_count = MentoringSession::where(["mentee_id" => $request->mentee->id, "status" => "pending"])->get()->count();
        
        $mentee_all_session_count = $mentee_active_session_count + $mentee_pending_session_count;

        if($mentee_all_session_count > 2) {
            $response = ['isCreated' => false, 'message' => 'Maximum concurrent active/pending sessions exceeded 3.'];
            return response($response, 422);
        }   

        $mentor_capable_areas = unserialize($mentor->expertise);
        $mentor_capable_languages = unserialize($mentor->preferred_languages);

        $request->validate([
            'language' => ['required', 'string', Rule::in($mentor_capable_languages)],
            'area' => ['required', 'string', Rule::in($mentor_capable_areas)],
            'timeslot_start' => 'required|string',
            'timeslot_str' => 'required|string',
            'description' => 'required|string|max:500',
        ]);

        //Check if mentor is available for the timeslot
        $sessions = MentoringSession::where(["mentor_id" => $mentor->id, "status" => "active"])->get();
        $mentor_timeslots = [];
        foreach($sessions as $session) {
            $mentor_timeslots[] = $session->timeslot_start;
        }

        if(in_array($request->timeslot_start, $mentor_timeslots)) {
            $response = ['isCreated' => false, 'message' => 'Mentor is not available for the timeslot.'];
            return response($response, 422);
        }

        $timeslot_str = $request->timeslot_str;

        $mentee_id = $request->mentee->id;
        $mentee_mid = $request->mentee->moodle_id;
        $mentee_name = $request->mentee->name;
        $mentee_email = $request->mentee->email;
        $mentee_username = $request->mentee->username;

        $ref_id = "SMEM".random_int(100000000, 9999999999);

        $session = MentoringSession::create([
            'mentee_id' => $mentee_id,
            'mentee_mid' => $mentee_mid,
            'mentee_name' => $mentee_name,
            'mentee_username' => $mentee_username,
            'mentee_email' => $mentee_email,

            'mentor_id' => $mentor->id,
            'mentor_mid' => $mentor->moodle_id,
            'mentor_name' => $mentor->name,
            'mentor_username' => $mentor->username,
            'mentor_email' => $mentor->email,
            'timeslot_start' => $request->timeslot_start,
            'language' => $request->language,
            'area' => $request->area,
            'timeslot_str' => $timeslot_str,
            'reference_id' => $ref_id,
            'description' => $request->description

        ]);

        if(!$session) {
            $response = ['isCreated' => false, 'message' => "Unable to create the session", 'data' => $session];
            return response($response, 422);
        }

        Mail::to($mentee_email)->send(new \App\Mail\MentorSessionRequest($session));

        $response = ['isCreated' => true, 'message' => '', 'data' => $session];
        return response($response, 201);

    }


    public function getMenteeSessions(Request $request) {
        $mentee = $request->mentee;

        if(!$mentee) {
            $response = ["fetchStatus" => false, "message"=> "Mentee session not found."];
            return response($response, 422);
        }

        $where_matches = ["mentee_id"=> $mentee->id];

        $mentee_sessions = MentoringSession::where($where_matches)->latest('updated_at')->get();

        $response = ["fetchStatus" => true, "sessions" => $mentee_sessions ];
        return response($response, 200);
    
    }

    public function getMentorSessions(Request $request) {
        $mentor = $request->mentor;

        if(!$mentor) {
            $response = ["fetchStatus" => false, "message"=> "Mentor session not found."];
            return response($response, 422);
        }

        $where_matches = ["mentor_id"=> $mentor->id];

        $mentor_sessions = MentoringSession::where($where_matches)->latest('updated_at')->get();

        $response = ["fetchStatus" => true, "sessions" => $mentor_sessions ];
        return response($response, 200);
    
    }

    public function cancelSessionMentee(Request $request) {

            $request->validate(["session_id" => "required|integer", "reason" => "required|string"]);

            $where_matches = ["mentee_id" => $request->mentee->id, "id" => $request->session_id];
            $session = MentoringSession::where($where_matches)->first();

            if(!$session) {
                $response = ["isCanceled" => false, "message" => "The Session ID is not belongs to this mentee."];
                return response($response, 403);
            }

            $session->status = "canceled";
            $session->canceled_by = "mentee";
            $session->updated_at = time();

            $event_id = $session->calendar_event_id;

            if(!$session->save()) {
                $response = ["isCanceled" => false, "message" => "Unknown error occurd while updating session record"];
                return response($response, 422);
            }

            //Checking if there is a calendar event id: null means the mentor still not accepted the session request.
            if($event_id) {
                try {
                $mentor_id = $session->mentor_id; 
                $mentor = Mentor::find($mentor_id);
                $calendar_id = $mentor->calendar_id;

               
                    $scheduler = new MentoringScheduler();
                    $scheduler->cancelEvent($event_id, $calendar_id);
                } catch (\Throwable $th) {
                    
                }
                
            }

            //Get calendar id 
            
            if(!$session->save()) {
                $response = ["isCanceled" => false, "message" => "Unknown error occurd while updating session record"];
                return response($response, 422);
            }
            

            $response = ["isCanceled" => true, "message" => $session];
            return response($response, 200);
    }

    public function cancelSessionMentor(Request $request) {

        $request->validate(["session_id" => "required|integer", "reason" => "required|string"]);

        $mentor_id = $request->mentor->id;
        $where_matches = ["mentor_id" => $mentor_id, "id" => $request->session_id];
        $session = MentoringSession::where($where_matches)->first();

        if(!$session) {
            $response = ["isCanceled" => false, "message" => "The Session ID is not belongs to this mentor."];
            return response($response, 403);
        }

        $session->status = "canceled";
        $session->canceled_by = "mentor";
        $session->updated_at = time();

        $mentor = Mentor::find($mentor_id);
        $calendar_id = $mentor->calendar_id;
        $event_id = $session->calendar_event_id;

        if($event_id) {
            $scheduler = new MentoringScheduler();
            try {
                $scheduler->cancelEvent($event_id, $calendar_id);
            } catch (\Throwable $th) {
                //throw $th;
            }
            
        }

        if(!$session->save()) {
            $response = ["isCanceled" => false, "message" => "Unknown error occurd while updating session record"];
            return response($response, 422);
        }

        $response = ["isCanceled" => true, "message" => $session];
        return response($response, 200);
    }

    public function rejectSessionMentor(Request $request) {

        $request->validate(["session_id" => "required|integer"]);

        $where_matches = ["mentor_id" => $request->mentor->id, "id" => $request->session_id];
        $session = MentoringSession::where($where_matches)->first();

        if(!$session) {
            $response = ["isRejected" => false, "message" => "The Session ID is not belongs to this mentor."];
            return response($response, 403);
        }

        $session->status = "rejected";
        $session->updated_at = time();

        if(!$session->save()) {
            $response = ["isRejected" => false, "message" => "Unknown error occurd while updating session record"];
            return response($response, 422);
        }

        $response = ["isRejected" => true, "message" => $session];

        //Notifying the mentee about the rejection.
        $mentee_email = $session->mentee_email;
        
        Mail::to($mentee_email)->send(new \App\Mail\MenteeSessionRequestReject($session));
        return response($response, 200);
    }

    public function acceptSessionMentor(Request $request) {

        $request->validate(["session_id" => "required|integer"]);

        $mentor_id = $request->mentor->id;

        $where_matches = ["mentor_id" => $mentor_id, "id" => $request->session_id];
        $session = MentoringSession::where($where_matches)->first();

        if(!$session) {
            $response = ["isAccepted" => false, "message" => "The Session ID is not belongs to this mentor."];
            return response($response, 403);
        }

        
        $mentor = Mentor::find($session->mentor_id);
        $calendar_id = $mentor->calendar_id;
        $start_time = $session->timeslot_start;

        $mentor_contact_number = $mentor->contact_number ?? "";

        $session->status = "active";
        $session->mentor_contact_number = $mentor_contact_number;
        $session->updated_at = time();

        //Create the calendar event for the session.
        //Create Google Calendar Event
        $scheduler = new MentoringScheduler();
        $event_id = $scheduler->createMentoringSession($start_time, $calendar_id, $session);

        if($event_id) {
            $session->calendar_event_id = $event_id;
        }


        if(!$session->save()) {
            $response = ["isAccepted" => false, "message" => "Unknown error occurd while updating session record."];
            return response($response, 422);
        }

        $response = ["isAccepted" => true, "message" => $session];

        //Notifying the mentee about the accept.
        $mentee_email = $session->mentee_email;
        
        Mail::to($mentee_email)->send(new \App\Mail\MenteeSessionRequestAccept($session));
        return response($response, 200);
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
        //
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

    public function sessionMarkComplete(Request $request) {
        $request->validate([
            "session_id" => "required|integer",
            "rating" => "required|integer|between:1,5",
            "feedback" => "sometimes|nullable|string"
        ]);

        $where_matches = ["mentee_id" => $request->mentee->id, "id" => $request->session_id];
        $session = MentoringSession::where($where_matches)->first();

        $mentor = Mentor::find($session->mentor_id);


        if(!$session) {
            $response = ["isRated" => false, "message" => "The Session ID is not belongs to this mentee."];
            return response($response, 403);
        }

        if(!$mentor) {
            $response = ["isRated" => false, "message" => "The Mentor ID is not found."];
            return response($response, 403);
        }



        $session->status = "completed";
        $session->mentee_rating = $request->rating;
        if($request->feedback) {
            $session->mentee_feedback = $request->feedback;
        }

        $scheduler = new MentoringScheduler();
        $event_id = $session->calendar_event_id;

        if($event_id) {
            $scheduler->cancelEvent($event_id, $mentor->calendar_id);
        }

        $session->updated_at = time();

        if(!$session->save()) {
            $response = ["isRated" => false, "message" => "Unknown error occurd while updating session record"];
            return response($response, 422);
        }

        $response = ["isRated" => true, "message" => $session];
        return response($response, 200);
    }

    /**
     * Update with mentor feedback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */ 

    public function feedbackSessionMentor(Request $request) {
        $request->validate([
            "session_id" => "required|integer",
            "rating" => "required|integer|between:1,5",
            "feedback" => "required|string|max:500",
        ]);

        $where_matches = ["mentor_id" => $request->mentor->id, "id" => $request->session_id];
        $session = MentoringSession::where($where_matches)->first();

        if(!$session) {
            $response = ["isRated" => false, "message" => "Mentoring session not found."];
            return response($response, 404);
        }

        $session->mentor_rating = $request->rating;
        $session->mentor_feedback = $request->feedback;

        if(!$session->save()) {
            $response = ["isRated" => false, "message" => "Unknown error occurd while updating session record"];
            return response($response, 422);
        }

        $response = ["isRated" => true, "message" => $session];
        return response($response, 200);

    }

    
}
