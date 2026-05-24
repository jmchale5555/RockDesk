<?php

declare(strict_types=1);

define('ROOTPATH', __DIR__ . '/../public/');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/core/config.php';

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as DirectoryUser;

function line(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit($exitCode);
}

function bool_text(bool $value): string
{
    return $value ? 'true' : 'false';
}

function host_name(string $host): string
{
    $host = preg_replace('#^ldaps?://#', '', trim($host));
    $host = explode('/', (string)$host)[0];

    return $host;
}

function positional_args(array $argv): array
{
    $positionals = [];

    foreach (array_slice($argv, 1) as $argument)
    {
        if (str_starts_with((string)$argument, '--'))
        {
            continue;
        }

        $positionals[] = trim((string)$argument);
    }

    return $positionals;
}

function has_flag(array $argv, string $flag): bool
{
    return in_array($flag, array_slice($argv, 1), true);
}

function option_value(array $options, array $positionals, string $long, int $position): ?string
{
    if (isset($options[$long]) && is_scalar($options[$long]))
    {
        return trim((string)$options[$long]);
    }

    $index = $position - 1;

    return isset($positionals[$index]) ? trim((string)$positionals[$index]) : null;
}

function prompt_secret(string $prompt): string
{
    if (!function_exists('shell_exec'))
    {
        return '';
    }

    fwrite(STDERR, $prompt);
    shell_exec('stty -echo 2>/dev/null');
    $value = fgets(STDIN);
    shell_exec('stty echo 2>/dev/null');
    fwrite(STDERR, PHP_EOL);

    return trim((string)$value);
}

$options = getopt('', ['username::', 'password::', 'prompt-password', 'help']);
$positionals = positional_args($argv);

if (isset($options['help']) || has_flag($argv, '--help'))
{
    line('Usage: php scripts/check-ldap.php [username] [password]');
    line('       php scripts/check-ldap.php --username=jdoe --prompt-password');
    line('       LDAP_CHECK_PASSWORD=secret php scripts/check-ldap.php jdoe');
    line();
    line('Without a username, this checks configuration and service bind only.');
    line('With a username, it searches for the directory user.');
    line('With a password, it also attempts authentication as that user.');
    exit(0);
}

$username = option_value($options, $positionals, 'username', 1) ?? '';
$password = option_value($options, $positionals, 'password', 2) ?? (string)(getenv('LDAP_CHECK_PASSWORD') ?: '');

if ($username !== '' && $password === '' && (isset($options['prompt-password']) || has_flag($argv, '--prompt-password')))
{
    $password = prompt_secret('LDAP password for ' . $username . ': ');
}

line('Rockdesk LDAP diagnostics');
line('-------------------------');
line('LDAP_ENABLED: ' . bool_text(LDAP_ENABLED));
line('LDAP_HOST: ' . (LDAP_HOST !== '' ? LDAP_HOST : '(empty)'));
line('LDAP_PORT: ' . LDAP_PORT);
line('LDAP_BASE_DN: ' . (LDAP_BASE_DN !== '' ? LDAP_BASE_DN : '(empty)'));
line('LDAP_USERNAME: ' . (LDAP_USERNAME !== '' ? LDAP_USERNAME : '(empty/anonymous bind)'));
line('LDAP_USE_SSL: ' . bool_text(LDAP_USE_SSL));
line('LDAP_USE_TLS: ' . bool_text(LDAP_USE_TLS));
line('LDAP_TLS_REQUIRE_CERT: ' . bool_text(LDAP_TLS_REQUIRE_CERT));
line('LDAP_USER_FILTER_ATTRIBUTE: ' . LDAP_USER_FILTER_ATTRIBUTE);

$certPath = '/etc/ldap/certs/ad-ca.crt';
if (file_exists($certPath))
{
    line('LDAP cert path: ' . $certPath . ' (' . (is_readable($certPath) ? 'readable' : 'not readable') . ')');
}
else
{
    line('LDAP cert path: ' . $certPath . ' (not present)');
}

line();

if (!LDAP_ENABLED)
{
    fail('LDAP_ENABLED is false. Enable LDAP before running a real bind check.');
}

if (!extension_loaded('ldap'))
{
    fail('PHP ldap extension is not installed. Rebuild the web image.');
}

if (!class_exists(Connection::class))
{
    fail('LdapRecord is not installed. Run composer install.');
}

if (LDAP_HOST === '' || LDAP_BASE_DN === '')
{
    fail('LDAP_HOST and LDAP_BASE_DN are required.');
}

if (!LDAP_USE_SSL && !LDAP_USE_TLS)
{
    fail('LDAP_USE_SSL or LDAP_USE_TLS must be true for this application configuration.');
}

if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT'))
{
    ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_TLS_REQUIRE_CERT ? LDAP_OPT_X_TLS_HARD : LDAP_OPT_X_TLS_NEVER);
}

$connection = new Connection([
    'hosts' => [host_name(LDAP_HOST)],
    'base_dn' => LDAP_BASE_DN,
    'username' => LDAP_USERNAME,
    'password' => LDAP_PASSWORD,
    'port' => LDAP_PORT,
    'use_tls' => LDAP_USE_SSL,
    'use_starttls' => LDAP_USE_TLS,
    'timeout' => LDAP_TIMEOUT,
]);

Container::addConnection($connection, 'default');

try
{
    $connection->connect();
    line('Service bind: OK');
}
catch (Throwable $e)
{
    fail('Service bind failed: ' . $e->getMessage());
}

if ($username === '')
{
    line('User search/auth: skipped; pass a username to test user lookup and authentication.');
    exit(0);
}

try
{
    $directoryUser = DirectoryUser::query()
        ->where(LDAP_USER_FILTER_ATTRIBUTE, '=', $username)
        ->first();
}
catch (Throwable $e)
{
    fail('User search failed: ' . $e->getMessage());
}

if (!$directoryUser)
{
    fail('User not found with ' . LDAP_USER_FILTER_ATTRIBUTE . '=' . $username);
}

line('User search: OK');
line('User DN: ' . $directoryUser->getDn());

if ($password === '')
{
    line('User auth: skipped; pass a password, set LDAP_CHECK_PASSWORD, or use --prompt-password.');
    exit(0);
}

try
{
    if (!$connection->auth()->attempt($directoryUser->getDn(), $password))
    {
        fail('User authentication failed: invalid credentials or bind rejected.');
    }
}
catch (Throwable $e)
{
    fail('User authentication failed: ' . $e->getMessage());
}

line('User auth: OK');
exit(0);
