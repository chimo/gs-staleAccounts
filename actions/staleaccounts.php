<?php
if (!defined('GNUSOCIAL')) {
    exit(1);
}

class StaleaccountsAction extends Action
{

    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }

    function title() {
        return 'Stale accounts';
    }

    function prepare($args) {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
        }

        $user = common_current_user();

        assert(!empty($user));

        // It must be a "real" login, not saved cookie login
        if (!common_is_real_login()) {
            // Cookie theft is too easy; we require automatic
            // logins to re-authenticate before admining the site
            common_set_returnto($this->selfUrl());
            if (Event::handle('RedirectToLogin', array($this, $user))) {
                common_redirect(common_local_url('login'), 303);
            }
        }

        // User must have the right to change admin settings
        if (!$user->hasRight(Right::CONFIGURESITE)) {
            $this->clientError(_('You cannot make changes to this site.'));
        }

        $this->page = isset($args['page']) ? $args['page'] + 0 : 1;
        $this->args = $args;

        return true;
    }

    function showContent() {
        $properties = [
            'id',
            'nickname',
            'fullname',
            'profileurl',
            'homepage',
            'bio',
            'location',
            'lat',
            'lon',
            'location_id',
            'location_ns',
            'created',
            'modified'
        ];

        // $config['staleaccounts']['inactive_period'] is number of months
        // of inactivity before an account is considered stale.
        // Defaults to 3
        $inactive_period = common_config('staleaccounts', 'inactive_period') ?: 3;

        // Calculate stale date (today - $inactive_period)
        $stale_date = new DateTime();
        $stale_date->modify('-' . $inactive_period . ' month');
        $stale_date = $stale_date->format('Y-m-d');

        $offset = ($this->page - 1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;

        $dataObj = new DB_DataObject();

        // Custom query because I only want to hit the db once
        $dataObj->query(
            'SELECT * FROM
            (
                SELECT local_profiles.*, MAX(n.created) as latest_activity FROM
                (
                    SELECT p.*
                    FROM profile p
                    JOIN user u ON u.id = p.id
                ) local_profiles
                LEFT JOIN notice n ON local_profiles.id = n.profile_id
                GROUP BY local_profiles.id
                ORDER BY latest_activity
            ) z
            WHERE z.latest_activity < "' . $stale_date . '"
            OR z.latest_activity IS NULL
            LIMIT ' . $offset . ', ' . $limit . ';'
        );

        $cnt = $dataObj->N;
        $this->elementStart('div');

        while($dataObj->fetch()) {
            $profile = new Profile();

            foreach($properties as $property) {
                $profile->$property = $dataObj->$property;
            }

            $profile->latest_activity = $dataObj->latest_activity;

            $pli = new StaleProfileListItem($profile, $this);
            $pli->show();
        }

        $this->elementEnd('div');

        $this->pagination(
            $this->page > 1,
            $cnt > PROFILES_PER_PAGE,
            $this->page,
            'staleaccounts',
            $this->args
        );
    }

    function showNoticeForm() {
        // Don't generate a notice form
    }

    function showProfileBlock() {
        // Don't generate a profile block
    }
}

class StaleProfileListItem extends ProfileListItem {
    function showBio() {
        parent::showBio();

        $latest_activity = $this->profile->latest_activity ?: 'NEVER';

        $this->action->element('p', array('class' => 'note'), 'Latest activity: ' . $latest_activity);
    }

    function showActions() {
        parent::startActions();

        try {
            // Throws NoSuchUserException
            $user = $this->profile->getUser();

            // Can't notify user if we don't have an email address
            if ($user->email) {
                $this->action->elementStart('div', 'entity_nudge');
                $form = new StaleReminderForm($this->out, $user);
                $form->show();
                $this->action->elementEnd('div');
            }else {				
				$this->action->element('div', array('class' => 'none'), 'e-mail not confirmed!' . $user->email);				
			}            
            
        } catch(Exception $e) {
            // This shouldn't be possible -- famous last words
            common_log(LOG_ERR, $e->getMessage());
        }

        $cur = common_current_user();
        list($action, $r2args) = $this->out->returnToArgs();
        $r2args['action'] = $action;

        if ($cur instanceof User && $cur->hasRight(Right::DELETEUSER)) {
            $this->elementStart('div', array('class' => 'entity_delete'));
            $df = new DeleteUserForm($this->out, $this->profile, $r2args);
            $df->show();
            $this->elementEnd('div');
        }

        parent::endActions();
    }
}

class StaleReminderForm extends Form {
    var $profile = null;

    function __construct($out=null, $profile=null)
    {
        parent::__construct($out);
        $this->profile = $profile;
    }

    function id()
    {
        return 'form_user_reminder';
    }

    function formClass()
    {
        return 'form_user_reminder ajax';
    }

    function action()
    {
        return common_local_url('stalereminder', array('nickname' => $this->profile->nickname));
    }

    function formLegend()
    {
        $this->out->element('legend', null, _('Remind this user'));
    }

    function formActions()
    {
        $this->out->submit('submit',
                           // TRANS: Button text to reminder/ping another user.
                           _m('BUTTON','Remind'),
                           'submit',
                           null,
                           // TRANS: Button title to reminder/ping another user.
                           _('Send a reminder to this user.'));
    }
}
