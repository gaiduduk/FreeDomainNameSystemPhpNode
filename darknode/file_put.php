<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/darknode/domain_utils.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/darkcoin/api/login.php";

$path = get_required("path");
$data = get("data");

if ($data == null && (sizeof($_FILES) == 0))
    error("Data is empty");


$file = getFile($path, $user_id);

if (strlen($file["file_data"]) == MAX_SMALL_DATA_LENGTH
    && scalar("select count(*) from files where file_data = '" . $file["file_data"] . "'") == 1)
    if (!unlink($_SERVER["DOCUMENT_ROOT"] . "/darknode/files/" . substr($file["file_data"], FILE_SIZE_HEX_LENGTH)))
        error("file cannot be replaced");

if ($data != null) {
    if (strlen($data) >= MAX_SMALL_DATA_LENGTH) {
        $hash = hash(HASH_ALGO, $data);
        $file_size_hex = sprintf("%0" . FILE_SIZE_HEX_LENGTH . "X", strlen($data));
        if (!file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/darknode/files/" . $hash, $data))
            error("file cannot be save to storage");
        updateList("files", array("file_data" => $file_size_hex . $hash), "file_id", $file_id);
    } else {
        updateList("files", array("file_data" => $data), "file_id", $file_id);
    }
}
if (sizeof($_FILES) != 0) {
    foreach ($_FILES as $key => $file) {
        if ($file["size"] >= MAX_SMALL_DATA_LENGTH) {
            $hash = hash_file(HASH_ALGO, $file["tmp_name"]);
            $file_size_hex = sprintf("%0" . FILE_SIZE_HEX_LENGTH . "X", strlen($data));
            if (!move_uploaded_file($file["tmp_name"], $_SERVER["DOCUMENT_ROOT"] . "/darknode/files/" . $hash))
                error("file cannot be save to storage");
            updateList("files", array("file_data" => $file_size_hex . $hash), "file_id", $file_id);
        } else {
            $data = file_get_contents($file["tmp_name"]);
            updateList("files", array("file_data" => $data), "file_id", $file_id);
            unlink($file["tmp_name"]);
        }
    }
}

echo json_encode(array("message" => null));

