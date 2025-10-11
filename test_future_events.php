<?php
require_once 'includes/future_events_handler.php';

$test_messages = [
    "i'm gonna watch the chainsaw man movie! i'm going to pick up my friend at 12",
    "meeting john at 3pm today",
    "going to the mall later",
    "tomorrow i'll go shopping"
];

foreach ($test_messages as $msg) {
    echo "<h3>Testing: \"$msg\"</h3>";
    $result = detectFutureEvent($msg);
    echo "<pre>";
    print_r($result);
    echo "</pre><hr>";
}
?>