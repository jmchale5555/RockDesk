<?php

namespace Model;

class User
{

    use Model;

    public const ROLES = ['user', 'staff', 'admin'];
    public const AUTH_PROVIDERS = ['local', 'ldap'];

    protected $table = 'users';

    protected $allowedColumns = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'auth_provider',
        'is_active',
        'must_reset_password',
        'directory_guid',
        'directory_domain',
        'directory_username',
        'directory_dn',
        'directory_synced_at',
        'last_login_at',
        'created_at',
        'updated_at',
    ];

    public function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    public function isValidUsername(string $username): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $username);
    }

    public function isValidRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public function isValidAuthProvider(string $authProvider): bool
    {
        return in_array($authProvider, self::AUTH_PROVIDERS, true);
    }

    public function validate($data)
    {
        $this->errors = [];

        if (empty($data['name']))
        {
            $this->errors['name'] = "Name is required";
        }
        else
        if (empty($data['username']))
        {
            $this->errors['username'] = "Username is required";
        }
        else
        if (!$this->isValidUsername((string)$data['username']))
        {
            $this->errors['username'] = "Username must be 3-100 characters and only use letters, numbers, dots, dashes, or underscores";
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        {
            $this->errors['email'] = "Enter a valid email address";
        }

        if (empty($data['password']))
        {
            $this->errors['password'] = "Password is required";
        }

        if (!empty($data['confirm']))
        {
            if ($data['confirm'] !== $data['password'])
            {
                $this->errors['confirm'] = "Passwords do not match";
            }
            else
                unset($data[2]);
        }

        if (empty($this->errors))
        {
            return true;
        }

        return false;
    }

    public function validateRoleData(array $data): bool
    {
        $this->errors = [];

        if (empty($data['role']) || !$this->isValidRole((string)$data['role']))
        {
            $this->errors['role'] = "Choose a valid role";
        }

        if (empty($data['auth_provider']) || !$this->isValidAuthProvider((string)$data['auth_provider']))
        {
            $this->errors['auth_provider'] = "Choose a valid auth provider";
        }

        return empty($this->errors);
    }

    public function validatePasswordChange(array $data, string $currentPasswordHash): bool
    {
        $this->errors = [];

        if (empty($data['current_password']))
        {
            $this->errors['current_password'] = "Current password is required";
        }
        else
        if (!password_verify($data['current_password'], $currentPasswordHash))
        {
            $this->errors['current_password'] = "Current password is incorrect";
        }

        if (empty($data['password']))
        {
            $this->errors['password'] = "New password is required";
        }
        else
        if (strlen($data['password']) < 8)
        {
            $this->errors['password'] = "New password must be at least 8 characters";
        }

        if (empty($data['confirm']))
        {
            $this->errors['confirm'] = "Please confirm your new password";
        }
        else
        if (!empty($data['password']) && $data['confirm'] !== $data['password'])
        {
            $this->errors['confirm'] = "Passwords do not match";
        }

        if (!empty($data['password']) && password_verify($data['password'], $currentPasswordHash))
        {
            $this->errors['password'] = "New password must be different from your current password";
        }

        return empty($this->errors);
    }
}
