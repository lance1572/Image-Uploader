<?php

include "st.php";
include_once "$serverRoot/lib/file.php";
$smarty = get_smarty();
$title  = "Edit Event";

$css = array(
        "dialogs",
        "components/jquery-ui.min",
        "seminar_events_bootstrap",
        "seminar_events_admin",
        "components/cropper"
    );

// The framework's smarty get_js_links plugin allows us to hotlink
// with fine control over the async and defer flags in this way:
$add_event = [
    'url' => "Location here",
    'async' => true,
    'defer' => true
];

$js  = array("components/jquery-ui.min",
             "admin/datepick",
             $add_event,
             "components/bootstrap.min",
             "components/cropper.min",
             "events_admin_edit"
);

$subject       = (isset($_POST['subject'])) ? $_POST['subject'] : '';
$details       = (isset($_POST['details'])) ? $_POST['details'] : '';
$aux           = (isset($_POST['aux'])) ? $_POST['aux'] : '';
$location      = (isset($_POST['location'])) ? $_POST['location'] : '';
$event_title   = (isset($_POST['event-title'])) ? $_POST['event-title'] : '';
$start_date    = (isset($_POST['start-date'])) ? $_POST['start-date'] : '';
$end_date      = (isset($_POST['end-date'])) ? $_POST['end-date'] : '';
$start_time    = (isset($_POST['start-time'])) ? $_POST['start-time'] : '';
$end_time      = (isset($_POST['end-time'])) ? $_POST['end-time'] : '';
$image_file    = (isset($_POST['cropped-image'])) ? $_POST['cropped-image'] : '';
$video_url     = (isset($_POST['video-url'])) ? $_POST['video-url'] : '';
$event_raw_title   = isset($_POST['event-title']) ? $_POST['event-title'] : '';

// Take event title and remove the whitespace (or other characters) from
// the beginning and the end of a string.
// https://www.php.net/manual/en/function.trim.php
$event_str_title = str_replace(array("'", '"', "“", "”", "‘", "’"), '', $event_raw_title);
$event_title = trim($event_str_title);
$faculty   = array();

/**
 * Set base directory for json
 * files.
 */
$base_dir = $serverRoot . '/conf/events';

// Split the base64 image file into an array of strings
// [0] is the data:image/jpeg;base64
// [1] is the binary string we want eventually use
// to convert the string to a jpg further down line:229
$image_data    = explode(',', $image_file);

// Create Time Function

function hoursRange() {
    $start = strtotime('7:30am');
    $end   = strtotime('8:00pm');
    $range = array();

    while ($start !== $end) {
        $start = strtotime('+30 minutes', $start);
        // Create military time and human time
        // for the value and labels on the dropdown
        $miltime         = date('H:i', $start);
        $human_time      = date("g:ia", $start);
        $range[$miltime] = $human_time;
    }

    return $range;
}

$time = hoursRange();

// Set Form Status

$success   = false;
$submitted = false;
$show_form = true;

$errors    = array();

/**
 * Check to see if the 'filename' query string has been passed since
 * we are submitting to the same page we need to check before
 * it is sent.
 */

if (isset($_GET['filename'])) {
    $_GET['filename'] = trim(stripcslashes(strip_tags($_GET['filename'])));
    $filename = $_GET['filename'];
} elseif (isset($_POST['filename']) && isset($_POST['submit'])) {
    $show_form = false;
    $submitted = true;
    $filename  = $_POST['filename'];
} else {
    array_push($errors, "Invalid data.");
}

function validate_url($youtube_url) {
    $pattern = "~^(?:https?://)?(?:www[.])?(?:youtube[.]com/watch[?]v=|youtu[.]be/)([^&]{11})~x";
    $valid = preg_match($pattern, $youtube_url);

    if ($valid) {
        echo "Valid";
    } else {
        echo "Invalid";
    }

    return $youtube_url;
}

// Set the filename path to file
$file = "$serverRoot/conf/events/$filename" . '.json';

/*
 * Submit Form function will go through and
 * check each input for a value. If user has
 * any of them empty, it will return errors for them to fix.
 */
function submit_form() {
    $errors = array();

    // Check if each input has been filled out.
    if (empty($_POST['subject'])) {
        array_push($errors, "The subject cannot be left blank.");
    }

    if (empty($_POST['details'])) {
        array_push($errors, "The details cannot be left blank.");
    }

    if (empty($_POST['event-title'])) {
        array_push($errors, "The title cannot be left blank.");
    }

    if (empty($_POST['aux'])) {
        array_push($errors, "The host selection cannot be left blank.");
    }

    if (empty($_POST['start-date'])) {
        array_push($errors, "The start date cannot be left blank.");
    }

    if (empty($_POST['end-date'])) {
        array_push($errors, "The end date cannot be left blank.");
    }

    if (empty($_POST['start-time'])) {
        array_push($errors, "The start time cannot be left blank.");
    }

    if (empty($_POST['end-time'])) {
        array_push($errors, "The end time cannot be left blank.");
    }

    // We are checking for optional http/https/www
    // We are requesting that the domain to be there
    if (! empty($_POST['video-url'])) {
        $url_pattern = '~^(?:https?://)?(?:www[.])?(?:youtube[.]com ' .
                       '/watch[?]v=|youtu[.]be/)([^&]{11})~x';
        if (! preg_match($url_pattern, $_POST['video-url'])) {
            array_push($errors, "There are errors is the url");
        }
    } else {
        // Do nothing
    }

    // The end date needs to be greater than the start date.
    if ((strtotime($_POST['end-date'])) < (strtotime($_POST['start-date']))) {
        array_push($errors, "The end date cannot come before the start date.");
    }

    $start_time = $_POST['start-time'];
    $end_time = $_POST['end-time'];
    $start_date = $_POST['start-date'];
    $end_date = $_POST['end-date'];

    // Check to see if the days set are the same. If they are, the times need
    // to be checked to make sure they come after each other and not set to the
    // same time.
    if (strtotime($end_date) == strtotime($start_date)) {
        if (strtotime($end_time) < strtotime($start_time)) {
            array_push($errors,
                "The end time cannot come before the start time.");
        } elseif (strtotime($start_time) == strtotime($end_time)) {
            array_push($errors, "The start and end times cannot be the same.");
        }
    }

    // Checking that the data is formatted properly.
    if (! preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",
        $start_date)) {
        array_push($errors,
            "The start date is not in the right format (YYYY-MM-DD).");
    }

    if (! preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",
        $end_date)) {
        array_push($errors,
            "The end date is not in the right format (YYYY-MM-DD).");
    }


    return $errors;
}

// Check to see if file exists, readable and writable
if (is_readable($file) && is_writable($file)) {
    // Get json file and decode it

    $current_data = file_get_contents($file);
    $json = json_decode($current_data, true);

    // Pass json to event file variable

    $event_file = $json;

    /**
     * The conditions below check a string to see
     * if it has 'Host: ' or ', Ph.D.' in it. It they
     * do then it is removed. This will help continuity
     * since the json files have a mix of different values
     */

    $host = $event_file['aux'];

    if (str_starts_with($host, 'Host: ')) {
        $event_file['aux'] = substr($host, 6);
    }

    $host_end = $event_file['aux'];

    if (str_ends_with($host, ', Ph.D.')) {
        $event_file['aux'] = substr($host_end, 0, -7);
    }
}

// Submit the form. We are going to check certain conditions
if (isset($_POST['submit'])) {
    // Set $submitted to true

    $submitted = true;
    $errors = submit_form();

    // Check for Form errors.
    // Generate a random string to name the file in a unique way (so as not
    // to conflicte with other events that may occur on the same day).
    $generated = generate_random_string(5);

    // Here we take the string of data that was split up
    // and we want to decode it and save it as jpg
    if (!empty($image_file)) {
            $stripped_image = base64_decode($image_data[1], true);
            $file_path      = $serverRoot.'/images/event-uploads/';
            $file_name      = $start_date.'--'.$generated. '.jpg';
            $image_file     = $file_path.$file_name;
        if(is_writable($file_path)) {
            file_put_contents($image_file, $stripped_image);
        } else {
            error_log("Unable to save event information. Could not open " .
                      "filehandle for writing. Reason: " . $errors);
            $errors = array("There was an issue uploading the image. Please try uploading later.");
        }
    }

    if (count($errors) == 0) {
        try {
            // Pass the post variables
            // Remove the submit
            unset($_POST['submit']);

            /**
             * Create an array to hold the key/value pairs
             * that are referenced in the json file
             */
            $data = array(
                'subject'       => $subject,
                'details'       => $details,
                'aux'           => "Host:" . " " . $aux,
                'location'      => $location,
                'title'         => $event_title,
                'start-date'    => $start_date,
                'end-date'      => $end_date,
                'start-time'    => $start_time,
                'end-time'      => $end_time,
                'image-file'    => $file_name,
                'video-url'     => $video_url
            );

            if (! $file) {
                error_log("Unable to save event information. Could not open " .
                    "filehandle for writing. Reason: " . $errors);
                throw new Exception("Could not open filehandle for writing.");
            }

            // Pass data to form data then encode it then put it to the file.
            $form_data = $data;
            $final_data = json_encode($form_data, JSON_PRETTY_PRINT);

            file_put_contents($file, $final_data);
        } catch (Exception $e) {
            // show an error dialog
            error_log("Unable to save event information. Reason: " . $errors);
            // if any of the try fails the msg below will show
            $errors = array("Unable to save event information.");
        }
    } else {
        // show an error dialog if there are form errors
        error_log("Unable to save event information. Reason: " . implode(";", $errors));
        $errors;
    }
}

$faculty = get_faculty();

$smarty->assign('js', $js);
$smarty->assign('css', $css);
$smarty->assign('event_file', $event_file);
$smarty->assign('title', $title);
$smarty->assign('faculty', $faculty);
$smarty->assign('event_title', $event_title);
$smarty->assign('aux', $aux);
$smarty->assign('subject', $subject);
$smarty->assign('details', $details);
$smarty->assign('location', $location);
$smarty->assign('start_date', $start_date);
$smarty->assign('end_date', $end_date);
$smarty->assign('start_time', $start_time);
$smarty->assign('end_time', $end_time);
$smarty->assign('time', $time);
$smarty->assign('submitted', $submitted);
$smarty->assign('errors', $errors);
$smarty->assign('show_form', $show_form);
$smarty->assign('video_url', $video_url);
$smarty->assign('image_file', $image_file);
//$smarty->assign('filename', $file_name);

// Assign tpl file

$smarty->display('admin/event-management/event_edit.tpl');
