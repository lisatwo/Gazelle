<?php
// This is a file of miscellaneous functions that are called so damn often
// that it'd just be annoying to stick them in namespaces.

use Gazelle\Util\{Type, Time, Irc};

/**
 * Return true if the given string is an integer. The original Gazelle developers
 * must have thought the only numbers out there were integers when naming this function.
 */
function is_number(mixed $integer): bool {
    return filter_var($integer, FILTER_VALIDATE_INT) !== false;
}

/**
 * Awful anglo-centric hack for handling plurals ;-)
 *
 * @param int $n the number
 * @param string $plural override the default 's' with something else e.g. 'es'
 * @return string '' if 1, otherwise 's'
 */
function plural(int $n, string $plural = 's'): string {
    return $n == 1 ? '' : $plural;
}

/**
 * Awful anglo-centric hack for handling articles
 *
 * @param int $n the number
 * @param string $article string to use if you don't want the default 'a' e.g. 'an'
 * @return string 'a' (or $article) if $n == 1, otherwise $n
 */
function article(int $n, $article = 'a') {
    return $n == 1 ? $article : $n;
}

/**
 * HTML-escape a string for output.
 */
function display_str(mixed $Str): string {
    if (is_null($Str) || is_array($Str)) {
        return '';
    }
    if ($Str != '' && !is_number($Str)) {
        $Str = make_utf8($Str);
        $Str = htmlspecialchars($Str, ENT_NOQUOTES|ENT_SUBSTITUTE, 'UTF-8', false);
        $Str = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,6};)/m", '&amp;', $Str);

        $Replace = [
            "<",">",
            '&#128;','&#130;','&#131;','&#132;','&#133;','&#134;','&#135;','&#136;',
            '&#137;','&#138;','&#139;','&#140;','&#142;','&#145;','&#146;','&#147;',
            '&#148;','&#149;','&#150;','&#151;','&#152;','&#153;','&#154;','&#155;',
            '&#156;','&#158;','&#159;'
        ];

        $With = [
            '&lt;','&gt;',
            '&#8364;','&#8218;','&#402;','&#8222;','&#8230;','&#8224;','&#8225;','&#710;',
            '&#8240;','&#352;','&#8249;','&#338;','&#381;','&#8216;','&#8217;','&#8220;',
            '&#8221;','&#8226;','&#8211;','&#8212;','&#732;','&#8482;','&#353;','&#8250;',
            '&#339;','&#382;','&#376;'
        ];

        $Str = str_replace($Replace, $With, $Str);
    }
    return $Str;
}

/**
 * Returns ratio
 */
function ratio(int $uploaded, $downloaded, $digits = 2): bool|string {
    return match(true) {
        $downloaded == 0 && $uploaded == 0 => false,
        $downloaded == 0 => '∞',
        default => number_format(max($uploaded / $downloaded - (0.5 / 10 ** $digits), 0), $digits),
    };
}

/**
 * Gets the CSS class corresponding to a ratio
 */
function ratio_css(float $ratio): string {
    if ($ratio < 0.1) { return 'r00'; }
    if ($ratio < 0.2) { return 'r01'; }
    if ($ratio < 0.3) { return 'r02'; }
    if ($ratio < 0.4) { return 'r03'; }
    if ($ratio < 0.5) { return 'r04'; }
    if ($ratio < 0.6) { return 'r05'; }
    if ($ratio < 0.7) { return 'r06'; }
    if ($ratio < 0.8) { return 'r07'; }
    if ($ratio < 0.9) { return 'r08'; }
    if ($ratio < 1.0) { return 'r09'; }
    if ($ratio < 2.0) { return 'r10'; }
    if ($ratio < 5.0) { return 'r20'; }
    return 'r50';
}

/**
 * Calculates and formats a ratio.
 */
function ratio_html(int $uploaded, int $downloaded, $wantColor = true) {
    $ratio = ratio($uploaded, $downloaded);
    if ($ratio === false) {
        return '--';
    }
    if ($ratio === '∞') {
        return $wantColor ? '<span class="tooltip r99" title="Infinite">∞</span>' : '∞';
    }
    if ($wantColor) {
        $ratio = sprintf('<span class="tooltip %s" title="%s">%s</span>',
            ratio_css((float)$ratio),
            ratio($uploaded, $downloaded, 5),
            $ratio
        );
    }
    return $ratio;
}

/**
 * Gets the query string of the current page, minus the parameters in $Exclude,
 * plus the parameters in $NewParams
 *
 * @param array $NewParams New query items to insert into the URL
 */
function get_url(array $Exclude = [], bool $Escape = true, bool $Sort = false, array $NewParams = []): string {
    $QueryItems = NULL;
    parse_str($_SERVER['QUERY_STRING'], $QueryItems);

    foreach ($Exclude as $Key) {
        unset($QueryItems[$Key]);
    }
    if ($Sort) {
        ksort($QueryItems);
    }
    $NewQuery = http_build_query(array_merge($QueryItems, $NewParams), '');
    return $Escape ? display_str($NewQuery) : $NewQuery;
}

/**
 * Format a size in bytes as a human readable string in KiB/MiB/...
 *        Note: KiB, MiB, etc. are the IEC units, which are in base 2.
 *            KB, MB are the SI units, which are in base 10.
 *
 * @param int $levels Number of decimal places. Defaults to 2, unless the size >= 1TB, in which case it defaults to 4.
 *                    or 0 in the case of bytes.
 */
function byte_format(float|int|null $size, int $levels = 2): string {
    $units = [' B', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', ' YiB'];
    $size = (float)$size;
    for ($steps = 0; abs($size) >= 1024; $steps++) {
        $size /= 1024;
    }
    if (func_num_args() == 1 && $steps >= 4) {
        $levels++;
    }
    if ($steps == 0) {
        $levels = 0;
    }
    return number_format($size, $levels) . $units[$steps];
}

/**
 * Format a number as a multiple of its highest power of 1000 (e.g. 10035 -> '10.04k')
 */
function human_format(float|int $number): string {
    $steps = 0;
    while ($number >= 1000) {
        $steps++;
        $number = $number / 1000;
    }
    return match ($steps) {
        0 => (string)round($number),
        1 => round($number, 2) . 'k',
        2 => round($number, 2) . 'M',
        3 => round($number, 2) . 'G',
        4 => round($number, 2) . 'T',
        5 => round($number, 2) . 'P',
        default => round($number, 2) . 'E + ' . $steps * 3,
    };
}

/**
 * Given a formatted string of a size, get the number of bytes it represents.
 */
function get_bytes(string $size): int {
    [$value, $unit] = sscanf($size, "%f%s");
    $unit = ltrim($unit);
    if (empty($unit)) {
        return $value ? (int)round($value) : 0;
    }
    return match (strtolower($unit[0])) {
        'k' => round($value *              1024),
        'm' => round($value *         1_048_576),
        'g' => round($value *     1_073_741_824),
        't' => round($value * 1_099_511_627_776),
        default => 0,
    };
}

/**
 * Un-HTML-escape a string for output.
 *
 * It's like the above function, but in reverse.
 */
function reverse_display_str(string $Str): string {
    if ($Str != '' && !is_number($Str)) {
        $Replace = [
            '&#39;','&quot;','&lt;','&gt;',
            '&#8364;','&#8218;','&#402;','&#8222;','&#8230;','&#8224;','&#8225;','&#710;',
            '&#8240;','&#352;','&#8249;','&#338;','&#381;','&#8216;','&#8217;','&#8220;',
            '&#8221;','&#8226;','&#8211;','&#8212;','&#732;','&#8482;','&#353;','&#8250;',
            '&#339;','&#382;','&#376;'
        ];

        $With = [
            "'",'"',"<",">",
            ' ','&#130;','&#131;','&#132;','&#133;','&#134;','&#135;','&#136;',
            '&#137;','&#138;','&#139;','&#140;','&#142;','&#145;','&#146;','&#147;',
            '&#148;','&#149;','&#150;','&#151;','&#152;','&#153;','&#154;','&#155;',
            '&#156;','&#158;','Ÿ'
        ];
        $Str = str_replace($Replace, $With, $Str);
        $Str = str_replace("&amp;", "&", $Str);
    }
    return $Str;
}

/**
 * Sanitize a string for use as a filename.
 *
 * @param string $name to escape
 * @return string contents with all OS meta-characters removed.
 */
function safeFilename(string $name): string {
    return str_replace(['"', '*', '/', ':', '<', '>', '?', '\\', '|'], '', $name);
}

/**
 * Determine the redirect header to use based on the client HTTP_REFERER or fallback
 *
 * @param string $fallback URL to use HTTP_REFERER is empty
 * @return string redirect URL
 */
function redirectUrl(string $fallback): string {
    return empty($_SERVER['HTTP_REFERER']) ? $fallback : $_SERVER['HTTP_REFERER'];
}

/**
 * Make sure $_GET['auth'] is the same as the user's authorization key
 * Should be used for any user action that relies solely on GET.
 */
function authorize(bool $Ajax = false): void {
    global $Viewer;
    if ($Viewer->auth() === ($_REQUEST['auth'] ?? $_REQUEST['authkey'] ?? '')) {
        return;
    }
    Irc::sendMessage(STATUS_CHAN,
        $Viewer->username() . " just failed authorize on "
        . $_SERVER['REQUEST_URI'] . (!empty($_SERVER['HTTP_REFERER']) ? " coming from " . $_SERVER['HTTP_REFERER'] : ""));
    error('Invalid authorization key. Go back, refresh, and try again.', $Ajax);
}

function parse_user_agent(string $useragent): array {
    if (preg_match("/^Lidarr\/([0-9\.]+) \((.+)\)$/", $useragent, $Matches) === 1) {
        $OS = explode(" ", $Matches[2]);
        $browserUserAgent = [
            'Browser' => 'Lidarr',
            'BrowserVersion' => substr($Matches[1], 0, strrpos($Matches[1], '.')),
            'OperatingSystem' => $OS[0] === 'macos' ? 'macOS' : ucfirst($OS[0]),
            'OperatingSystemVersion' => $OS[1] ?? null
        ];
    } elseif (preg_match("/^VarroaMusica\/([0-9]+(?:dev)?)$/", $useragent, $Matches) === 1) {
        $browserUserAgent = [
            'Browser' => 'VarroaMusica',
            'BrowserVersion' => str_replace('dev', '', $Matches[1]),
            'OperatingSystem' => null,
            'OperatingSystemVersion' => null
        ];
    } elseif (in_array($useragent, ['Headphones/None', 'whatapi [isaaczafuta]'])) {
        $browserUserAgent = [
            'Browser' => $useragent,
            'BrowserVersion' => null,
            'OperatingSystem' => null,
            'OperatingSystemVersion' => null
        ];
    } else {
        $Result = new WhichBrowser\Parser($useragent);
        $Browser = $Result->browser;
        if (empty($Browser->getName())) {
            $Browser = $Browser->using;
        }
        $browserUserAgent = [
            'Browser' => $Browser->getName(),
            'BrowserVersion' => explode('.', $Browser->getVersion())[0],
            'OperatingSystem' => $Result->os->getName(),
            'OperatingSystemVersion' => $Result->os->getVersion()
        ];
    }
    foreach (['Browser', 'BrowserVersion', 'OperatingSystem', 'OperatingSystemVersion'] as $Key) {
        if ($browserUserAgent[$Key] === "") {
            $browserUserAgent[$Key] = null;
        }
    }
    return $browserUserAgent;
}

/**
 * Display a critical error and kills the page.
 *
 * $Error Error type. Automatically supported:
 *    403, 404, 0 (invalid input), -1 (invalid request)
 *    If you use your own string for Error, it becomes the error description.
 * $NoHTML If true, the header/footer won't be shown, just the description.
 * $Log If true, the user is given a link to search $Log in the site log.
 */
function error(int|string $Error, bool $NoHTML = false, bool $Log = false): never {
    global $Debug, $Document, $Viewer, $Twig;
    require_once(__DIR__ . '/../sections/error/index.php');
    if (isset($Viewer)) {
        $Debug->profile($Viewer, $Document);
    }
    exit;
}

/**
 * Print JSON status result with an optional message and die.
 */
function json_die($Status, $Message="bad parameters"): never {
    json_print($Status, $Message);
    exit;
}

/**
 * Print JSON status result with an optional message.
 */
function json_print($Status, $Message) {
    if ($Status == 'success' && $Message) {
        $response = ['status' => $Status, 'response' => $Message];
    } elseif ($Message) {
        $response = ['status' => $Status, 'error' => $Message];
    } else {
        $response = ['status' => $Status, 'response' => []];
    }

    print(json_encode(add_json_info($response)));
}

function json_error($Code): never {
    echo json_encode(add_json_info(['status' => 'failure', 'error' => $Code, 'response' => []]));
    exit;
}

function json_or_error($JsonError, $Error = null, $NoHTML = false) {
    if (defined('AJAX')) {
        json_error($JsonError);
    } else {
        error($Error ?? $JsonError, $NoHTML);
    }
}

function add_json_info($Json) {
    if (!isset($Json['info'])) {
        $Json = array_merge($Json, [
            'info' => [
                'source' => SITE_NAME,
                'version' => 1,
            ],
        ]);
    }
    global $Viewer;
    if (!isset($Json['debug']) && $Viewer instanceof \Gazelle\User && $Viewer->permitted('site_debug')) {
        global $Debug;
        $info = ['debug' => ['queries' => $Debug->get_queries()]];
        if (class_exists('Sphinxql') && !empty(\Sphinxql::$Queries)) {
            $info['searches'] = \Sphinxql::$Queries;
        }
        $Json = array_merge($Json, ['debug' => $info]);
    }
    return $Json;
}

function dump($thing): void {
    echo "<pre>" . json_encode($thing, JSON_PRETTY_PRINT) . "</pre>";
}

function show(mixed $data): void {
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

/**
 * Utility function that unserializes an array, and then if the unserialization fails,
 * it'll then return an empty array instead of a null or false which will break downstream
 * things that require an incoming array
 *
 * @param string $array
 * @return array
 */
function unserialize_array($array) {
    $array = empty($array) ? [] : unserialize($array);
    return (empty($array)) ? [] : $array;
}

/**
 * Helper function to return an string of N elements from an array.
 *
 * (e.g. [2, 4, 6] into a list of query placeholders (e.g. '?,?,?')
 * By default '?' is used, but a custom placeholder may be specified,
 * such as '(?)' or '(?, now(), 100)', for use in a bulk insert.
 *
 * @param array $list The list of elements
 * @param string $placeholder ('?' by default).
 * @return string The resulting placeholder string.
 */
function placeholders(array $list, $placeholder = '?') {
    return implode(',', array_fill(0, count($list), $placeholder));
}

/**
 * Parse a string to determine if it valid UTF-8
 */
function is_utf8(string $s): bool {
    return (bool)preg_match('/
        ^(?:
            [\x09\x0A\x0D\x20-\x7E]            # ASCII
          | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
          | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
          | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
          | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
          | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
          | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
          | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
        )*$
        /xs', $s
    );
}

/**
 * Detect the encoding of a string and transform it to UTF-8.
 */
function make_utf8(?string $str): string {
    if (is_null($str) || $str === '' || is_utf8($str)) {
        return $str;
    }
    $encoding = mb_detect_encoding($str, 'UTF-8, ISO-8859-1', true);
    return $encoding === 'ISO-8859-1' ? @mb_convert_encoding($str, 'UTF-8', $encoding) : $str;
}

/**
 * Generate a random string drawn from alphanumeric characters
 * but omitting lowercase l, uppercase I and O (to avoid confusion).
 *
 * @param  int    $len
 * @return string random alphanumeric string
 */
function randomString($len = 32) {
    $alphabet = str_split('abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789');
    $max = count($alphabet);
    $mask = (int)pow(2, ceil(log($len, 2))) - 1;
    $out = '';
    while (strlen($out) < $len) {
        $n = ord(openssl_random_pseudo_bytes(1)) & $mask;
        if ($n < $max) {
            $out .= $alphabet[$n];
        }
    }
    return $out;
}

/**
 * Shorten a string
 *
 * @param string $text string to cut
 * @param int    $maxLength cut at length
 * @param bool   $force force cut at length instead of at closest word
 * @param bool   $ellipsis Show dots at the end
 * @return string formatted string
 */
function shortenString(string $text, int $maxLength, bool $force = false, bool $ellipsis = true): string {
    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        return $text;
    }
    if ($force) {
        $short = mb_substr($text, 0, $maxLength, 'UTF-8');
    } else {
        $short = mb_substr($text, 0, $maxLength, 'UTF-8');
        $words = explode(' ', $short);
        if (count($words) > 1) {
            array_pop($words);
            $short = implode(' ', $words);
        }
    }
    if ($ellipsis) {
        $short .= "\xE2\x80\xA6"; // U+2026 HORIZONTAL ELLIPSIS
    }
    return $short;
}

function proxyCheck(string $IP): bool {
    foreach (ALLOWED_PROXY as $allowed) {
        //based on the wildcard principle it should never be shorter
        if (strlen($IP) < strlen($allowed)) {
            continue;
        }

        //since we're matching bit for bit iterating from the start
        for ($j = 0, $jl = strlen($IP); $j < $jl; ++$j) {
            //completed iteration and no inequality
            if ($j === $jl - 1 && $IP[$j] === $allowed[$j]) {
                return true;
            }

            //wildcard
            if ($allowed[$j] === '*') {
                return true;
            }

            //inequality found
            if ($IP[$j] !== $allowed[$j]) {
                break;
            }
        }
    }
    return false;
}

/*** Time and date functions ***/

/*
 * Returns a <span> by default but can optionally return the raw time
 * difference in text (e.g. "16 hours and 28 minutes", "1 day, 18 hours").
 */
function time_diff($TimeStamp, $Levels = 2, $Span = true, $StartTime = false) {
    return Time::diff($TimeStamp, $Levels, $Span, $StartTime);
}

/*** Paranoia functions ***/

// The following are used throughout the site:
// uploaded, ratio, downloaded: stats
// lastseen: approximate time the user last used the site
// uploads: the full list of the user's uploads
// uploads+: just how many torrents the user has uploaded
// snatched, seeding, leeching: the list of the user's snatched torrents, seeding torrents, and leeching torrents respectively
// snatched+, seeding+, leeching+: the length of those lists respectively
// uniquegroups, perfectflacs: the list of the user's uploads satisfying a particular criterion
// uniquegroups+, perfectflacs+: the length of those lists
// If "uploads+" is disallowed, so is "uploads". So if "uploads" is in the array, the user is a little paranoid, "uploads+", very paranoid.

// The following are almost only used in /sections/user/user.php:
// requiredratio
// requestsfilled_count: the number of requests the user has filled
//   requestsfilled_bounty: the bounty thus earned
//   requestsfilled_list: the actual list of requests the user has filled
// requestsvoted_...: similar
// artistsadded: the number of artists the user has added
// torrentcomments: the list of comments the user has added to torrents
//   +
// collages: the list of collages the user has created
//   +
// collagecontribs: the list of collages the user has contributed to
//   +
// invitedcount: the number of users this user has directly invited

/**
 * Return whether currently logged in user can see $Property on a user with $Paranoia, $UserClass and (optionally) $UserID
 * If $Property is an array of properties, returns whether currently logged in user can see *all* $Property ...
 *
 * $Property The property to check, or an array of properties.
 * $Paranoia The paranoia level to check against.
 * $UserClass The user class to check against (Staff can see through paranoia of lower classed staff)
 * $UserID Optional. The user ID of the person being viewed
 * return mixed   1 representing the user has normal access
 *                2 representing that the paranoia was overridden,
 *                false representing access denied.
 */

function check_paranoia(string $Property, string|array $Paranoia, int $UserClass, int|false $UserID = false): int|false {
    if (!is_array($Paranoia)) {
        $Paranoia = unserialize($Paranoia);
    }
    if (!is_array($Paranoia)) {
        $Paranoia = [];
    }
    global $Viewer;
    if (($UserID !== false) && ($Viewer->id() == $UserID)) {
        return PARANOIA_ALLOWED;
    }

    $May = !in_array($Property, $Paranoia) && !in_array($Property . '+', $Paranoia);
    if ($May)
        return PARANOIA_ALLOWED;

    if ($Viewer->permitted('users_override_paranoia', $UserClass)) {
        return PARANOIA_OVERRIDDEN;
    }
    $Override=false;
    switch ($Property) {
        case 'downloaded':
        case 'ratio':
        case 'uploaded':
        case 'lastseen':
            if ($Viewer->permitted('users_mod', $UserClass))
                return PARANOIA_OVERRIDDEN;
            break;
        case 'snatched': case 'snatched+':
            if ($Viewer->permitted('users_view_torrents_snatchlist', $UserClass))
                return PARANOIA_OVERRIDDEN;
            break;
        case 'uploads': case 'uploads+':
        case 'seeding': case 'seeding+':
        case 'leeching': case 'leeching+':
            if ($Viewer->permitted('users_view_seedleech', $UserClass))
                return PARANOIA_OVERRIDDEN;
            break;
        case 'invitedcount':
            if ($Viewer->permitted('users_view_invites', $UserClass))
                return PARANOIA_OVERRIDDEN;
            break;
    }
    return false;
}

function httpProxy(): ?string {
    $proxy = getenv('HTTP_PROXY');
    if ($proxy !== false) {
        return (string)$proxy;
    } elseif (HTTP_PROXY !== false) { /** @phpstan-ignore-line */
        return (string)HTTP_PROXY;
    }
    return null;
}

/**
 * Geolocate an IP address using the database
 *
 * @param string|int $IP the ip to fetch the country for
 * @return string the country of origin
 */
function geoip($IP): string {
    static $IPs = [];
    if (isset($IPs[$IP])) {
        return $IPs[$IP];
    }
    if (is_number($IP)) {
        $Long = $IP;
    } else {
        $Long = sprintf('%u', ip2long($IP));
    }
    if (!$Long || $Long == 2130706433) { // No need to check cc for 127.0.0.1
        return 'XX';
    }
    return '?';
}
