<?php

App::uses('BaseAuthenticate', 'Controller/Component/Auth');

class LdapAuthenticate extends BaseAuthenticate {

    public function authenticate(CakeRequest $request, CakeResponse $response) {
        $this->Ldap = ClassRegistry::init('LdapUser');

        //$userModel = $this->settings['userModel'];
        $userModel = "Ldap";
        list($plugin, $model) = pluginSplit($userModel);

        //$fields = $this->settings['fields'];
        if (empty($request->data[$model])) {
            //	return false;
        }
        if (
                empty($request->data[$model]['username']) ||
                empty($request->data[$model]['password'])
        ) {
            //	return false;
        }
        return $this->Ldap->_findUser(
                        $request->data['User']['username'], $request->data['User']['password']
        );
    }

}

?>
