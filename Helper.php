<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\MentoringSession;
//use App\Models\MentorTimeSlots;
use App\Models\Mentor;

use RRule\RRule;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
* change plain number to formatted currency
*
* @param $number
* @param $currency
*/

function mdl_getAuthenticatedUser($session_key) {
    $usersession = DB::connection('moodle')->table('mdl_sessions')->where('sid', [$session_key])->first();
    if(!$usersession) {
        return false;
    }
    $userdata = DB::connection('moodle')->table('mdl_user')->where('id', [$usersession->userid])->first();
    if(!$userdata) {
        return false;
    }
    return $userdata;
}


function mdl_isAdmin($id) {
    $siteadmins = explode(",", DB::connection('moodle')->table('mdl_config')->where('name', 'siteadmins')->first()->value);
    return in_array($id, $siteadmins);
}

function mdl_getSession($request) {
    return $request->cookie('MoodleSession');
}

function mdl_isAthenticated($request) {
    $auth = mdl_getAuthenticatedUser(mdl_getSession($request));
    if(!$auth) {
        return false;
    }
    return true;
}


function mdl_filterUserData($userdata) {
    unset($userdata->password);
    unset($userdata->secret);
    return $userdata;
}

function filterMentorData($mentordata) {
    unset($mentordata->previous_experience);
    unset($mentordata->industry_experience);
    unset($mentordata->cv_path);
    unset($mentordata->minimum_work_hours);
    unset($mentordata->profile_status);
    unset($mentordata->make_public);
    unset($mentordata->accept_requests);
    unset($mentordata->contact_number);
    unset($mentordata->calendar_email);
   return $mentordata;
}

function get_mentor($id) {
    //$mentor = DB::connection('mysql')->table('mentors')->where('moodle_id', [$id])->first();
    //Get record
    $where_matches = ['moodle_id' => $id];
    $mentor = Mentor::where($where_matches)->first();

    if(!$mentor) {
        return false;
    }

    return $mentor;

}

function unserialize_out($stdClass, $elements) {
    foreach($elements as $element) {
     $stdClass->$element = unserialize($stdClass->$element);
    }
    return $stdClass;
}

function is_timeslot_available($date, $timeslot_id, $mentor_id) {

    $where_matches = ['mentor_id'=> $mentor_id, 'status' => 'active', 'date_str' => $date, 'timeslot_id' => $timeslot_id];
    $session = MentoringSession::where($where_matches)->first();

    if($session) {
        return false;
    }

    return true;

}

//ODL FUNCTION
/*function get_available_timeslots($date, $mentor_id) {

    $booked_slots_for_date = [];
    $slots = [];

    $timeslots = MentorTimeSlots::all();
    $where_matches = ['mentor_id' => $mentor_id, 'status' => 'active', 'date_str' => $date];
    
    foreach(MentoringSession::where($where_matches)->cursor() as $session) {
        array_push($booked_slots_for_date, $session->timeslot_id);
    }

    foreach($timeslots as $timeslot) {

        switch (in_array($timeslot->id, $booked_slots_for_date)) {
            case true:
                $status = 0;
                break;

            case false:
                $status = 1;
                break;
            
            default:
                $status = 0;
                break;
        }

        $timeslot_from = date("g:i A", strtotime($timeslot->from));
        $timeslot_to = date("g:i A", strtotime($timeslot->to));
        
        $timeslot_str = $timeslot_from." - ".$timeslot_to;

        $timeslot->str = $timeslot_str;
        $timeslot->availability = $status;
        array_push($slots, $timeslot);
    }

    return $slots;

}*/

function get_pending_sessions($mentor_id) {
   
    $where_matches = ['mentor_id' => $mentor_id];
    $pending_sessions = MentoringSession::where($where_matches)->get();

    return $pending_sessions;
}

function is_mentor($id) {
    $mentor = get_mentor($id);
    if(!$mentor) {
        return false;
    }
    return true;
}

function mdl_getUserExtraData($id) {
    //Gettig all exsiting fields from moodle
    $filds = DB::connection('moodle')->table('mdl_user_info_field')->get();
    $user_extra_data = [];
    foreach($filds as $field) {
        $user_extra_data[$field->shortname] = DB::connection('moodle')->table('mdl_user_info_data')->where('userid', [$id])->where('fieldid', [$field->id])->first();
    }
    unset($user_extra_data['contact_number']);
    return $user_extra_data;
}

function mdl_getUserData($id, $extra_data = false) {
    $userdata = DB::connection('moodle')->table('mdl_user')->where('id', [$id])->first();
    if(!$userdata) {
        return false;
    }
    if($extra_data) {
        $extra_user_data = mdl_getUserExtraData($id);
        $userdata->extra_data['user_dist'] = $extra_user_data['user_dist'];
	    $userdata->extra_data['business_type'] = $extra_user_data['business_type'];    
        $userdata->extra_data['user_gender'] = $extra_user_data['user_gender'];
    }
    
    $userdata->mentor = is_mentor($id);

    //Unset unwanted data

    unset($userdata->password);
    unset($userdata->secret);
    //unset($userdata->email);
    unset($userdata->address);
    unset($userdata->auth);
    unset($userdata->confirmed);
    unset($userdata->policyagreed);
    unset($userdata->deleted);
    unset($userdata->suspended);
    unset($userdata->mnethostid);
    //unset($userdata->lang);
    unset($userdata->theme);
    unset($userdata->timezone);
    unset($userdata->firstaccess);
    unset($userdata->lastaccess);
    unset($userdata->lastlogin);
    unset($userdata->currentlogin);
    unset($userdata->lastip);
    unset($userdata->description);
    unset($userdata->descriptionformat);
    unset($userdata->phone2);
    unset($userdata->phone1);
    unset($userdata->timecreated);
    unset($userdata->timemodified);
    unset($userdata->trustbitmask);
    unset($userdata->imagealt);
    unset($userdata->lastnamephonetic);
    unset($userdata->firstnamephonetic);
    unset($userdata->idnumber);
    unset($userdata->maildigest);
    unset($userdata->maildisplay);
    unset($userdata->mailformat);
    unset($userdata->msn);
    unset($userdata->skype);
    unset($userdata->yahoo);
    unset($userdata->aim);
    unset($userdata->icq);
    unset($userdata->alternatename);
    unset($userdata->url);
    unset($userdata->institution);
    unset($userdata->department);
    unset($userdata->emailstop);

    // unset($userdata->extra_data->about_me_custom);
    // unset($userdata->extra_data->business_address);
    // unset($userdata->extra_data->business_email);
    // unset($userdata->extra_data->business_decision_maker);
    // unset($userdata->extra_data->business_hours);
    // unset($userdata->extra_data->business_links);
    // unset($userdata->extra_data->business_name);
    // unset($userdata->extra_data->business_ownership_type);
    // unset($userdata->extra_data->business_type);
    // unset($userdata->extra_data->business_years);
    // unset($userdata->extra_data->user_gender);




    return $userdata;
}

function mdl_getEntrepreneurs() {
    //fieldid = 3 is the field id for the field "Entrepreneurs": is_entp
    $where_matches = ['fieldid' => 3, 'data' => 1];
    $entrepreneur_rows = DB::connection('moodle')->table('mdl_user_info_data')->where($where_matches)->get();
    
    //For each entrepreneur id, get the user data
    $entrepreneurs = [];
    foreach($entrepreneur_rows as $entrepreneur_row) {
        $entrepreneur = mdl_getUserData($entrepreneur_row->userid, true);
        if($entrepreneur) {
            array_push($entrepreneurs, $entrepreneur);
        }
    }
    return $entrepreneurs;
}

function paginate($items, $perPage = 5, $page = null, $options = [])
{
    $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    $items = $items instanceof Collection ? $items : Collection::make($items);
    return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, $options);
}


// Secure Image upload function with path managment.
function upload_image($image, $type) {

    //Root images folder
    $root_path = 'uploads/images';

    if($type == null || $type == '') {
        $type = '/common';
    } else {
        $type = '/'.$type;
    }

    if(!file_exists($root_path.$type)) {
        mkdir($root_path.$type, 0777, true);
    }

    $image_save_path = $root_path.$type;

    $validator = Validator::make(['image' =>  $image], [
        'image' => config('constants.rules.image')
    ]);

    if ($validator->fails()) {
        return false;
    }

    $image_name = time().'.'.$image->getClientOriginalExtension();
    $image->storeAs($image_save_path, $image_name, 'public');
    $url = asset('storage/'.$image_save_path.'/'.$image_name);
    return $url;
    
}

// Google client with service account to access google calendar
function get_google_client() {
    $cred = __DIR__ . '\\google\\config.json';

    $client = new Google_Client();
    $client->setApplicationName('Mentoring Calendar');
    $client->setAuthConfig($cred);
    $client->addScope(Google_Service_Calendar::CALENDAR);
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');


    //check if token is valid

    //Create a new calendar
    $service = new Google_Service_Calendar($client);

    //List all calendars
    //$calendarList = $service->calendarList->listCalendarList();

    
    //List all events 
    //$events = $service->events->listEvents($calendar_id);

    //Get specific event by name
    $calendar_id = 'mucnm59v97n5od19d7m2490tr4@group.calendar.google.com';
    $name = 'Mentoring Time';
    $event = $service->events->listEvents($calendar_id, ['q' => $name]);

    //Get only confirmed events

    $confirmed_events = [];
    $cancled_events = [];
    $cancled_events_dates = [];
    $final_events = [];


    foreach($event->getItems() as $item) {
        $all[] = $item;
        if($item->getStatus() == 'confirmed') {
            array_push($confirmed_events, $item);
        }
        if($item->getStatus() == 'cancelled') {
           array_push($cancled_events, $item);
           $original_date = $item['originalStartTime']['dateTime'];
           //Get only the date from the string by converting it to a date object
            $date = new DateTime($original_date);
            $date = $date->format('Y-m-d');
            array_push($cancled_events_dates, $date);
        }
    }

    //Checking if theres any recurring events
    $recurring_events = [];
    foreach($confirmed_events as $event) {
        if($event->getRecurrence() != null) {
            array_push($recurring_events, $event);
        }
    }

    if(count($recurring_events) > 0) {
        $recuring_event = $recurring_events[0];
        $rrule_string = $recuring_event->getRecurrence()[0];
        $dates = new RRule($rrule_string);
        $dates = $dates->getOccurrences(30);

        foreach($dates as $date) {
            $date = $date->format('Y-m-d');
            if(!in_array($date, $cancled_events_dates)) {
                array_push($final_events, $date);
            }
        }

        //print_r($dates);
        //print_r($dates);
        //Filter cancelled events
    } else {
        $final_events = $confirmed_events;
    }

    print_r($all);

    //print_r($final_events);

    //print_r($cancled_events);

    //Get rrule for each event
    // $rrule_events = [];
    // foreach($confirmed_events as $event) {
    //     $rrule = $event->getRecurrence();
    //     if($rrule) {
    //         array_push($rrule_events, $event);
    //     }
    // }
    //$rrule1 = new RRule($rrule_events[0]->getRecurrence());
    //Rrule string to array
    // $rrule_array = [];
    // foreach($rrule_events as $event) {
    //     $rrule = $event->getRecurrence();
    //     $rrule_array[$event->getId()] = new RRule($rrule);
    // }


    // $rrulestring = $rrule_events[0]->getRecurrence()[0];
    //Rrule string to object 
    //Parse rrule string to array

    //$modified_rrule_exdate = 'EXDATE=20190705T090000'
    
    // $rrule = new RRule($rrulestring);
    //$rrule->addExdate('2022-08-20 09:00:00');
    //Get all dates for the rrule
    // $dates = $rrule->getOccurrences(30);
    
    // print_r($dates);
    
    

    


    //Get freebusy for a specific time range for a specific calendar
    //$freebusy = $service->freebusy->query(['timeMin' => '2019-01-01T00:00:00Z', 'timeMax' => '2019-01-01T23:59:59Z', 'items' => [['id' => 'mucnm59v97n5od19d7m2490tr4@group.calendar.google.com']]]);

   

    //$freebusy_req = new Google_Service_Calendar_FreeBusyRequest();
    //$freebusy_req->setTimeMin(date(DateTime::ATOM,strtotime('2022/08/19')));
    //$freebusy_req->setTimeMax(date(DateTime::ATOM,strtotime('2022/08/29')));
    //$freebusy_req->setTimeZone('Asia/Colombo');
    //$item = new Google_Service_Calendar_FreeBusyRequestItem();
    //$item->setId($calendar_id);
    //$freebusy_req->setItems(array($item));
    //$query = $service->freebusy->query($freebusy_req);



    //
    // $freebusy = $service->freebusy->query($calendar_id, ['timeMin' => $timeMin, 'timeMax' => $timeMax]);



    //print_r(json_encode($event));

    
    //$calendar = new Google_Service_Calendar_Calendar();

   

    // $calendar->setSummary('Mentoring Calendar #1');
    // $calendar->setTimeZone('Asia/Colombo');
    // $calendar->setDescription('Mentoring Calendar #1');
    // $calendar->setLocation('Sri Lanka');

    // $created_calendar = $service->calendars->insert($calendar);
    // $calendar_id = $created_calendar->getId();

    // //print_r($created_calendar);

    // //List all calendars
    // $calendar_list = $service->calendarList->listCalendarList();

    // //Give access to other user to the calendar
    // //$calendar_id = '2bd7aijdqm14btviv5lq7qe4ac@group.calendar.google.com';
    // $user_email = 'mmallawa3062@gmail.com';
    // $role = 'user';
   
    // //Add email to the calendar using ACL
    // $rule = new Google_Service_Calendar_AclRule();
    // $scope = new Google_Service_Calendar_AclRuleScope();
    // $scope->setType("user");
    // $scope->setValue($user_email);
    // $rule->setScope($scope);
    // $rule->setRole("writer");

    // $service->acl->insert($calendar_id, $rule);

    //List all ACLs for the calendar


    //$service->acl->insert($calendar_id, $acl);


    //print_r($calendar_list);



    //print_r($client);

    return $event;
}



