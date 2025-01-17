<?php

namespace Gazelle\Manager;

use Gazelle\Util\Crypto;
use Gazelle\Util\Mail;
use Gazelle\Util\Proxy;

class Referral extends \Gazelle\Base {
    private $accounts;
    private readonly \Gazelle\Util\Proxy $proxy;

    public $readOnly;

    final const CACHE_ACCOUNTS = 'referral_accounts';
    final const CACHE_BOUNCER = 'bouncer_status';
    // Do not change the ordering in this array after launch.
    final const ACCOUNT_TYPES = ['Gazelle (API)', 'Gazelle Games', 'Tentacles', 'Luminance', 'Gazelle (HTML)', 'PTP'];
    // Accounts which use the user ID instead of username.
    final const ID_TYPES = [3, 4, 5];

    public function __construct() {
        $this->accounts = self::$cache->get_value(self::CACHE_ACCOUNTS);
        $this->proxy = new Proxy(REFERRAL_KEY, REFERRAL_BOUNCER);

        if ($this->accounts === false) {
            self::$db->prepared_query("SELECT ID, Site, Active, Type FROM referral_accounts");
            $this->accounts = self::$db->has_results() ? self::$db->to_array('ID') : [];
            foreach ($this->accounts as &$acc) {
                $acc["UserIsId"] = in_array($acc["Type"], self::ID_TYPES);
                unset($acc);
            }
            self::$cache->cache_value(self::CACHE_ACCOUNTS, $this->accounts, 86400 * 30);
        }

        $this->readOnly = !apcu_exists('DB_KEY');

        if (!$this->readOnly) {
            $url = self::$db->scalar("SELECT URL FROM referral_accounts LIMIT 1");
            if ($url) {
                $this->readOnly = Crypto::dbDecrypt($url) == null;
            }
        }
    }

    public function checkBouncer() {
        if (!OPEN_EXTERNAL_REFERRALS) {
            // Not strictly true, but we don't care about this if referrals are closed.
            return true;
        }

        if (!count($this->accounts)) {
            return true;
        }

        $status = self::$cache->get_value(self::CACHE_BOUNCER);
        if ($status === false) {
            $req = $this->proxy->fetch(SITE_URL, [], [], false);
            $status = $req == null ? 'dead' : 'alive';
            self::$cache->cache_value(self::CACHE_BOUNCER, $status, 60 * 15);
        }

        return $status == 'alive';
    }

    public function generateToken() {
        return 'OPS|' . randomString(64) . '|OPS';
    }

    public function getTypes() {
        return self::ACCOUNT_TYPES;
    }

    public function getAccounts() {
        return $this->accounts;
    }

    public function getActiveAccounts() {
        return array_filter($this->accounts,
            fn($i) => $i['Active'] == '1' && !$this->readOnly);
    }

    public function getAccount($id) {
        return array_key_exists($id, $this->accounts) ? $this->accounts[$id] : null;
    }

    public function getFullAccount($id) {
        self::$db->prepared_query("
            SELECT ID, Site, URL, User, Password, Active, Type, Cookie
            FROM referral_accounts
            WHERE ID = ?
            ", $id
        );

        $account = null;
        if (self::$db->has_results()) {
            $account = self::$db->next_record();
            foreach (['URL', 'User', 'Password', 'Cookie'] as $key) {
                if (array_key_exists($key, $account)) {
                    $account[$key] = Crypto::dbDecrypt($account[$key]);
                }
            }
            $account["Cookie"] = json_decode($account["Cookie"], true);
            $account["UserIsId"] = in_array($account["Type"], self::ID_TYPES);
        }

        return $account;
    }

    public function getFullAccounts() {
        self::$db->prepared_query("
            SELECT ID, Site, URL, User, Password, Active, Type, Cookie
            FROM referral_accounts");

        if (self::$db->has_results()) {
            $accounts = self::$db->to_array('ID', MYSQLI_ASSOC);
            foreach ($accounts as &$account) {
                foreach (['URL', 'User', 'Password', 'Cookie'] as $key) {
                    if (array_key_exists($key, $account)) {
                        $account[$key] = Crypto::dbDecrypt($account[$key]);
                    }
                }
                $account["Cookie"] = json_decode($account["Cookie"], true);
                $account["UserIsId"] = in_array($account["Type"], self::ID_TYPES);
            }
            return $accounts;
        }

        return [];
    }

    public function createAccount($site, $url, $user, $password, $active, $type, $cookie) {
        if ($this->readOnly) {
            return;
        }

        if (strlen($cookie) < 2) {
            $cookie = '[]';
        }

        json_decode($cookie);
        if (json_last_error() != JSON_ERROR_NONE) {
            $cookie = '[]';
        }

        self::$db->prepared_query("
            INSERT INTO referral_accounts
                (Site, URL, User, Password, Active, Type, Cookie)
            VALUES
                (?,    ?,   ?,    ?,        ?,      ?,    ?)
            ", $site, Crypto::dbEncrypt($url), Crypto::dbEncrypt($user),
            Crypto::dbEncrypt($password), $active, $type, Crypto::dbEncrypt($cookie)
        );

        self::$cache->delete_value(self::CACHE_ACCOUNTS);
    }

    private function updateCookie($id, $cookie) {
        if ($this->readOnly) {
            return;
        }

        self::$db->prepared_query("
            UPDATE referral_accounts
            SET Cookie = ?
            WHERE ID = ?
            ", Crypto::dbEncrypt(json_encode($cookie)), $id
        );
    }

    public function updateAccount($id, $site, $url, $user, $password, $active, $type, $cookie) {
        if ($this->readOnly) {
            return;
        }

        $account = $this->getFullAccount($id);
        if (strlen($cookie) < 2) {
            $cookie = '[]';
        }
        json_decode($cookie);
        if (json_last_error() != JSON_ERROR_NONE) {
            $cookie = '[]';
        }
        if ($cookie == '[]') {
            $cookie = json_encode($account["Cookie"]);
        }
        if (strlen($password) == 0) {
            $password = $account["Password"];
        }
        self::$db->prepared_query("
            UPDATE referral_accounts SET
                Site = ?,
                URL = ?,
                User = ?,
                Password = ?,
                Active = ?,
                Type = ?,
                Cookie = ?
            WHERE ID = ?
            ", $site, Crypto::dbEncrypt($url), Crypto::dbEncrypt($user),
            Crypto::dbEncrypt($password), $active, $type, Crypto::dbEncrypt($cookie), $id
        );

        self::$cache->delete_value(self::CACHE_ACCOUNTS);
    }

    public function deleteAccount($id) {
        self::$db->prepared_query("DELETE FROM referral_accounts WHERE ID = ?", $id);

        self::$cache->delete_value(self::CACHE_ACCOUNTS);
    }

    public function getReferredUsers($startDate, $endDate, $site, $username, $invite, \Gazelle\Util\Paginator $paginator, $view) {
        if (empty($startDate)) {
            $startDate = \Gazelle\Util\Time::offset(-3600 * 24 * 30);
        }

        $filter = ['ru.Created BETWEEN ? AND coalesce(?, now())'];
        $params = [$startDate, $endDate];

        if ($view === 'pending') {
            $filter[] = 'ru.Active = 0';
        } else if ($view === 'processed') {
            $filter[] = 'ru.Active = 1';
        }

        if (!empty($site)) {
            $filter[] = 'ru.Site LIKE ?';
            $params[] = $site;
        }

        if (!empty($username)) {
            $filter[] = '(ru.Username LIKE ? OR um.Username LIKE ?)';
            array_push($params, $username, $username);
        }

        if (!empty($invite)) {
            $filter[] = 'ru.InviteKey LIKE ?';
            $params[] = $invite;
        }

        $filter = implode(' AND ', $filter);

        $results = self::$db->scalar("
            SELECT count(*)
            FROM referral_users ru
            LEFT JOIN users_main um ON (um.ID = ru.UserID)
            WHERE $filter
            ", ...$params
        );
        $paginator->setTotal($results);

        array_push($params, $paginator->limit(), $paginator->offset());
        self::$db->prepared_query("
            SELECT ru.ID     AS id,
                ru.UserID    AS user_id,
                ru.Site      AS site,
                ru.Username  AS username,
                ru.Created   AS created,
                ru.Joined    AS joined,
                ru.IP        AS ip,
                ru.Active    AS active,
                ru.InviteKey AS invite
            FROM referral_users ru
            LEFT JOIN users_main um ON (um.ID = ru.UserID)
            WHERE $filter
            ORDER BY ru.Created DESC
            LIMIT ? OFFSET ?
            ", ...$params
        );
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    public function deleteUserReferral($id) {
        self::$db->prepared_query("
            DELETE FROM referral_users WHERE ID = ?
            ", $id
        );
    }

    public function validateCookie($acc)
    {
        return match ($acc["Type"]) {
            0 => $this->validateGazelleCookie($acc),
            1 => true,
            2 => $this->validateTentacleCookie($acc),
            3, 4, 5 => $this->validateLuminanceCookie($acc),
            default => false,
        };
    }

    private function validateGazelleCookie($acc) {
        $url  = $acc["URL"] . 'ajax.php';

        $result = $this->proxy->fetch($url, ["action" => "index"], $acc["Cookie"], false);
        $json = json_decode($result["response"], true);

        return $json["status"] === 'success';
    }

    private function validateTentacleCookie(array $acc): bool {
        $result = $this->proxy->fetch($acc["URL"], [], $acc["Cookie"], false);
        return str_contains($result["response"], "authKey:");
    }

    private function validateLuminanceCookie(array $acc): bool {
        $result = $this->proxy->fetch($acc["URL"], [], $acc["Cookie"], false);
        return str_contains($result["response"], "authkey");
    }

    public function loginAccount(&$acc)
    {
        return match ($acc["Type"]) {
            0 => $this->loginGazelleAccount($acc),
            1 => true,
            2 => $this->loginTentacleAccount($acc),
            3 => $this->loginLuminanceAccount($acc),
            4 => $this->loginGazelleHTMLAccount($acc),
            5 => $this->loginPTPAccount($acc),
            default => false,
        };
    }

    private function loginGazelleAccount(&$acc) {
        if ($this->validateGazelleCookie($acc)) {
            return true;
        }

        $url = $acc["URL"] . "login.php";

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeplogged" => "1"], [], true);

        if ($result["status"] == 200) {
            $acc["Cookie"] = $result["cookies"];
            $this->updateCookie($acc["ID"], $acc["Cookie"]);
        }

        return $result["status"] == 200;
    }

    private function loginTentacleAccount(&$acc) {
        if ($this->validateTentacleCookie($acc)) {
            return true;
        }

        $url = $acc["URL"] . "user/login";

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeplogged" => "1"], [], true);

        if ($result["status"] == 200) {
            $acc["Cookie"] = $result["cookies"];
            $this->updateCookie($acc["ID"], $acc["Cookie"]);
        }

        return $result["status"] == 200;
    }

    private function loginLuminanceAccount(&$acc) {
        if ($this->validateLuminanceCookie($acc)) {
            return true;
        }

        $url = $acc["URL"] . "login";

        $result = $this->proxy->fetch($url, [], [], false);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$doc->loadHTML($result["response"]);
        $xpath = new \DOMXPath($doc);
        $token = $xpath->evaluate("string(//input[@name='token']/@value)");

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeploggedin" => "1",
            "token" => $token, "cinfo" => "1024|768|24|0",
            "iplocked" => "1"], $result["cookies"], true);

        if ($result["status"] == 200) {
            $acc["Cookie"] = $result["cookies"];
            $this->updateCookie($acc["ID"], $acc["Cookie"]);
        }

        return $result["status"] == 200;
    }

    private function loginGazelleHTMLAccount(&$acc) {
        if ($this->validateLuminanceCookie($acc)) {
            return true;
        }

        $url = $acc["URL"] . "login.php";

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeplogged" => "1"], [], true);

        if ($result["status"] == 200) {
            $acc["Cookie"] = $result["cookies"];
            $this->updateCookie($acc["ID"], $acc["Cookie"]);
        }

        return $result["status"] == 200;
    }

    private function loginPTPAccount(&$acc) {
        if ($this->validateLuminanceCookie($acc)) {
            return true;
        }

        $url = $acc["URL"] . "login_finish.php";

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeplogged" => "1"], [], true);

        if ($result["status"] == 200) {
            $acc["Cookie"] = $result["cookies"];
            $this->updateCookie($acc["ID"], $acc["Cookie"]);
        }

        return $result["status"] == 200;
    }

    public function verifyAccount($acc, $user, $key)
    {
        return match ($acc["Type"]) {
            0 => $this->verifyGazelleAccount($acc, $user, $key),
            1 => $this->verifyGGNAccount($acc, $user, $key),
            2 => $this->verifyTentacleAccount($acc, $user, $key),
            3 => $this->verifyLuminanceAccount($acc, $user, $key),
            4 => $this->verifyGazelleHTMLAccount($acc, $user, $key),
            5 => $this->verifyPTPAccount($acc, $user, $key),
            default => "Unrecognised account type",
        };
    }

    private function verifyGazelleAccount($acc, $user, $key) {
        if (!$this->loginGazelleAccount($acc)) {
            return "Internal error 10";
        }

        $url = $acc["URL"] . 'ajax.php';

        $result = $this->proxy->fetch($url, ["action" => "usersearch", "search" => $user],
            $acc["Cookie"], false);
        $json = json_decode($result["response"], true);

        if ($json["status"] === 'success') {
            $match = false;
            $userId = null;
            foreach ($json["response"]["results"] as $userResult) {
                if ($userResult["username"] == $user) {
                    $match = true;
                    $userId = $userResult["userId"];
                    break;
                }
            }

            if ($match) {
                $result = $this->proxy->fetch($url, ["action" => "user", "id" => $userId],
                    $acc["Cookie"], false);
                $json = json_decode($result["response"], true);

                if (str_contains($json["response"]["profileText"], $key)) {
                    return true;
                } else {
                    return "Token not found. Please try again.";
                }
            }
        }

        return "Token not found. Please try again.";
    }

    private function verifyGGNAccount($acc, $user, $key) {
        $url = $acc["URL"] . 'api.php';

        $result = $this->proxy->fetch($url, ["request" => "user", "name" => $user,
            "key" => $acc["Password"]], [], false);
        $json = json_decode($result["response"], true);

        if (str_contains($json["response"]["profileText"], $key)) {
            return true;
        } else {
            return "Token not found. Please try again.";
        }
    }

    private function verifyTentacleAccount($acc, $user, $key) {
        if (!$this->loginTentacleAccount($acc)) {
            return "Internal error 11";
        }

        $url = $acc["URL"] . 'user/profile/' . $user;

        $result = $this->proxy->fetch($url, [], $acc["Cookie"], false);

        if (str_contains($result["response"], $key)) {
            return true;
        } else {
            return "Token not found. Please try again.";
        }
    }

    private function verifyLuminanceAccount($acc, $user, $key) {
        if (!$this->loginLuminanceAccount($acc)) {
            return "Internal error 12";
        }

        $url = $acc["URL"] . 'user.php';

        $result = $this->proxy->fetch($url, ["id" => $user], $acc["Cookie"], false);

        if (str_contains($result["response"], $key)) {
            return true;
        } else {
            return "Token not found. Please try again.";
        }
    }

    private function verifyGazelleHTMLAccount($acc, $user, $key) {
        if (!$this->loginGazelleHTMLAccount($acc)) {
            return "Internal error 13";
        }

        $url = $acc["URL"] . 'user.php';

        $result = $this->proxy->fetch($url, ["id" => $user],
            $acc["Cookie"], false);

        if (str_contains($result["response"], $key)) {
            return true;
        } else {
            return "Token not found. Please try again.";
        }
    }

    private function verifyPTPAccount($acc, $user, $key) {
        if (!$this->loginPTPAccount($acc)) {
            return "Internal error 14";
        }

        $url = $acc["URL"] . 'user.php';

        $result = $this->proxy->fetch($url, ["id" => $user], $acc["Cookie"], false);

        if (str_contains($result["response"], $key)) {
            return true;
        } else {
            return "Token not found. Please try again.";
        }
    }

    public function generateInvite($acc, $username, $email) {
        $existing = self::$db->scalar("
            SELECT Username
            FROM referral_users
            WHERE Username = ? AND Site = ?
            ", $username, $acc["Site"]
        );

        if ($existing) {
            return [false, "Account already used for referral, join " . BOT_DISABLED_CHAN . " on " . BOT_SERVER . " for help."];
        }

        $inviteKey = randomString();
        self::$db->prepared_query("
            INSERT INTO invites
                   (InviteKey, Email, Reason, Expires)
            VALUES (?,         ?,     ?,      now() + INTERVAL 3 DAY)
            ", $inviteKey, $email,
                'This user was referred from their account on ' . $acc["Site"] . '.'
        );

        self::$db->prepared_query("
            INSERT INTO referral_users
                   (Username, Site, IP, InviteKey)
            VALUES (?,        ?,    ?,  ?)
            ", $username, $acc["Site"], $_SERVER["REMOTE_ADDR"], $inviteKey
        );

        if (REFERRAL_SEND_EMAIL) {
            (new Mail)->send($email, 'You have been invited to ' . SITE_NAME,
                self::$twig->render('email/referral.twig', [
                    'email' => $email,
                    'inviter_key' => $inviteKey,
                ])
            );
        }

        return [true, $inviteKey];
    }
}
