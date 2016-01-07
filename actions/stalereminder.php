<?php

if (!defined('GNUSOCIAL')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/mail.php';

class StaleReminderAction extends Action
{

    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
        }

        $user  = common_current_user();
        $other = User::getKV('nickname', $this->arg('nickname'));

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('showstream',
                array('nickname' => $other->nickname)));
        }

        // CSRF protection
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
        }

        if (!$other->email) {
            // TRANS: Client error displayed trying to reminder a user that hasn't confirmed or set their email address.
            $this->clientError(_('This user hasn\'t confirmed or set their email address yet.'));
        }

        $this->notify($user, $other);

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending a reminder.
            $this->element('title', null, _('Reminder sent'));
            $this->elementEnd('head');
            $this->elementStart('body');
            // TRANS: Confirmation text after sending a reminder.
            $this->element('p', array('id' => 'reminder_response'), _('Reminder sent!'));
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            // display a confirmation to the user
            common_redirect(common_local_url('showstream',
                                             array('nickname' => $other->nickname)),
                            303);
        }
    }

     /**
     * Do the actual notification
     *
     * @param class $from reminderer
     * @param class $to reminderee
     *
     * @return nothing
     */
    function notify($from, $to)
    {
        if ($to->id != $from->id) {
            if ($to->email) {
                // TODO
                common_switch_locale($to->language);

                // TRANS: Subject for 'reminder' notification email.
                // TRANS: %s is the sender.
                $subject = sprintf(_('%s would like to see you post on GNU social'), $from->nickname);
                $from_profile = $from->getProfile();

                // TRANS: Body for 'reminder' notification email.
                // TRANS: %1$s is the sender's long name, $2$s is the receiver's nickname,
                // TRANS: %3$s is a URL to post notices at.
                $body = sprintf(_("%1\$s (%2\$s) is wondering what you are up to ".
                                  "these days and is inviting you to post some news.\n\n".
                                  "So let's hear from you :)\n\n".
                                  "%3\$s\n\n".
                                  "Don't reply to this email; it won't get to them."),
                                $from_profile->getBestName(),
                                $from->nickname,
                                common_local_url('all', array('nickname' => $to->nickname))) .
                        mail_footer_block();
                common_switch_locale();

                $headers = $this->mail_prepare_headers('nudge', $to->nickname, $from->nickname);

                return mail_to_user($to, $subject, $body, $headers);
            }
        }
    }

    /**
     * Prepare the common mail headers used in notification emails
     *
     * @param string $msg_type type of message being sent to the user
     * @param string $to       nickname of the receipient
     * @param string $from     nickname of the user triggering the notification
     *
     * @return array list of mail headers to include in the message
     */
    function mail_prepare_headers($msg_type, $to, $from)
    {
        $headers = array(
            'X-GNUsocial-MessageType' => $msg_type,
            'X-GNUsocial-TargetUser'  => $to,
            'X-GNUsocial-SourceUser'  => $from,
            'X-GNUsocial-Domain'      => common_config('site', 'server')
        );
        return $headers;
    }

    function isReadOnly($args)
    {
        return true;
    }
}
