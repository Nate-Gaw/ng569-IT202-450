<?php
function convertTimezone($timestamp, $fromOffset, $toOffset) {
    
    $shift = $toOffset - $fromOffset;

    $time = strtotime($timestamp);

    $newTime = $time + ($shift * 3600);

    return date('Y-m-d H:i:s', $newTime);
}
?>