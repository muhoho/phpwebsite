<?php

/**
 * Handles the logging and routing of security problems
 *
 * @author Matthew McNaney <matt at tux dot appstate dot edu>
 * @version $Id$
 */

class Security {
    function log($message)
    {
        translate('core');
        if (class_exists('Current_User')) {
            $username = Current_User::getUsername();
        } else {
            $username = _('Unknown user');
        }

        $ip = $_SERVER['REMOTE_ADDR'];

        if (isset($_SERVER['HTTP_REFERER'])) {
            $via = sprintf(_('Coming from: %s'), $_SERVER['HTTP_REFERER']);
        }
        else {
            $via = _('Unknown source');
        }

        $infraction = sprintf('%s@%s %s -- %s', $username, $ip, $via, $message);
        
        PHPWS_Core::log(escapeshellcmd($infraction), 'security.log', _('Warning'));
        translate();
    }
}

?>
