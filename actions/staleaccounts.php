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
            OR z.latest_activity IS NULL;'
        );

        // TODO: Pagination
        $this->elementStart('ul');

        while($dataObj->fetch()) {
            $profile = new Profile();

            foreach($properties as $property) {
                $profile->$property = $dataObj->$property;
            }

            $profile->latest_activity = $dataObj->latest_activity;

            $pli = new StaleProfileListItem($profile, $this);
            $pli->show();
        }

        $this->elementEnd('ul');
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

        // Send 'poke' action
        // TODO: rename this to something like 're-invite' or 'send reminder'
        // TODO: add functionality
        $this->action->elementStart('li');
        $this->action->element('a', array("href" => "#"), 'Poke');
        $this->action->elementEnd('li');

        // Delete action
        // TODO: add functionality
        $this->action->elementStart('li');
        $this->action->element('a', array("href" => "#"), 'Delete');
        $this->action->elementEnd('li');

        parent::endActions();
    }
}
