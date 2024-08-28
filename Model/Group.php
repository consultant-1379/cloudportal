<?php

class Group extends AppModel {

    public $name = 'Group';
    public $validate = array(
        'group_dn' => array(
            'Please enter the group dn.' => array(
                'rule' => 'notEmpty',
                'message' => 'Please enter the group dn.'
            )
        ),
	'vcloudorgvdcid' => array(
            'Please enter the OrgvDC.' => array(
                'rule' => 'notEmpty',
                'message' => 'Please enter the OrgvDC.'
            )
        ),
	'vcloudorgid' => array(
            'Please enter the Org.' => array(
                'rule' => 'notEmpty',
                'message' => 'Please enter the Org.'
            )
        ),
        'username' => array(
            'The username must be between 5 and 15 characters.' => array(
                'rule' => array('between', 5, 15),
                'message' => 'The username must be between 5 and 15 characters.'
            ),
            'That username has already been taken' => array(
                'rule' => 'isUnique',
                'message' => 'That username has already been taken.'
            )
        ),
        'email' => array(
            'Valid email' => array(
                'rule' => array('email'),
                'message' => 'Please enter a valid email address'
            )
        ),
        'password' => array(
            'Not empty' => array(
                'rule' => 'notEmpty',
                'message' => 'Please enter your password'
            ),
            'Match passwords' => array(
                'rule' => 'matchPasswords',
                'message' => 'Your passwords do not match'
            )
        ),
        'password_confirmation' => array(
            'Not empty' => array(
                'rule' => 'notEmpty',
                'message' => 'Please confirm your password'
            )
        )
    );

}
?>
