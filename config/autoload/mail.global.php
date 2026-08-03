<?php

/**
 * The application's mail identity.
 *
 * All mail is sent through symfony/mailer via the 'SionModel\MailTransport'
 * service, which builds the transport from the top-level `smtp_options` block
 * in local.php (in the capsule: docker/local.docker.php, pointing at Mailpit).
 *
 * This block is what SionModel\Mailing\Mailer::createEmail() stamps on
 * outgoing application mail (e.g. the Books overdue notices). The JUser
 * sign-in mails and the exception notifier address their own messages.
 */

return [
    'sion_model' => [
        'mail' => [
            'from' => 'webmaster@schoenstatt.link',
            'from_name' => 'Schoenstatt Link',
            'bcc' => ['webmaster@schoenstatt.link'],
        ],
    ],
];
