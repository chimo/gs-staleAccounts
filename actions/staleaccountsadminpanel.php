<?php

defined('GNUSOCIAL') || die();

class StaleaccountsadminpanelAction extends AdminPanelAction
{
    public function title(): string
    {
        return 'Stale accounts';
    }

    public function prepare(array $args = []): bool
    {
        parent::prepare($args);

        $this->page = $this->int('page', 1, null, 1);
        $this->args = $args;

        return true;
    }

    public function showContent(): void
    {
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

        $inactive_period = common_config('staleaccounts', 'inactive_period');

        // No config set fallback to default
        if ($inactive_period === false) {
            $inactive_period = 3;
        } else {
            // Make sure config is a number
            // Returns 1 if it's an object, 0 for other non-numbers
            $inactive_period = floatval($inactive_period);
        }

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

        if ($cnt === 0) {
            $this->element(
                'p',
                null,
                "No accounts have been inactive for more than {$inactive_period} months."
            );
            return;
        }

        $this->elementStart('ul', array('class' => 'stale_profile_list'));

        while ($dataObj->fetch()) {
            $profile = new Profile();

            foreach ($properties as $property) {
                $profile->$property = $dataObj->$property;
            }

            $profile->latest_activity = $dataObj->latest_activity;

            $pli = new StaleProfileListItem($profile, $this);
            $pli->show();
        }

        $this->elementEnd('ul');

        $this->pagination(
            $this->page > 1,
            $cnt > PROFILES_PER_PAGE,
            $this->page,
            'staleaccounts',
            $this->args
        );
    }

    public function showNoticeForm(): void
    {
        // Don't generate a notice form
    }

    public function showProfileBlock(): void
    {
        // Don't generate a profile block
    }
}

class StaleProfileListItem extends ProfileListItem
{
    public function showBio(): void
    {
        parent::showBio();

        $latest_activity = $this->profile->latest_activity ?: 'NEVER';

        $this->action->element(
            'p',
            ['class' => 'note'],
            'Latest activity: ' . $latest_activity
        );
    }

    public function showActions(): void
    {
        parent::startActions();

        try {
            // Throws NoSuchUserException
            $user = $this->profile->getUser();

            // Can't notify user if we don't have an email address
            if ($user->email) {
                $this->action->elementStart('li', 'entity_nudge');
                $form = new StaleReminderForm($this->out, $user);
                $form->show();
                $this->action->elementEnd('li');
            } else {
                $this->action->element(
                    'li',
                    ['class' => 'unconfirmed_email'],
                    'unconfirmed email' . $user->email
                );
            }
        } catch (Exception $e) {
            // This shouldn't be possible -- famous last words
            common_log(LOG_ERR, $e->getMessage());
        }

        $cur = common_current_user();
        list($action, $r2args) = $this->out->returnToArgs();
        $r2args['action'] = $action;

        if ($cur instanceof User && $cur->hasRight(Right::DELETEUSER)) {
            $this->elementStart('li', array('class' => 'entity_delete'));
            $df = new DeleteUserForm($this->out, $this->profile, $r2args);
            $df->show();
            $this->elementEnd('li');
        }

        parent::endActions();
    }
}

/**
 * This form uses the "form_user_nudge" class to get the envelope icon.
 * I haven't found a way to use the "current theme's sprite file" from
 * the plugin's stylesheet.
 */
class StaleReminderForm extends Form
{
    public $profile = null;

    public function __construct($out = null, $profile = null)
    {
        parent::__construct($out);
        $this->profile = $profile;
    }

    public function id(): ?string
    {
        return 'form_user_nudge';
    }

    public function formClass(): string
    {
        return 'form_user_nudge ajax';
    }

    public function action(): ?string
    {
        return common_local_url(
            'stalereminder',
            ['nickname' => $this->profile->nickname]
        );
    }

    public function formLegend(): void
    {
        $this->out->element('legend', null, _('Remind this user'));
    }

    public function formActions(): void
    {
        $this->out->submit(
            'submit',
            // TRANS: Button text to reminder/ping another user.
            _m('BUTTON', 'Remind'),
            'submit',
            null,
            // TRANS: Button title to reminder/ping another user.
            _('Send a reminder to this user.')
        );
    }
}
