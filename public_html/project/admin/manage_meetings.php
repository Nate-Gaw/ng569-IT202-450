<?php
//note we need to go up 1 more directory
require(__DIR__ . "/../../../partials/nav.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    die(header("Location: " . get_url("landing.php")));
}

$meetings = [];

$db = getDB();
$stmt = $db->prepare(
    "SELECT * FROM Meetings AS m"
);
try {
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results) {
        $meetings = $results;
    } else {
        array_push($meetings, "There are no meetings.");
    }
} catch (PDOException $e) {
    flash("There was an error finding meetings, please contact an admin for support Code 5", "danger");
    error_log("Error toggling meetings" . var_export($e->errorInfo, true));
}

//handle delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {
    $id = $_POST["delete_id"];
    $stmt2 = $db->prepare("DELETE FROM Meetings WHERE id = :id");
    try {
        $stmt2->execute([":id" => $id]);
    } catch (PDOException $e) {
        flash("There was an error deleting meetings, please contact an admin for support Code 6", "danger");
        error_log("Error deleting meetings" . var_export($e->errorInfo, true));
    }
    header("Location: " . $_SERVER['PHP_SELF']); //reload page
}
?>

<br>
<h1 style="text-align: center;">Manage All Meetings</h1>
<br>
<table class="table table-hover table-sm">
    <thead>
        <tr>
            <th scope="col">Index</th>
            <th scope="col">Creator</th>
            <th scope="col">Message</th>
            <th scope="col">Date & Time</th>
            <th scope="col">Original Date & Time + GMT</th>
            <th scope="col">DELETE</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($meetings as $meeting) { ?>
            <tr>
                <th scope="row"><?php echo $meeting['id']; ?></th>
                <td><?php echo $meeting['host']; ?></td>
                <td><?php echo $meeting['message']; ?></td>
                <td>
                    <?php
                    echo convertTimezone($meeting['meetingDate'], $meeting['gmt'], get_user_gmt());
                    ?>
                </td>
                <td><?php echo $meeting['meetingDate'] . $meeting["gmt"]; ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="delete_id" value="<?php echo $meeting['id']; ?>">
                        <button id="delete" type="submit" class="btn btn-danger"> Delete?</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
<?php
//note we need to go up 1 more directory
require_once(__DIR__ . "/../../../partials/flash.php");
?>