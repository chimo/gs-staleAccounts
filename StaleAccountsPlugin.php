<?php

defined('GNUSOCIAL') || die();

class StaleAccountsPlugin extends Plugin
{
    const VERSION = '0.0.2';

    public function onRouterInitialized(URLMapper $m): bool
    {
        $m->connect(
            'panel/staleaccounts',
            ['action' => 'staleaccountsadminpanel']
        );

        $m->connect(
            ':nickname/stalereminder',
            ['action' => 'stalereminder'],
            ['nickname' => Nickname::DISPLAY_FMT]
        );

        return true;
    }

    public function onEndAdminPanelNav(AdminPanelNav $nav): bool
    {
        if (AdminPanelAction::canAdmin('user')) {
            $menu_title  = _('Stale accounts management');
            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(
                common_local_url('staleaccountsadminpanel'),
                _m('MENU', 'Stale Accounts'),
                $menu_title,
                ($action_name === 'staleaccountsadminpanel'),
                'stale_accounts_admin_panel'
            );
        }

        return true;
    }

    /**
     * If the plugin's installed, this should be accessible to admins
     */
    public function onAdminPanelCheck(string $name, bool &$isOK): bool
    {
        if ($name === 'staleaccounts') {
            $isOK = true;
            return false;
        }

        return true;
    }

    public function onEndShowStyles(Action $action): bool
    {
        $action->cssLink($this->path('css/stale-accounts.css'));

        return true;
    }

    public function onPluginVersion(array &$versions): bool
    {
        $versions[] = [
            'name'        => 'Stale Accounts',
            'version'     => self::VERSION,
            'author'      => 'chimo',
            'homepage'    => 'https://github.com/chimo/gs-staleAccounts',
            // TRANS: Plugin description.
            'description' => _m(''), // TODO
        ];
        return true;
    }
}
