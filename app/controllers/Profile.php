<?php

namespace Controller;

use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class Profile
{
    use MainController;

    public function index()
    {
        require_login();

        $users = new User;
        $row = $users->findPublicProfileById((int)current_user_id());

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            require_csrf();

            if (($row->auth_provider ?? 'local') === 'ldap')
            {
                $this->view('profile/view', [
                    'user' => $row,
                    'isOwnProfile' => true,
                    'canEditEmail' => false,
                    'errors' => ['email' => 'Your email address is managed by Active Directory'],
                ]);
                return;
            }

            $email = trim((string)($_POST['email'] ?? ''));
            if ($users->validateProfileEmail($email))
            {
                if ($users->emailExists($email, (int)$row->id))
                {
                    $users->errors['email'] = 'Email is already in use';
                }
            }

            if (empty($users->errors))
            {
                $now = date('Y-m-d H:i:s');
                $users->update((int)$row->id, [
                    'email' => $email,
                    'updated_at' => $now,
                ]);

                $_SESSION['USER']->email = $email;
                $_SESSION['USER']->updated_at = $now;

                message('Profile updated successfully.');
                redirect('profile');
            }

            $row->email = $email;
            $this->view('profile/view', [
                'user' => $row,
                'isOwnProfile' => true,
                'canEditEmail' => true,
                'errors' => $users->errors,
            ]);
            return;
        }

        $this->view('profile/view', [
            'user' => $row,
            'isOwnProfile' => true,
            'canEditEmail' => ($row->auth_provider ?? 'local') !== 'ldap',
        ]);
    }

    public function show($id = '')
    {
        require_login();

        $users = new User;
        $row = $users->findPublicProfileById((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $this->view('profile/view', [
            'user' => $row,
            'isOwnProfile' => (int)$row->id === (int)current_user_id(),
            'canEditEmail' => false,
        ]);
    }
}
