<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MentorSessionRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $session;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        //
        $this->session=$data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.mentor_session_request')->from('noreply@smeconnect.lk', 'SMEConnect Mentoring')->subject("New mentoring session request.");
    }
}
