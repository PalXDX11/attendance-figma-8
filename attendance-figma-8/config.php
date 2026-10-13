<?php
/*
 * Settings for emails and links. Fill this in once.
 * Do NOT share this file or upload it anywhere public once it has your app password in it.
 *
 * To really send emails with Gmail:
 *   1. Use a Gmail account just for the business.
 *   2. Turn on 2-Step Verification (Google Account -> Security).
 *   3. Google Account -> Security -> App passwords -> create one (16 letters).
 *   4. Put the Gmail address in 'username' and 'from_email', the app password in 'password',
 *      and change 'mode' to 'smtp'.
 *
 * While 'mode' is 'log', no email is sent. Each message is written to storage/mail.log instead,
 * so you can test the whole flow (copy the link from that file).
 */
return [
    // Address of this system as people type it in the browser, no slash at the end.
    // Example: 'http://localhost/attendance-figma-8'. Leave '' to detect it automatically.
    'app_url' => '',

    'mail' => [
        'mode'       => 'log',              // 'log' = write to storage/mail.log, 'smtp' = really send
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'encryption' => 'tls',              // 'tls' (port 587) or 'ssl' (port 465)
        'username'   => '',                 // your Gmail address
        'password'   => '',                 // the 16-letter App password (not your normal password)
        'from_email' => '',                 // usually the same as username
        'from_name'  => "Oppa's Samgyeopsal",
    ],
];
