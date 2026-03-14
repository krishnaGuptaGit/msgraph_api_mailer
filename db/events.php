<?php
defined('MOODLE_INTERNAL') || die();

// No event observers needed.
// All outgoing emails are intercepted via local_msgraph_api_mailer_phpmailer_init()
// in lib.php, which email_to_user() calls for every Moodle email regardless of type.
// The previous message_sent observer was redundant and caused duplicate emails
// for messaging notifications that also route through email_to_user().
$observers = [];
