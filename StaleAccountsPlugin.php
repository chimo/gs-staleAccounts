<?php

if (!defined('GNUSOCIAL')) {
    exit(1);
}

class StaleAccountsPlugin extends Plugin
{
    const VERSION = '0.0.1';

    function onRouterInitialized($m)
    {
        $m->connect('panel/staleaccounts', array('action' => 'staleaccounts'));

        $m->connect(':nickname/stalereminder',
                    array('action' => 'stalereminder'),
                    array('nickname' => Nickname::DISPLAY_FMT));

        return true;
    }

    function onEndAdminPanelNav($nav) {
        if (AdminPanelAction::canAdmin('user')) {
            $menu_title = _('Stale accounts management');
            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(common_local_url('staleaccounts'), _m('MENU','Stale Accounts'),
                                 $menu_title, $action_name == 'staleaccounts', 'stale_accounts_admin_panel');
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Stale Accounts',
                            'version' => self::VERSION,
                            'author' => 'chimo',
                            'homepage' => 'https://github.com/chimo/gs-staleAccounts',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('')); // TODO
        return true;
    }
}

