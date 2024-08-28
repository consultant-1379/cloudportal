<?php

App::uses('Controller','Controller');

class AppController extends Controller {

    public $components = array(
    'Session',
	'RequestHandler',
        'Auth' => array(
            'logoutRedirect' => array('controller' => 'users', 'action' => 'login'),
            'authError' => "You don't have permission to access that page, please contact your team co-ordinator to give you access if required.",
            'authorize' => array('Controller')
        )
    );

    public function isAuthorized($user) {
        if ($user['is_admin']) {
            return true;
        }
        return false;
    }

    public function beforeFilter() {

        $this->Auth->loginRedirect = array('controller' => Configure::read('pooling_enabled') ? "Bookings" : "OrgVdcs", 'action' => 'index');

        // Allow REST calls from other servers (eg for metrics gathering)
        header("Access-Control-Allow-Origin: *");

		// Forcibly login rest users
        if (isset($this->params['ext']) && ($this->params['ext'] == 'xml' || $this->params['ext'] == 'json'))
        {
            $this->Auth->authenticate = array(
                'Ldap' => array('userModel' => 'Ldap'),
                'Basic',
            );
            $rest_user=env('PHP_AUTH_USER');
            $rest_pw=env('PHP_AUTH_PW');
            if (isset($rest_user)&& isset($rest_pw))
            {
                $data['User']['username'] = $rest_user;
                $data['User']['password'] = $rest_pw;
                $this->request->data=$data;
                $cache_key = $rest_user . "_" . md5($rest_pw);
                $user_object = Cache::read($cache_key, 'user_login_cache');
                if ($user_object !== false){
                    $this->Auth->login($user_object);
                } else {
                    //Without this,proper response is not returned for the exceptions
                    $this->Auth->initialize($this);
                    if (!$this->Auth->login()) {
                        throw new ForbiddenException();
                    }
                    Cache::write($cache_key, $this->Auth->user(), 'user_login_cache');
                }
           }
        }
        else
         {
            $this->Auth->authenticate = array(
                'Form',
                'Ldap' => array('userModel' => 'Ldap')
            );
         }

        $this->set('logged_in', $this->Auth->loggedIn());
        $this->set('current_user', $this->Auth->user());
        if (Configure::read('pooling_enabled'))
        {
            $this->layout = 'booking';
        }

    }
}
