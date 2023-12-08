<?php

include_once "st.php";
include_once "$serverRoot/lib/file.php";

foreach ($_POST as $k => $v) {
    $_POST[$k] = trim(stripcslashes(strip_tags($v)));
}

$response = array();

if ($_POST['action'] == 'delete_event') {
    $json_file = $serverRoot.'/events/'.$_POST['event_file'].'.json';


    // check if JSON file exists
    if (is_file($json_file)) {
        // delete the job listing JSON file
        if (unlink($json_file)) {
            $response['status'] = 'success';
            $response['msg']    = 'Event has been successfully deleted.';
        } else {
            $response['status'] = 'error';
            $response['msg']    = 'Could not delete event: ' . $json_file;
        }
    }
}


// send status and message back to script
header('Content-type: application/json');

if (function_exists('json_encode')) {
    echo json_encode($response);
} else {
    echo '{"status":"' . $response['status'] . '","msg":"' .
        $response['msg'] . '"}';
}


?>
