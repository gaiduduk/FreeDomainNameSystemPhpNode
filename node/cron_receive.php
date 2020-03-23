<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/node/domain_utils.php";

$domains = get("domains");
$servers = get("servers");

if ($domains == null)
    error("domains are null");

//set domains
$success_domains = domains_set($domains, $servers);

//return last domains
$current_domains = array();
foreach ($domains as $domain) {
    if (!in_array($domain["domain_name"], $success_domains)) {
        $domain_key = scalar("select domain_key from domain_keys where domain_name = '" . uencode($domain["domain_name"])
            . "' and domain_key_hash = '" . uencode($domain["domain_key_hash"]) . "'");
        if ($domain_key != null) {
            $current_domain = domain_get($domain["domain_name"]);
            $current_domain["domain_prev_key"] = $domain_key;
            $current_domains[] = $current_domain;
        }
    }
}
file_put_contents("receive", json_encode(array(
    "domains" => $domains,
    "current_domains" => $current_domains,
    "success_domains" => $success_domains,
)));
if (sizeof($current_domains) > 0)
    echo json_encode(array(
        "domains" => $current_domains,
        "servers" => servers_get(array_column($current_domains, "domain_name")),
    ));

//download last version
foreach ($success_domains as $domain_name) {

    $active_server_repo_hash = domain_get($domain_name)["server_repo_hash"];

    $self_server_repo_hash = scalar("select server_repo_hash from servers "
        . " where domain_name = '" . uencode($domain_name) . "' "
        . " and server_host_name = '" . uencode($host_name) . "'");

    if ($self_server_repo_hash != $active_server_repo_hash) {
        $server_host_name = scalar("select server_host_name from servers "
            . " where domain_name = '" . uencode($domain_name) . "' "
            . " and server_repo_hash = '" . uencode($active_server_repo_hash) . "' limit 1");

        $repo_string = http_get("$server_host_name/$domain_name/app.zip");
        $repo_path = $_SERVER["DOCUMENT_ROOT"] . "/$domain_name/app.zip";
        file_put_contents($repo_path, $repo_string);
        domain_repo_set($domain_name, $repo_path);
    }
}
