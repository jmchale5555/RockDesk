<?php

namespace Controller;

defined('ROOTPATH') or exit('Access Denied');

/**
 * Signup controller
 */
class Signup
{
    use MainController;

    public function index()
    {
        message('Accounts are created by an administrator. Please contact support if you need access.');
        redirect('login');
    }
}
