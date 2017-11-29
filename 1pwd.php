<?php

error_reporting(0);
set_error_handler("pwd_error_handler");

define('BASE_PATH', str_replace('\\', '/', __DIR__));
define('IS_WINDOWS', stripos(PHP_OS, 'win') !== false);

const DB_PATH = BASE_PATH . '/pwd.db';
const LOG_PATH = BASE_PATH . '/error.log';

if (!file_exists(DB_PATH)) {
    exit('Db file not found.');
}

$sqlite = new SQLite3(DB_PATH);

$key = '';

$preset_auth_str = '1pwd_auth_chars';

$is_login = false;

login();

while ($is_login)
{
    echo 'Command: ';

    $command = trim(fgets(STDIN));

    switch($command)
    {
        case '1':
            search_password();
            break;

        case '2':
            read_password();
            break;

        case '3':
            add_password();
            break;

        case '4':
            echo 'Account to remove: ';
            $account = trim(fgets(STDIN));
            if (strpos($account, 'id:') === 0) {
                $id_arr = explode(':', $account);
                $id = array_pop($id_arr);
                $result = delete_password(null, $id);
            } else {
                $result = delete_password($account);
            }
            echo $result ? "Remove success.\r\n" : "Remove fail.\r\n";
            break;

        case '5':
            echo 'Account to edit: ';
            $account = trim(fgets(STDIN));
            if (strpos($account, 'id:') === 0) {
                $id_arr = explode(':', $account);
                $id = array_pop($id_arr);
                $result = edit_password(null, $id);
            } else {
                $result = edit_password($account);
            }
            echo $result ? "Edit success.\r\n" : "Edit fail.\r\n";
            break;

        case 'h':
            help();
            break;

        case 'c':
            change_password();
            break;

        case 'logout':
            exit(0);
            break;

        default:
            break;
    }
}

/**
 * 列出
 */
function read_password()
{
    global $key, $sqlite;
    $page = 1;
    $page_size = 10;
    $total = $sqlite->query("SELECT COUNT(*) FROM passwords")->fetchArray();
    $total = array_pop($total);
    $total_page = ceil($total / $page_size);
    while ($page)
    {
        $offset = ($page - 1) * $page_size;
        $result = $sqlite->query("SELECT * FROM passwords LIMIT {$offset}, {$page_size}");
        $row = $result->fetchArray();
        if (empty($row) || $page > $total_page) {
            if ($page > $total_page) $page = $total_page;
            goto input_page_num;
        }
        $data = array();
        while ($row) {
            $data[] = array(
                'id' => $row['id'],
                'account' => $row['account'],
                'passwd' => decrypt($row['passwd'], $key),
                'note' => $row['note'],
            );
            $row = $result->fetchArray();
        }
        show_table_list($data);
        input_page_num:
        echo 'page['.$page .'/'.$total_page.']: ';
        $page_str = trim(fgets(STDIN));
        if ($page_str == 'n') {
            $page = intval($page + 1);
        } elseif ($page_str == 'p') {
            $page = intval($page - 1);
        } elseif (ord($page_str) == 24) {
            return;
        } else {
            $page = intval($page_str);
        }
        $page = $page <= 0 ? 1 : $page;
    }
}

/**
 * 添加
 */
function add_password()
{
    global $key, $sqlite;
    echo 'Account: ';
    $account = trim(fgets(STDIN));
    if (ord($account) == 24) {
        return;
    }
    echo 'Password: ';
    $passwd = trim(fgets(STDIN));
    if (ord($passwd) == 24) {
        return;
    }
    echo 'Note: ';
    $note = trim(fgets(STDIN));
    if (ord($note) == 24) {
        return;
    }
    $passwd = encrypt($passwd, $key);
    $sql = "INSERT INTO passwords (account, passwd, note) VALUES ('{$account}', '{$passwd}', '{$note}')";
    return $sqlite->exec($sql);
}

/**
 * 编辑
 */
function edit_password($account, $id = 0)
{
    global $sqlite, $key;
    $id = intval($id);
    if (empty($account) && empty($id)) {
        return false;
    }
    if (ord($account) == 24) {
        return false;
    }
    if (empty($account) && $id > 0) {
        $sql = "SELECT * FROM passwords WHERE id = {$id} LIMIT 1";
    } else {
        $sql = "SELECT * FROM passwords WHERE account = '{$account}' LIMIT 1";
    }
    $result = $sqlite->query($sql)->fetchArray();
    if (empty($result)) {
        echo "Account '{$account}' not found.\r\n";
        return false;
    }
    echo "---------------------------------\r\n";
    echo "Account: {$result['account']}\r\n";
    echo "Password: ".decrypt($result['passwd'], $key)."\r\n";
    echo "Note: {$result['note']}\r\n";
    echo "---------------------------------\r\n";
    echo "New account ([Enter] for no change): ";
    $new_account = trim(fgets(STDIN));
    echo "New password ([Enter] for no change): ";
    $new_password = trim(fgets(STDIN));
    echo "New note ([Enter] for no change): ";
    $new_note = trim(fgets(STDIN));
    if (ord($new_account) == 24 || ord($new_password) == 24 || ord($new_note) == 24) {
        return false;
    }
    $set_sql_arr = [];
    empty($new_account) || $set_sql_arr[] = "account = '{$new_account}'";
    empty($new_password) || $set_sql_arr[] = "passwd = '".encrypt($new_password, $key)."'";
    empty($new_note) || $set_sql_arr[] = "note = '{$new_note}'";
    $set_sql_str = implode(', ', $set_sql_arr);
    if (!empty($set_sql_str)) {
        if (empty($account) && $id > 0) {
            $sql = "UPDATE passwords SET {$set_sql_str} WHERE id = {$id}";
        } else {
            $sql = "UPDATE passwords SET {$set_sql_str} WHERE account = '{$account}'";
        }
        $sqlite->exec($sql);
        return $sqlite->changes();
    }
    return false;
}

/**
 * 删除
 */
function delete_password($account, $id = 0)
{
    global $sqlite;
    $id = intval($id);
    if (empty($account) && empty($id)) {
        return false;
    }
    if (ord($account) == 24) {
        return false;
    }
    if (empty($account) && $id > 0) {
        $sql = "SELECT count(*) FROM passwords WHERE id = {$id}";
    } else {
        $sql = "SELECT count(*) FROM passwords WHERE account = '{$account}'";
    }
    $result = $sqlite->query($sql)->fetchArray();
    $count = array_shift($result);
    if ($count <= 0) {
        echo "Account '{$account}' not found.\r\n";
        return false;
    }
    if (empty($account) && $id > 0) {
        $sql = "DELETE FROM passwords WHERE id = {$id}";
    } else {
        $sql = "DELETE FROM passwords WHERE account = '{$account}'";
    }
    $sqlite->exec($sql);
    return $sqlite->changes();
}

/**
 * 搜索
 */
function search_password()
{
    global $key, $sqlite;
    echo 'Search: ';
    while ($search_str = trim(fgets(STDIN))) {
        if (ord($search_str) == 24) {
            break;
        }
        $result = $sqlite->query("SELECT * FROM passwords WHERE account LIKE '%{$search_str}%' OR note LIKE '%{$search_str}%'");
        $row = $result->fetchArray();
        if (empty($row)) {
            echo "Result not found.\r\n\r\n";
        } else {
            $data = array();
            while ($row) {
                $data[] = array(
                    'id' => $row['id'],
                    'account' => $row['account'],
                    'passwd' => decrypt($row['passwd'], $key),
                    'note' => $row['note'],
                );
                $row = $result->fetchArray();
            }
            show_table_list($data);
        }
        echo 'Search: ';
    }
}

/**
 * 修改登录密码(重新加密所有密码)
 */
function change_password()
{
    global $key, $sqlite, $preset_auth_str;
    echo 'Enter new password: ';
    if (!IS_WINDOWS) system('stty -echo');
    $new_password = trim(fgets(STDIN));
    if (!IS_WINDOWS) system('stty echo');

    echo "\r\nUpdate all passwords...\r\n";

    $result = $sqlite->query("SELECT * FROM passwords");
    $new_key = base64_encode(md5($new_password));
    while ($row = $result->fetchArray()) {
        $id = intval($row['id']);
        $old_passwd = trim(decrypt($row['passwd'], $key));
        $password_encrypt = encrypt($old_passwd, $new_key);
        $sql = "UPDATE passwords SET passwd = '{$password_encrypt}' WHERE id = {$id}";
        $sqlite->exec($sql);
    }

    echo "Update login password...\r\n";

    $iv = substr(md5(sprintf('%u', crc32($new_password))), 0, openssl_cipher_iv_length('aes-256-cbc'));
    $auth_passwd = encrypt($preset_auth_str, md5($new_password), $iv);
    $sql = "UPDATE auth SET auth_chars = '{$auth_passwd}' WHERE name = 'admin'";
    $sqlite->exec($sql);

    $key = $new_key;

    echo "Update success.\r\n";
}

/**
 * AES-256-CBC加密
 */
function encrypt($data, $key, $set_iv = false) {
    $encryption_key = base64_decode($key);
    $iv = $set_iv ? $set_iv : openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * AES-256-CBC解密
 */
function decrypt($data, $key, $set_iv = false) {
    $encryption_key = base64_decode($key);
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    $iv = $set_iv ? $set_iv : $iv;
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
}

/**
 * 登录验证
 */
function login()
{
    global $sqlite, $key, $is_login, $preset_auth_str;
    $sql = "SELECT auth_chars FROM auth WHERE name = 'admin' LIMIT 1";
    $result = $sqlite->query($sql)->fetchArray();
    $auth_str = isset($result['auth_chars']) ? trim($result['auth_chars']) : '';
    if ($auth_str == $preset_auth_str) {
        echo "You haven't setup the login password yet, please enter your login password: ";
    } else {
        echo "Enter password: ";
    }
    if (!IS_WINDOWS) system('stty -echo');
    $passwd = trim(fgets(STDIN));
    $iv = substr(md5(sprintf('%u', crc32($passwd))), 0, openssl_cipher_iv_length('aes-256-cbc'));
    if ($auth_str == $preset_auth_str) {
        $auth_passwd = encrypt($preset_auth_str, md5($passwd), $iv);
        $sql = "UPDATE auth SET auth_chars = '{$auth_passwd}' WHERE name = 'admin'";
        $sqlite->exec($sql);
        echo "\r\n\r\nYour login password is {$passwd}, please note it down.\r\n";
    } else {
        while ($auth_str != encrypt($preset_auth_str, md5($passwd), $iv)) {
            if (!IS_WINDOWS) system('stty echo');
            echo "\r\nPassword error!\r\n";
            echo "Enter password: ";
            if (!IS_WINDOWS) system('stty -echo');
            $passwd = trim(fgets(STDIN));
            $iv = substr(md5(sprintf('%u', crc32($passwd))), 0, openssl_cipher_iv_length('aes-256-cbc'));
        }
    }
    if (!IS_WINDOWS) system('stty echo');
    $key = base64_encode(md5($passwd));
    $is_login = true;
    help();
}

/**
 * 显示帮助
 */
function help()
{
    echo "\r\n";
    echo "-------------------------\r\n";
    echo "Welcome to 1password!\r\n";
    echo "-------------------------\r\n";
    echo "Commands:\r\n";
    echo "1. Search password\r\n";
    echo "2. List all password\r\n";
    echo "3. Add new password\r\n";
    echo "4. Remove a password\r\n";
    echo "5. Edit a password\r\n";
    echo "c. Change login password\r\n";
    echo "h. Show this help\r\n";
    echo "-------------------------\r\n";
}

/**
 * 用列表显示数据
 * @param $data
 */
function show_table_list($data)
{
    $blank_str = ' ';
    $title_arr = ['id', 'account', 'passwd', 'note'];
    $max_len_arr = array_fill_keys($title_arr, 0);

    foreach ($data as $v)
    {
        foreach ($title_arr as $title)
        {
            $len = mb_strwidth($v[$title], 'utf-8');
            if ($len > $max_len_arr[$title]) {
                $max_len_arr[$title] = $len;
            }
        }
    }

    $max_len_arr['id'] = $max_len_arr['id'] < 4 ? 4 : $max_len_arr['id'] + 2;
    $max_len_arr['account'] = $max_len_arr['account'] < 7 ? 7 : $max_len_arr['account'] + 2;
    $max_len_arr['passwd'] = $max_len_arr['passwd'] < 5 ? 5 : $max_len_arr['passwd'] + 2;
    $max_len_arr['note'] = $max_len_arr['note'] < 7 ? 7 : $max_len_arr['note'] + 2;

    $title_line = '+'.str_pad('-', $max_len_arr['id'], '-', STR_PAD_RIGHT).
                  '+'.str_pad('-', $max_len_arr['account']+1, '-', STR_PAD_RIGHT).
                  '+'.str_pad('-', $max_len_arr['passwd']+2, '-', STR_PAD_RIGHT).
                  '+'.str_pad('-', $max_len_arr['note']+1, '-', STR_PAD_RIGHT).'+'."\r\n";

    $data = array_merge(array(array('id'=>'ID', 'account'=>'Account', 'passwd'=>'Password', 'note'=>'Note')), $data);

    foreach ($data as $k => $v)
    {
        if ($k == 0) {
            echo $title_line;
        }
        echo '|';
        echo mb_str_pad($v['id'], $max_len_arr['id'], $blank_str, STR_PAD_BOTH).'| ';
        echo mb_str_pad($v['account'], $max_len_arr['account'], $blank_str, STR_PAD_RIGHT).'| ';
        $password_field = mb_str_pad($v['passwd'], $max_len_arr['passwd'], $blank_str, STR_PAD_RIGHT);
        echo $k == 0 ? $password_field . ' | ' : back_color($password_field) . ' | ';
        echo mb_str_pad($v['note'], $max_len_arr['note'], $blank_str, STR_PAD_RIGHT).'|';
        echo "\r\n";
        if ($k == 0) {
            echo $title_line;
        }
        if ($k == count($data) - 1) {
            echo $title_line;
        }
    }
}

/**
 * 多语言str_pad
 * @param $input
 * @param $pad_length
 * @param string $pad_string
 * @param int $pad_type
 * @param string $encoding
 * @return string
 */
function mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = 'utf-8')
{
    $mb_diff = mb_strwidth($input, $encoding) - strlen($input);
    return str_pad($input, $pad_length - $mb_diff, $pad_string, $pad_type);
}

/**
 * 密码背景颜色
 * 请根据自己的shell设置
 */
function back_color($str)
{
    return IS_WINDOWS ? $str : "\033[48;5;12m" . $str . "\033[0m";
}

/**
 * 错误处理
 */
function pwd_error_handler($errno, $errstr, $errfile, $errline)
{
    $error_content = '';

    switch ($errno) {
        case E_ERROR:
        case E_USER_ERROR:
            $error_content = "[ERROR][$errno] $errstr\n";
            $error_content .= "Fatal error on line [$errline]\n";
            exit(1);
            break;

        case E_WARNING:
        case E_USER_WARNING:
            $error_content = "[WARNING][".date('Y-m-d H:i:s')."] [L{$errline}] $errstr\n";
            break;

        case E_NOTICE:
        case E_USER_NOTICE:
            $error_content = "[NOTICE][".date('Y-m-d H:i:s')."] [L{$errline}] $errstr\n";
            break;

        default:
            $error_content = "[Unknown][".date('Y-m-d H:i:s')."] [L{$errline}] $errstr\n";
            break;
    }

    file_put_contents(LOG_PATH, $error_content, FILE_APPEND);

    return true;
}
