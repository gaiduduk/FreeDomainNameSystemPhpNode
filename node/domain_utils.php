<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/db-utils/db.php";

function domain_hash($domain_name, $fromIndex = 0)
{
    $charsum = 0;
    for ($i = $fromIndex; $i < strlen($domain_name); $i++)
        $charsum += ord($domain_name[$i]);
    return $charsum;
}

function domain_set($domain_name, $domain_key, $domain_key_hash_next)
{
    if ($domain_key_hash_next == null)
        return false;
    $current_domain_key_hash = scalar("select domain_key_hash from domains where domain_name = '" . uencode($domain_name) . "'");
    if ($current_domain_key_hash != null) {
        $domain_key_hash = hash("sha256", $domain_key);
        if ($domain_key_hash != $current_domain_key_hash)
            return false;
        if ($domain_key_hash == $domain_key_hash_next)
            return false;
        updateList("domains", array(
            "domain_prev_key" => $domain_key,
            "domain_key_hash" => $domain_key_hash_next,
        ), "domain_name", $domain_name);
    } else {
        $server_group_id = rand(0, 1000000);
        insertList("domains", array(
            "domain_name" => $domain_name,
            "domain_name_hash" => domain_hash($domain_name),
            "domain_key_hash" => $domain_key_hash_next,
            "server_group_id" => $server_group_id,
        ));
        insertList("files", array(
            "file_id" => $server_group_id,
        ));
    }
    return true;
}

/*function domains_set($domains)
{
    $success_domain_changed = [];
    foreach ($domains as $domain) {
        if (domain_set($domain["domain_name"], $domain["domain_key"], $domain["domain_next_key_hash"]))
            $success_domain_changed[] = $domain["domain_name"];
    }
    return $success_domain_changed;
}*/

function domain_get($domain_name)
{
    return selectMap("select domain_name, domain_prev_key, domain_key_hash from domains where domain_name = '" . uencode($domain_name) . "'");
}

function domain_similar($domain_name)
{
    $domain_name_hash = domain_hash($domain_name);
    return select("select domain_name, domain_prev_key, domain_key_hash from domains "
        . " where domain_name_hash > " . ($domain_name_hash - 32768) . " and domain_name_hash < " . ($domain_name_hash + 32768)
        . " order by ABS(domain_name_hash - $domain_name_hash)  limit 5");
}

function getListFromStart($domain_prefix, $count, $user_id = null, $to_user_login = null)
{
    if ($user_id != null) {
        $where = "where user_id = $user_id and domain_name like '$domain_prefix%' limit $count";
        $domains = select("select domain_name, domain_key_hash, domain_key from domains $where");
        if ($user_id != null)
            update("update domains set user_id = null $where");
    } else {
        $domains = select("select domain_name, domain_key_hash from domains where domain_name like '$domain_prefix%' limit $count");
    }
    if ($to_user_login != null)
        foreach ($domains as $domain)
            $domain["user_login"] = $to_user_login;
    return $domains;
}


define("FILE_SIZE_HEX_LENGTH", 8);
define("HASH_ALGO", "sha256");
define("HASH_LENGTH", 64);
define("MAX_SMALL_DATA_LENGTH", HASH_LENGTH + FILE_SIZE_HEX_LENGTH);

function getFile($domain_name, $path, $mkdirs = false)
{
    $file_id = scalar("select server_group_id from domains where domain_name = '" . uencode($domain_name) . "'");
    if ($file_id == null)
        error("domain doesnt exist");

    if ($path[0] == "/") $path = substr($path, 1);
    if ($path != null) {
        $path_items = explode("/", $path);
        foreach ($path_items as $file_name) {
            $file_name = str_replace("%47", "/", $file_name);
            $next_file_id = scalar("select file_id from files where file_parent_id = $file_id and file_name = '" . uencode($file_name) . "'");
            if ($next_file_id == null) {
                if ($mkdirs) {
                    $next_file_id = insertListAndGetId("files", array(
                        "file_parent_id" => $file_id,
                        "file_name" => $file_name,
                    ));
                } else
                    error("file doesnt exist");
            }
            $file_id = $next_file_id;
        }
    }
    return selectMap("select * from files where file_id = $file_id");
}

function getData($hash_or_data)
{
    if (strlen($hash_or_data) == MAX_SMALL_DATA_LENGTH) {
        $hash = substr($hash_or_data, FILE_SIZE_HEX_LENGTH);
        return file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/node/files/" . $hash);
    }
    return $hash_or_data;
}

function getSize($hash_or_data)
{
    if (strlen($hash_or_data) == MAX_SMALL_DATA_LENGTH)
        return hexdec(substr($hash_or_data, 0, FILE_SIZE_HEX_LENGTH));
    return strlen($hash_or_data);
}