<?php

namespace Core;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as DirectoryUser;
use Model\User;
use Throwable;

defined('ROOTPATH') or exit('Access Denied');

class LdapAuth
{
    public function authenticate(string $username, string $password): mixed
    {
        $username = (new User)->normalizeUsername($username);

        if (!$this->isEnabled() || $username === '' || $password === '')
        {
            return false;
        }

        try
        {
            $connection = $this->connection();
            $directoryUser = $this->findDirectoryUser($username);

            if (empty($directoryUser) || !$connection->auth()->attempt($directoryUser->getDn(), $password))
            {
                return false;
            }

            return $this->syncLocalUser($directoryUser, $username);
        }
        catch (Throwable)
        {
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return LDAP_ENABLED
            && LDAP_HOST !== ''
            && LDAP_BASE_DN !== ''
            && (LDAP_USE_SSL || LDAP_USE_TLS)
            && class_exists(Connection::class);
    }

    private function connection(): Connection
    {
        if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT'))
        {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_TLS_REQUIRE_CERT ? LDAP_OPT_X_TLS_HARD : LDAP_OPT_X_TLS_NEVER);
        }

        $connection = new Connection([
            'hosts' => [$this->hostName(LDAP_HOST)],
            'base_dn' => LDAP_BASE_DN,
            'username' => LDAP_USERNAME,
            'password' => LDAP_PASSWORD,
            'port' => LDAP_PORT,
            'use_tls' => LDAP_USE_SSL,
            'use_starttls' => LDAP_USE_TLS,
            'timeout' => LDAP_TIMEOUT,
        ]);

        Container::addConnection($connection, 'default');
        $connection->connect();

        return $connection;
    }

    private function findDirectoryUser(string $username): ?DirectoryUser
    {
        return DirectoryUser::query()
            ->where(LDAP_USER_FILTER_ATTRIBUTE, '=', $username)
            ->first();
    }

    private function syncLocalUser(DirectoryUser $directoryUser, string $loginUsername): mixed
    {
        $users = new User;
        $now = date('Y-m-d H:i:s');
        $metadata = $this->metadata($directoryUser, $loginUsername, $now);
        $row = false;

        if ($metadata['directory_guid'] !== '')
        {
            $row = $users->findByDirectoryGuid($metadata['directory_guid']);
        }

        if (!$row)
        {
            $row = $users->findByDirectoryUsernameOrUsername($metadata['directory_username'], $metadata['username']);
        }

        if ($row)
        {
            if ((int)($row->is_active ?? 1) !== 1)
            {
                return false;
            }

            $users->update((int)$row->id, $metadata + [
                'auth_provider' => 'ldap',
                'last_login_at' => $now,
                'updated_at' => $now,
            ]);

            return $users->findById((int)$row->id);
        }

        $users->insert($metadata + [
            'password' => null,
            'role' => 'user',
            'auth_provider' => 'ldap',
            'is_active' => 1,
            'must_reset_password' => 0,
            'last_login_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $users->findByDirectoryUsernameOrUsername($metadata['directory_username'], $metadata['username']);
    }

    private function metadata(DirectoryUser $directoryUser, string $loginUsername, string $now): array
    {
        $directoryUsername = $this->firstAttribute($directoryUser, LDAP_USER_FILTER_ATTRIBUTE) ?: $loginUsername;
        $username = $this->appUsername($directoryUsername, $loginUsername);

        return [
            'name' => $this->firstAttribute($directoryUser, 'displayname')
                ?: $this->firstAttribute($directoryUser, 'cn')
                ?: $username,
            'username' => $username,
            'email' => $this->firstAttribute($directoryUser, 'mail') ?: null,
            'directory_guid' => $this->guid($directoryUser),
            'directory_domain' => LDAP_DOMAIN !== '' ? LDAP_DOMAIN : null,
            'directory_username' => (new User)->normalizeUsername($directoryUsername),
            'directory_dn' => $directoryUser->getDn(),
            'directory_synced_at' => $now,
        ];
    }

    private function firstAttribute(DirectoryUser $directoryUser, string $attribute): ?string
    {
        $value = $directoryUser->getFirstAttribute($attribute);

        if (is_array($value))
        {
            $value = reset($value);
        }

        if (!is_scalar($value))
        {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function guid(DirectoryUser $directoryUser): string
    {
        if (method_exists($directoryUser, 'getConvertedGuid'))
        {
            return (string)$directoryUser->getConvertedGuid();
        }

        return (string)($this->firstAttribute($directoryUser, 'objectguid') ?: '');
    }

    private function hostName(string $host): string
    {
        $host = preg_replace('#^ldaps?://#', '', trim($host));
        $host = explode('/', (string)$host)[0];

        return $host;
    }

    private function appUsername(string $directoryUsername, string $loginUsername): string
    {
        $source = $directoryUsername !== '' ? $directoryUsername : $loginUsername;
        $source = explode('@', $source)[0];
        $username = strtolower((string)preg_replace('/[^a-zA-Z0-9._-]+/', '.', $source));
        $username = trim($username, '.-_');
        $username = $username !== '' ? $username : 'ldap.user';

        if (strlen($username) < 3)
        {
            $username .= '.ad';
        }

        return substr($username, 0, 100);
    }
}
