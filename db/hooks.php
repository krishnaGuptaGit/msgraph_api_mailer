<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\after_config::class,
        'callback' => \local_msgraph_api_mailer\hook\after_config_callbacks::class . '::check_phpmailer_patch',
        'priority' => 500,
    ],
];
