<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body>
<p>Dear {{ $session->mentor_name }},</p>
<p><br />This is to notify you that you have received a mentoring request from {{ $session->mentee_name }} for a mentoring session on {{ date('d-m-Y', strtotime($session->timeslot_start)) }} {{ $session->timeslot_str }}.</p> 
<p>Please login to the <a href="https://mentoring.smeconnect.lk/#/dashboard/mentor/sessions">dashboard</a> to accept or reject the request.</p>
<p>Thank you.<br />Kind Regards.</p>

</body>
</html>


