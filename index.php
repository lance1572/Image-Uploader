<?php

include "st.php";
require_once "$serverRoot/lib/file.php";

$title = "Create, Edit, or Delete Event";
$css   = array("dialogs");
$js    = array("events_admin", "components/jquery.tablesorter.min");

// The default year being the current year
$year = date("Y");


$smarty = get_smarty();

$smarty->assign("css", $css);
$smarty->assign("js", $js);
$smarty->assign("title", $title);
$smarty->assign('year', $year);



// Set how many news articles to display on each page
$smarty->assign('events_items_per_page', 1);

$smarty->display('admin/event-management/index.tpl');

?>
