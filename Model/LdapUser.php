<?php

class LdapUser extends AppModel {

    var $name = 'LdapUser';
    var $useTable = false;
    var $host = 'SESSIWEGAD0003.ericsson.se';
    var $port = 3268;
    var $baseDn = 'dc=ericsson,dc=se';
    var $ds;

    function __construct() {
        parent::__construct();
        if (!$this->ds = ldap_connect($this->host, $this->port)) {
            die("Could not connect to LDAP server");
        }
        ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ds, LDAP_OPT_REFERRALS, 0);
    }

    function _findUser($uid, $password) {
        if (empty($password))
        {
            return false;
        }
        $possible_domains = array('ERICSSON','EEMEA');
        $bind_successful = false;
        foreach ($possible_domains as $domain)
        {
            $uid_with_domain = $uid . '@' . $domain;
            if (@ldap_bind($this->ds, $uid_with_domain, $password))
            {
                $bind_successful = true;
                break;
            }
        }
        if (!$bind_successful)
        {
            return false;
        }
        $result = $this->findAll('sAMAccountName', $uid, $this->baseDn);
        ldap_close($this->ds);
        if (isset($result[0])) {
            $permissions = "";
            $groups = ClassRegistry::init('Group')->find('all');
            $restrict_true = false;
            $restrict_false = false;

            foreach ($groups as $group) {
                if (in_array($group['Group']['group_dn'],$result[0]['memberof'])) {
                    if ($group['Group']['read_permission']) {
                        $permissions[$group['Group']['vcloudorgvdcid']]["read_permission"] = true;
                    }
                    if ($group['Group']['write_permission']) {
                        $permissions[$group['Group']['vcloudorgvdcid']]["write_permission"] = true;
                        $permissions[$group['Group']['vcloudorgid']]["write_permission"] = true;
                    }
                    if ($group['Group']['admin_permission']) {
                        $permissions[$group['Group']['vcloudorgvdcid']]["admin_permission"] = true;
                        $permissions[$group['Group']['vcloudorgid']]["admin_permission"] = true;
                        $permissions[$group['Group']['vcloudorgvdcid']]["write_permission"] = true;
                        $permissions[$group['Group']['vcloudorgid']]["write_permission"] = true;
                        $permissions[$group['Group']['vcloudorgvdcid']]["read_permission"] = true;
                        $permissions[$group['Group']['vcloudorgid']]["read_permission"] = true;
                    }
                    if ($group['Group']['restrict_catalogs'] || $restrict_false) {

                        $restrict_true = true;

                        // split unrestricted_catalogs by newline
                        $unrestricted_catalogs = split(",", $group['Group']['unrestricted']);
                        // for each one, set the fact that i have read access to this catalog
                        foreach ($unrestricted_catalogs as $catalogid) {

                            //if the tring contains cloud
                            if (strstr($catalogid, "cloud")) {

                                $permissions[$catalogid]["read_permission"] = true;
                            }
                        }
                    } else {
                        $restrict_false = true;
                    }
                }
            }

            // default not restricting
            if ($restrict_false) {
                // not restricting
            } else if ($restrict_true) {
                $permissions['restrict_catalogs'] = true;
            }

            $object = array(
                'login_type' => 'ldap',
                'username' => $result[0]['cn'][0],
                'is_admin' => false,
                'permissions' => $permissions
            );
            if (isset($result[0]['mail']))
            {
                $object['email'] = $result[0]['mail'][0];
            } else {
                $object['email'] = null;
            }
            return $object;
        } else {
            return false;
        }
    }

    function findAll($attribute = 'uid', $value = '*', $baseDn = 'ou=People,dc=example,dc=com') {
        $attributes = array("mail", "cn", "memberof");
        $r = ldap_search($this->ds, $baseDn, "(" . $attribute . '=' . $value . ")", $attributes);

        if ($r) {
            return ldap_get_entries($this->ds, $r);
        }
    }
}
?>
