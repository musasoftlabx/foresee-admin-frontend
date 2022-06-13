<?php
define('DRUPAL_HASH_COUNT', 15);

/**
 * The minimum allowed log2 number of iterations for password stretching.
 */
define('DRUPAL_MIN_HASH_COUNT', 7);

/**
 * The maximum allowed log2 number of iterations for password stretching.
 */
define('DRUPAL_MAX_HASH_COUNT', 30);

/**
 * The expected (and maximum) number of characters in a hashed password.
 */
define('DRUPAL_HASH_LENGTH', 55);

/**
 * Returns a string for mapping an int to the corresponding base 64 character.
 */

function drupal_random_bytes($count)
{
    // $random_state does not use drupal_static as it stores random bytes.
    static $random_state, $bytes, $has_openssl;

    $missing_bytes = $count - strlen($bytes);

    if ($missing_bytes > 0) {
        // PHP versions prior 5.3.4 experienced openssl_random_pseudo_bytes()
        // locking on Windows and rendered it unusable.
        if (!isset($has_openssl)) {
            $has_openssl = version_compare(PHP_VERSION, '5.3.4', '>=') && function_exists('openssl_random_pseudo_bytes');
        }

        // openssl_random_pseudo_bytes() will find entropy in a system-dependent
        // way.
        if ($has_openssl) {
            $bytes .= openssl_random_pseudo_bytes($missing_bytes);
        }

        // Else, read directly from /dev/urandom, which is available on many *nix
        // systems and is considered cryptographically secure.
        elseif ($fh = @fopen('/dev/urandom', 'rb')) {
            // PHP only performs buffered reads, so in reality it will always read
            // at least 4096 bytes. Thus, it costs nothing extra to read and store
            // that much so as to speed any additional invocations.
            $bytes .= fread($fh, max(4096, $missing_bytes));
            fclose($fh);
        }

        // If we couldn't get enough entropy, this simple hash-based PRNG will
        // generate a good set of pseudo-random bytes on any system.
        // Note that it may be important that our $random_state is passed
        // through hash() prior to being rolled into $output, that the two hash()
        // invocations are different, and that the extra input into the first one -
        // the microtime() - is prepended rather than appended. This is to avoid
        // directly leaking $random_state via the $output stream, which could
        // allow for trivial prediction of further "random" numbers.
        if (strlen($bytes) < $count) {
            // Initialize on the first call. The contents of $_SERVER includes a mix of
            // user-specific and system information that varies a little with each page.
            if (!isset($random_state)) {
                $random_state = print_r($_SERVER, true);
                if (function_exists('getmypid')) {
                    // Further initialize with the somewhat random PHP process ID.
                    $random_state .= getmypid();
                }
                $bytes = '';
            }

            do {
                $random_state = hash('sha256', microtime() . mt_rand() . $random_state);
                $bytes .= hash('sha256', mt_rand() . $random_state, true);
            } while (strlen($bytes) < $count);
        }
    }
    $output = substr($bytes, 0, $count);
    $bytes  = substr($bytes, $count);
    return $output;
}

function _password_itoa64()
{
    return './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
}

function _password_base64_encode($input, $count)
{
    $output = '';
    $i      = 0;
    $itoa64 = _password_itoa64();
    do {
        $value = ord($input[$i++]);
        $output .= $itoa64[$value & 0x3f];
        if ($i < $count) {
            $value |= ord($input[$i]) << 8;
        }
        $output .= $itoa64[($value >> 6) & 0x3f];
        if ($i++ >= $count) {
            break;
        }
        if ($i < $count) {
            $value |= ord($input[$i]) << 16;
        }
        $output .= $itoa64[($value >> 12) & 0x3f];
        if ($i++ >= $count) {
            break;
        }
        $output .= $itoa64[($value >> 18) & 0x3f];
    } while ($i < $count);

    return $output;
}

function _password_generate_salt($count_log2)
{
    $output = '$S$';
    // Ensure that $count_log2 is within set bounds.
    $count_log2 = _password_enforce_log2_boundaries($count_log2);
    // We encode the final log2 iteration count in base 64.
    $itoa64 = _password_itoa64();
    $output .= $itoa64[$count_log2];
    // 6 bytes is the standard salt for a portable phpass hash.
    $output .= _password_base64_encode(drupal_random_bytes(6), 6);
    return $output;
}

function _password_enforce_log2_boundaries($count_log2)
{
    if ($count_log2 < DRUPAL_MIN_HASH_COUNT) {
        return DRUPAL_MIN_HASH_COUNT;
    } elseif ($count_log2 > DRUPAL_MAX_HASH_COUNT) {
        return DRUPAL_MAX_HASH_COUNT;
    }

    return (int) $count_log2;
}

function _password_crypt($algo, $password, $setting)
{
    // Prevent DoS attacks by refusing to hash large passwords.
    if (strlen($password) > 512) {
        return false;
    }
    // The first 12 characters of an existing hash are its setting string.
    $setting = substr($setting, 0, 12);

    if ($setting[0] != '$' || $setting[2] != '$') {
        return false;
    }
    $count_log2 = _password_get_count_log2($setting);
    // Hashes may be imported from elsewhere, so we allow != DRUPAL_HASH_COUNT
    if ($count_log2 < DRUPAL_MIN_HASH_COUNT || $count_log2 > DRUPAL_MAX_HASH_COUNT) {
        return false;
    }
    $salt = substr($setting, 4, 8);
    // Hashes must have an 8 character salt.
    if (strlen($salt) != 8) {
        return false;
    }

    // Convert the base 2 logarithm into an integer.
    $count = 1 << $count_log2;

    // We rely on the hash() function being available in PHP 5.2+.
    $hash = hash($algo, $salt . $password, true);
    do {
        $hash = hash($algo, $hash . $password, true);
    } while (--$count);

    $len    = strlen($hash);
    $output = $setting . _password_base64_encode($hash, $len);
    // _password_base64_encode() of a 16 byte MD5 will always be 22 characters.
    // _password_base64_encode() of a 64 byte sha512 will always be 86 characters.
    $expected = 12 + ceil((8 * $len) / 6);
    return (strlen($output) == $expected) ? substr($output, 0, DRUPAL_HASH_LENGTH) : false;
}

function _password_get_count_log2($setting)
{
    $itoa64 = _password_itoa64();
    return strpos($itoa64, $setting[3]);
}

function user_hash_password($password, $count_log2 = 0)
{
    /*if (empty($count_log2)) {
    // Use the standard iteration count.
    $count_log2 = variable_get('password_count_log2', DRUPAL_HASH_COUNT);
    }*/
    return _password_crypt('sha512', $password, _password_generate_salt($count_log2));
}

function user_check_password($password, $account)
{
    if (substr($account->pass, 0, 2) == 'U$') {
        // This may be an updated password from user_update_7000(). Such hashes
        // have 'U' added as the first character and need an extra md5().
        $stored_hash = substr($account->pass, 1);
        $password    = md5($password);
    } else {
        $stored_hash = $account->pass;
    }

    $type = substr($stored_hash, 0, 3);
    switch ($type) {
        case '$S$':
            // A normal Drupal 7 password using sha512.
            $hash = _password_crypt('sha512', $password, $stored_hash);
            break;
        case '$H$':
        // phpBB3 uses "$H$" for the same thing as "$P$".
        case '$P$':
            // A phpass password generated using md5.  This is an
            // imported password or from an earlier Drupal version.
            $hash = _password_crypt('md5', $password, $stored_hash);
            break;
        default:
            return false;
    }
    return ($hash && $stored_hash == $hash);
}

function user_needs_new_hash($account)
{
    // Check whether this was an updated password.
    if ((substr($account->pass, 0, 3) != '$S$') || (strlen($account->pass) != DRUPAL_HASH_LENGTH)) {
        return true;
    }
    // Ensure that $count_log2 is within set bounds.
    $count_log2 = _password_enforce_log2_boundaries(variable_get('password_count_log2', DRUPAL_HASH_COUNT));
    // Check whether the iteration count used differs from the standard number.
    return (_password_get_count_log2($account->pass) !== $count_log2);
}
