<?php 

namespace App\Classes;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Calendar;
use Google_Service_Calendar_AclRule;
use Google_Service_Calendar_AclRuleScope;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;
use Google_Service_Calendar_EventReminders;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

use RRule\RRule;
use DateTime;


class MentoringScheduler
{  

    public function __construct()
    {
       
        $credential_file = base_path() . '/config/Google/config.json';

        $client = new Google_Client();
        $client->setApplicationName('Mentoring Calendar');
        $client->setAuthConfig($credential_file);
        $client->addScope(Google_Service_Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        try {

            $this->service = new Google_Service_Calendar($client);

        } catch (Google_Auth_Exception $e) {
            throw new Exception('Unable to connect to Google Calendar: ' . $e->getMessage());
        }

        

    }

    public function scheduleMentoring($mentor, $mentee)
    {
        return true;
    }

    public function createCalendar($email = null)

    {
        $calendar = new Google_Service_Calendar_Calendar();
        $rule = new Google_Service_Calendar_AclRule();
        $scope = new Google_Service_Calendar_AclRuleScope();

        $calendar->setSummary('SME Mentoring Calendar');
        $calendar->setTimeZone('Asia/Colombo');
        $calendar->setDescription('Calendar to mark avialabity and view SME Connect mentoring sessions.');
        $calendar->setLocation('Sri Lanka');

        $calendarId = $this->service->calendars->insert($calendar)->getId();

        //Make the calendar public
        $scope->setType('default');
        $rule->setScope($scope);
        $rule->setRole('reader');
        $this->service->acl->insert($calendarId, $rule);

        if($email != null) {
            $this->shareCalendar($email, $calendarId);
        }

        return $calendarId;
    }

    public function shareCalendar($email, $calendarId, $role = 'writer')

    {
        $rule = new Google_Service_Calendar_AclRule();
        $scope = new Google_Service_Calendar_AclRuleScope();
        $scope->setType("user");
        $scope->setValue($email);
        $rule->setScope($scope);
        $rule->setRole($role);

        $aclrule = $this->service->acl->insert($calendarId, $rule);

        if($aclrule != null) {
            return true;
        } else {
            return false;
        }
    }

    public function removeEmailFromClanedar($email, $calendarId)
    {
        //Get the ACL rule for the calendar
        $aclrules = $this->service->acl->listAcl($calendarId);
        $deleted = false;
        foreach($aclrules as $rule) {
            if($rule->getScope()->getValue() == $email) {
                $this->service->acl->delete($calendarId, $rule->getId());
                $deleted = true;
            }
        }

        return $deleted;
    }

    public function getEvents($calendarId)
    {
        $events = $this->service->events->listEvents($calendarId, array('q' => 'Mentoring Time'));
        $events = $events->getItems();
        
        print_r($events);
        return $events;
    }

    public function getEvent($calendarId, $eventId)
    {
        $event = $this->service->events->get($calendarId, $eventId);
        return $event;
    }

    public function getTimeSlots($calendarId)
    {
        try {

            $mentoring_times = $this->service->events->listEvents($calendarId, array('q' => 'Mentoring Time'))->getItems();
            $mentoring_sessions = $this->service->events->listEvents($calendarId, array('q' => 'Mentoring Session'))->getItems();

        } catch (Google_Service_Exception $e) {
            throw new Exception('Unable to get calendar times: ' . $e->getMessage());
        }
       
        $mentoring_sessions = array_filter($mentoring_sessions, function($mentoring_session) {
            return $mentoring_session->getStatus() == 'confirmed';
        });

        $all_events = [];
        $confirmed_events = [];
        $canceled_events = [];
        $recurring_events = [];

        $temp_dates = [];

        foreach ($mentoring_times as $event) {
            $all_events[] = $event;
            if ($event->getStatus() == 'confirmed') {
                array_push($confirmed_events, $event);
            } elseif ($event->getStatus() == 'cancelled') {
                array_push($canceled_events, $event);
            }
        }

        foreach ($confirmed_events as $event) {
            if ($event->getRecurrence() != null) {
                array_push($recurring_events, $event);
            } 
        }

        if(count($recurring_events) > 0) {
            foreach ($recurring_events as $event) {

                $rrule_string = $event->getRecurrence()[0];
                $dates = new RRule($rrule_string);
                $dates = $dates->getOccurrences(6);

                $dates = array_map(function($date) {
                    return $date->format('Y-m-d');
                }, $dates);

                $reccuring_event_id = $event->getId();

                foreach($canceled_events as $canceled_event) {
                    if($canceled_event['recurringEventId'] == $reccuring_event_id) {
                        $canceled_event_date = $canceled_event['originalStartTime']['dateTime'];
                        $canceled_event_date = new DateTime($canceled_event_date);
                        $canceled_event_date = $canceled_event_date->format('Y-m-d');
                        if(in_array($canceled_event_date, $dates)) {
                            unset($dates[array_search($canceled_event_date, $dates)]);
                        }
                    }
                }

                foreach($dates as $date) {

                    $start = $event->getStart()->getDateTime();
                    $start = Carbon::parse($start)->format('H:i');

                    $end = $event->getEnd()->getDateTime();
                    $end = Carbon::parse($end)->format('H:i');

                    $startDateTime = new DateTime($date . ' ' . $start);
                    $startDateTime = $startDateTime->format('Y-m-d\TH:i:s');
                    $endDateTime = new DateTime($date . ' ' . $end);
                    $endDateTime = $endDateTime->format('Y-m-d\TH:i:s');


                    $timeInfo = array(
                        'startDateTime' => $startDateTime,
                        'endDateTime' => $endDateTime,
                        'date' => $date,
                        'type' => 'recurring'
                    );
                    array_push($temp_dates, $timeInfo);
                }

            }
        } 

        foreach ($confirmed_events as $event) {
            if ($event->getRecurrence() == null) {

                $date = $event->getStart()['dateTime'];
                $date = new DateTime($date);
                $date = $date->format('Y-m-d');

                $start = $event->getStart()['dateTime'];
                $start = Carbon::parse($start);
                $end = $event->getEnd()['dateTime'];
                $end = Carbon::parse($end);

                //Remove timezone
                $startDateTime = $start->format('Y-m-d\TH:i:s');
                $endDateTime = $end->format('Y-m-d\TH:i:s');

                $timeInfo = array(
                    'date' => $date,
                    'startDateTime' => $startDateTime,
                    'endDateTime' => $endDateTime,
                    'type' => 'single'
                );

                array_push($temp_dates, $timeInfo);
            }
        }

        $time_slots = [];

        foreach($temp_dates as $temp_date) {

            $times = $this->calculateTimeSlots($temp_date['startDateTime'], $temp_date['endDateTime'], '1 Hour', $mentoring_sessions);

            if(in_array($temp_date['date'], array_column($time_slots, 'date'))) {
                $key = array_search($temp_date['date'], array_column($time_slots, 'date'));
                $time_slots[$key]['times'] = array_merge($time_slots[$key]['times'], $times);
            } else {
                $time_slots[] = [
                    'date' => $temp_date['date'],
                    'times' => $times
                ];
            }
        }

        //Order by date
        usort($time_slots, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        //Delete expired dates: 24 hours
        //$time_slots = array_filter($time_slots, function($time_slot) {
            //$date = new DateTime($time_slot['date']);
            //$date->modify('+1 day');
            //$date = $date->format('Y-m-d');
            //return $date >= date('Y-m-d');
        //});

        //Remove empty dates
        $time_slots = array_filter($time_slots, function($time_slot) {
            return count($time_slot['times']) > 0;
        });


        //Get only first 5 days
        $time_slots = array_slice($time_slots, 0, 5);


        return $time_slots;

    }


    public function calculateTimeSlots($start,  $end, $interval = '1 Hour', $mentoring_sessions) {

        $period = CarbonPeriod::create($start, $interval, $end);
        $period = $period->toArray();
        //remove last element
        array_pop($period);

        $mentoring_times = array_map(function($mentoring_session) {
            return Carbon::parse($mentoring_session['start']['dateTime'])->format('Y-m-d\TH:i:s');
        }, $mentoring_sessions);

        $times = [];
        foreach($period as $time) {
            $time = Carbon::parse($time)->format('Y-m-d\TH:i:s');
            $date = Carbon::parse($time)->format('Y-m-d');
            $availability = 0;

            if(!in_array($time, $mentoring_times) ) {
                $availability = 1;
            }
            $startTime = Carbon::parse($time)->format('h:i A');
            $endTime = Carbon::parse($time)->addHour()->format('h:i A');
            $times[] = [
                'startTime' => $time,
                'slotString' => $startTime . ' - ' . $endTime,
                'availability' => $availability
            ];
        }

        //Eligible date_time = current date_time + 24 hours
        $eligible_date_time = new DateTime();
        $eligible_date_time->modify('+24 hours');
        $eligible_date_time = $eligible_date_time->format('Y-m-d\TH:i:s');

        //Remove all slots before eligible date_time
        $times = array_filter($times, function($time) use ($eligible_date_time) {
            return $time['startTime'] >= $eligible_date_time;
        });

        return $times;
    }

    public function createMentoringSession($startDateTime, $calendarId, $session) {
        
        $event_name = 'Mentoring Session';

        $events = $this->service->events->listEvents($calendarId, array(
            'timeMin' => Carbon::parse($startDateTime)->toRfc3339String(),
            'timeMax' => Carbon::parse($startDateTime)->toRfc3339String(),
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'maxResults' => 1,
            'q' => $event_name,
        ));

        $events = $events->getItems();

        if(count($events) > 0) {
            return false;
        } 

        
        $description = "Mentoring Session with " . $session->mentee_name . " (" . $session->mentee_email . ")";
 

        $event = new Google_Service_Calendar_Event();
        $event->setSummary($event_name);
        $event->setDescription($description);
        //$event->setLocation('Mentoring Room');
        $event->setColorId('11');
        $event->setStart(new Google_Service_Calendar_EventDateTime(array(
            'dateTime' => Carbon::parse($startDateTime, 'Asia/Colombo')->toRfc3339String(),
            'timeZone' => 'Asia/Colombo',
        )));
        $event->setEnd(new Google_Service_Calendar_EventDateTime(array(
            'dateTime' => Carbon::parse($startDateTime, 'Asia/Colombo')->addHour()->toRfc3339String(),
            'timeZone' => 'Asia/Colombo',
        )));

        //Reminders
        $reminders = new Google_Service_Calendar_EventReminders();
        $reminders->setUseDefault(false);
        $reminders->setOverrides(array(
            array('method' => 'email', 'minutes' => 24 * 60),
            array('method' => 'popup', 'minutes' => 10),
        ));

        $event->setReminders($reminders);
       
        $event = $this->service->events->insert($calendarId, $event);
        
        return $event->getId();
    }

    public function cancelEvent($eventId, $calendarId) {
        
        $this->service->events->delete($calendarId, $eventId);
    }

    public function createSimpleSlot($calendarId, $dates, $startTime, $endTime) {
        //Rrule for the dates
        $rrule = 'RRULE:FREQ=WEEKLY;BYDAY=';
        foreach($dates as $date) {
            $rrule .= $date . ',';
        }
        $rrule = rtrim($rrule, ',');

        $event = new Google_Service_Calendar_Event();
        $event->setSummary('Mentoring Time');
        $event->setDescription('Scheduled by Simple Scheduler');
        $event->setColorId('11');
        $event->setStart(new Google_Service_Calendar_EventDateTime(array(
            'dateTime' => Carbon::parse($startTime, 'Asia/Colombo')->toRfc3339String(),
            'timeZone' => 'Asia/Colombo',
        )));
        $event->setEnd(new Google_Service_Calendar_EventDateTime(array(
            'dateTime' => Carbon::parse($endTime, 'Asia/Colombo')->toRfc3339String(),
            'timeZone' => 'Asia/Colombo',
        )));

        $event->setRecurrence(array($rrule));
        $event = $this->service->events->insert($calendarId, $event);
        return $event->getId();

    }


}