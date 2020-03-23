<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/node/domain_utils.php";

$domain_name = get("domain_name");
$server_host_name = get("server_host_name");


// reg new hostname
if ($domain_name != null && $server_host_name != null) {
    if (scalar("select count(*) from servers where domain_name = '" . uencode($domain_name) . "' "
            . " and server_host_name = '" . uencode($server_host_name) . "'") == 0) {
        insertList("servers", array(
            "domain_name" => $domain_name,
            "server_host_name" => $server_host_name
        ));
    }
}

define("MAX_DOMAIN_COUNT_IN_REQUEST", 1000);

foreach (selectList("select distinct server_host_name from servers where server_host_name <> '" . uencode($host_name) . "'") as $server_host_name) {

    $domains_in_request = select("select t2.* from servers t1 "
        . " left join domains t2 on t2.domain_name = t1.domain_name "
        . " where t1.server_host_name = '" . uencode($server_host_name) . "'"
        . " and t2.domain_set_time >= t1.server_sync_time");

    if (sizeof($domains_in_request) > 0) {

        $request = array(
            "domains" => $domains_in_request,
            "servers" => servers_get(array_column($domains_in_request, "domain_name"))
        );
        file_put_contents("rec", json_encode($request));
        $response = http_json_post($server_host_name . "/node/cron_receive.php", $request);
        file_put_contents("res", json_encode($response));

        if ($response !== false) {
            domains_set($response["domains"], $response["servers"]);
            foreach ($domains_in_request as $domain)
                update("update servers set server_sync_time = " . $domain["domain_set_time"]
                    . " where domain_name = '" . uencode($domain["domain_name"]) . "' and server_host_name = '" . uencode($server_host_name) . "'");
        }
    }
}

