<?php

include "st.php";
include_once "$serverRoot/lib/file.php";

$smarty = get_smarty();
$title  = "Create Event";

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
    'url' => "add event url here",
    'async' => true,
    'defer' => true
];

$js  = array("components/jquery-ui.min",
             "admin/datepick",
             $add_event,
             "components/bootstrap.min",
             "components/cropper.min",
             "events_admin_create"
);

$default_add = 'Location';
$faculty   = array();
$show_form = true;

$subject       = isset($_POST['subject']) ? $_POST['subject'] : '';
$details       = isset($_POST['details']) ? $_POST['details'] : '';
$aux           = isset($_POST['aux']) ? $_POST['aux'] : '';
$location      = isset($_POST['location']) ? $_POST['location'] : $default_add;
$event_raw_title   = isset($_POST['event-title']) ? $_POST['event-title'] : '';
$start_date    = isset($_POST['start-date']) ? $_POST['start-date'] : '';
$end_date      = isset($_POST['end-date']) ? $_POST['end-date'] : '';
$start_time    = isset($_POST['start-time']) ? $_POST['start-time'] : '';
$end_time      = isset($_POST['end-time']) ? $_POST['end-time'] : '';
$image_file    = isset($_POST['cropped-image']) ? $_POST['cropped-image'] : '';


// Take event title and remove the whitespace (or other characters) from
// the beginning and the end of a string.
// https://www.php.net/manual/en/function.trim.php
$event_str_title = str_replace(array("'", '"', "“", "”", "‘", "’"), '',
    $event_raw_title);
$event_title = trim($event_str_title);

// Split the image file into an array of strings
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
        $miltime = date('H:i', $start);
        $human_time = date("g:ia", $start);
        $range[$miltime] = $human_time;
    }

    return $range;
}

$time = hoursRange();

function validateURL($URL) {
    $pattern_1 = "/^http:\/\/|(www\.)[a-z0-9]+([\-\.]{1}[a-z0-9]+)*" .
                 "\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/";

    if (preg_match($pattern_1, $URL)) {
        return true;
    } else{
        return false;
    }
}

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

    // The end date needs to be greater than the start date.
    if ((strtotime($_POST['end-date'])) < strtotime($_POST['start-date'])) {
        array_push($errors, "The end date cannot come before the start date.");
    }

    // Check to see if the title of the event has double quotes,
    // single quotes and apostrophes. If so, then we remove them


    // Check to see if the days set are the same. If they are, the times need to
    // be checked to make sure they come after each other and not set to the
    // same time.
    $start_time = $_POST['start-time'];
    $end_time = $_POST['end-time'];
    $start_date = $_POST['start-date'];
    $end_date = $_POST['end-date'];

    if (strtotime($end_date) == strtotime($start_date)) {
        if (strtotime($end_time) < strtotime($start_time)) {
            array_push($errors,
                "The end time cannot come before the start time.");
        } elseif (strtotime($start_time) == strtotime($end_time)) {
            array_push($errors, "The start and end times cannot be the same.");
        }
    }

    // Checking that the data is formatted properly
    if (! preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",
        $start_date)) {
        array_push($errors,
            "The start date is not formatted. Please use YYYY-MM-DD format.");
    }

    if (! preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",
        $end_date)) {
        array_push($errors,
            "The end date is not formatted. Please use YYYY-MM-DD format.");
    }

    return $errors;
}

// We want to assign an array to the var or Smarty will cry.
$errors = array();

/*
 * Give $submitted var a falsey that will be used to detect
 * if the form has been submitted.
 */
$submitted = false;

/*
 * Check if the submit button was pressed. Give the $submitted
 * variable a truthy value. Then pass the submit_form() function
 * which does the error checking into the $errors variable. In the
 * tpl we check the true/false value of the $submitted var. If true, then
 * we know the submit has been pressed. Proceeed onward through the condition.
 */

$append_start_date = '';

if (isset($_POST['submit'])) {
    $submitted = true;
    $errors    = submit_form();

    // If there are NO errors in submission, then we do the fopen, fwrite, and
    // then fclose
    if (count($errors) == 0) {
        try {
            // Generate a random string to name the file in a unique way (so as
            // not to conflicte with other events that may occur on the same
            // day).
            $generated = generate_random_string(5);

            // Here we take the string of data that was split up
            // and we want to decode it and save it as jpg
            if (! empty($image_file)) {
                    $stripped_image = base64_decode($image_data[1], true);
                    $file_path      = $serverRoot.'/images/event-uploads/';
                    $file_name      = $start_date.'--'.$generated. '.jpg';
                    $image_file     = $file_path.$file_name;
                if (is_writable($file_path)) {
                    file_put_contents($image_file, $stripped_image);
                } else {
                    error_log("Unable to save event information. Could not " .
                              "open filehandle for write. Reason: " . $errors);
                    $errors = array("There was an issue uploading the image. " .
                                    "Please try again later.");
                }
            }

            $seminar_data = array(
                'subject'       => $subject,
                'details'       => $details,
                'aux'           => "Host:" . " " . $aux,
                'location'      => $location,
                'title'         => $event_title,
                'start-date'    => $start_date,
                'end-date'      => $end_date,
                'start-time'    => $start_time,
                'end-time'      => $end_time,
                'image-file'    => $file_name
            );

            $event_file = fopen("{$serverRoot}/events/" . $start_date .
                  '-' . $generated . '.json', 'w');
            $json = json_encode($seminar_data, JSON_PRETTY_PRINT);

            if (! $event_file) {
                error_log("Unable to save event information. Could not open " .
                          "filehandle for writing. Reason: " . $errors);
                throw new Exception("Could not open filehandle for writing.");
            }

            fwrite($event_file, $json);
            fclose($event_file);
        } catch (Exception $e) {
            // Show an error dialog
            error_log("Unable to save event information. Reason: " . $errors);

            // If any of the try fails the msg below will show
            $errors = array("Unable to save event information.");
        }
    } else {
        // Show an error dialog if there are form errors
        error_log("Unable to save event information. Reason: " .
            implode(";", $errors));
        $errors;
    }
}

$faculty = get_faculty();

// Assign vars to Smarty
$smarty->assign('js', $js);
$smarty->assign('css', $css);
$smarty->assign('title', $title);
$smarty->assign('event_title', $event_title);
$smarty->assign('faculty', $faculty);
$smarty->assign('time', $time);
$smarty->assign('aux', $aux);
$smarty->assign('image_file', $image_file);
$smarty->assign('subject', $subject);
$smarty->assign('details', $details);
$smarty->assign('location', $location);
$smarty->assign('start_date', $start_date);
$smarty->assign('end_date', $end_date);
$smarty->assign('start_time', $start_time);
$smarty->assign('end_time', $end_time);
$smarty->assign('submitted', $submitted);
$smarty->assign('errors', $errors);
$smarty->assign('show_form', $show_form);

// Assign tpl file
$smarty->display('admin/event-management/create_event.tpl');

?>
