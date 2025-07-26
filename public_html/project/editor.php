<?php
require(__DIR__ . "/../../partials/nav.php");

if (!is_logged_in()) {
    die(header("Location: login.php"));
}
if(isset($_GET['success'])) {
    flash("Successfully updated meeting!", "success");
}
//ng569 7/25/2025
//This page is a hidden one. he only way to access this page is through redirect. I didn't want anyone to be able to access this page to reduce clutter on the nav
//The meeting id is passed through GET and the page does an SQL call to grab all relevent information and output it

$access = true;
$users = [];
$info = [];
$attendees_id = [];
$message = "";
$user_gmt = get_user_gmt();

if (isset($_GET["id"])) {
    $access = true;
    $id = $_GET["id"];
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT ma.meeting_id, ma.attendee_id, u.username, u.email, u.tz_loc, m.host, m.message, m.meetingDate, m.gmt FROM meeting_attendees AS ma
        JOIN Users AS u ON ma.attendee_id = u.id 
        JOIN Meetings AS m ON ma.meeting_id = m.id
        WHERE ma.meeting_id = :id"
    );
    try {
        $stmt->execute([":id" => $id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $info = $results;
            if ($info[0]["host"] != get_username() && !has_role("admin")) {
                $access = false;
                flash("You do not have access to modify this meeting Code 13", "danger");
                error_log("Error getting meeting" . var_export($e->errorInfo, true));
            }
        } else {
            flash("This meeting doesn't exist or you do not have access to this meeting, please contact an admin for support Code 14", "danger");
            error_log("Error getting meeting" . var_export($e->errorInfo, true));
            $access = false;
        }
    } catch (PDOException $e) {
        flash("This meeting doesn't exist or you do not have access to this meeting, please contact an admin for support Code 12", "danger");
        error_log("Error getting meeting" . var_export($e->errorInfo, true));
        $access = false;
    }

    foreach ($info as $attendee) {
        array_push($attendees_id, $attendee["attendee_id"]);
    }

    //getting all users
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT id, email, username, gmt, tz_name, tz_loc, tz_abb FROM Users"
    );
    try {
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $users = $results;
        } else {
            flash("Couldn't retrieve Users. Code 7.", "danger");
        }
    } catch (PDOException $e) {
        flash("Couldn't retrieve Users. Code 6." . var_export($e->errorInfo, true), "danger");
    }

    //header("Location: " . $_SERVER['PHP_SELF']); //reload page
}

if (isset($_GET["timestamp"])) {
    $timestamp = se($_GET, "timestamp", null, false);
    $can_update = !empty($timestamp);
    if ($can_update) {
        $formattedTime = date("Y-m-d H:i:s", strtotime($timestamp));
    }
}

if (isset($_GET["timestamp"]) && isset($_POST['checkbox'])) {
    $user_ids = $_POST['checkbox'];
    $datetime = se($_GET, "timestamp", null, false);
    $message = se($_POST, "message", null, false);
    $error = false;
    $success = false;

    if (empty($user_ids)) {
        flash("At least one attendee is required", "warning");
        $error = true;
    }
    if (empty($datetime)) {
        flash("Day and Time is required", "warning");
        $error = true;
    }

    if (!$error) {
        $formattedTime = date("Y-m-d H:i:s", strtotime($datetime));
        $username = get_username();

        //Creating new meeting
        $db = getDB();
        $sql = "UPDATE Meetings SET message = :message, meetingDate = :meetingDate, gmt = :gmt WHERE id = :id;";
        $stmt = $db->prepare($sql);
        try {
            $stmt->execute([":message" => $message, ":meetingDate" => $formattedTime, ":gmt" => $user_gmt, ":id" => $id]);
            $success = true;
        } catch (PDOException $e) {
            flash("There was an error updating the meeting, please try again later", "danger");
            error_log("Error updating meeting: " . var_export($e->errorInfo, true));
            $success = false;
        }

        $add_id = array_diff($user_ids, $attendees_id);
        $delete_id = array_diff($attendees_id, $user_ids);

        //add attendees
       if (!empty($add_id)) {
            $placeholders = implode(',', array_fill(0, count($add_id), "(?, ?)"));
            $params = [];
            foreach ($add_id as $uid) {
                $params[] = $id;
                $params[] = $uid;
            }
            $stmt = $db->prepare("INSERT INTO meeting_attendees (meeting_id, attendee_id) VALUES $placeholders");
            try {
                $stmt->execute($params);
                $success = true;
            } catch (PDOException $e) {
                flash("Error adding attendees.", "danger");
                error_log("Add error: " . var_export($e->errorInfo, true));
                $success = false;
            }
        }

        // Delete attendees
        if (!empty($delete_id)) {
            $conditions = [];
            $params = [];
            foreach ($delete_id as $uid) {
                $conditions[] = "(meeting_id = ? AND attendee_id = ?)";
                $params[] = $id;
                $params[] = $uid;
            }
            $sql = "DELETE FROM meeting_attendees WHERE " . implode(" OR ", $conditions);
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute($params);
                $success = true;
            } catch (PDOException $e) {
                flash("Error deleting attendees.", "danger");
                error_log("Delete error: " . var_export($e->errorInfo, true));
                $success = false;
            }
        }
    }
    if ($success) header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id . "&timestamp=" . $datetime . "&success=" . $success);
}

?>

<?php if ($access): ?>
    <p style="margin: 10px;">Meeting date and time is based on your current location of <?php echo get_user_loc() . get_user_gmt() ?></p>
    <p style="text-align: center; color:Yellow; margin:0px;">Please enter date and time and click "Update Table" to update the table times below</p>
    <form method="GET">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="mb-3">
            <label for="username">Date and Time</label>
            <input type="datetime-local" value="<?php echo (isset($_GET['timestamp'])) ? $_GET['timestamp'] : ""; ?>" name="timestamp" id="timestamp" required />
            <input type="submit" class="btn btn-info" value="Update Table" name="update" />
        </div>
    </form>
    <?php if (isset($_GET["timestamp"])): ?>
        <form method="POST" onsubmit="return validate(this);">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Username</th>
                        <th scope="col">Email</th>
                        <th scope="col">Respective Time</th>
                        <th scope="col">Location</th>
                        <th scope="col">Timezone</th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <th scope="row">
                            <?php if (in_array($user['id'], $attendees_id)): ?>
                                <input type="checkbox" class="form-check-input" checked name="checkbox[]" value="<?php echo $user["id"]; ?>" id="checkbox">
                            <?php else: ?>
                                <input type="checkbox" class="form-check-input" name="checkbox[]" value="<?php echo $user["id"]; ?>" id="checkbox">
                            <?php endif ?>
                        </th>
                        <td> <?php echo $user['username']; ?></td>
                        <td> <?php echo $user['email']; ?></td>
                        <td>
                            <?php
                            if (isset($formattedTime)) {
                                echo convertTimezone($formattedTime, $user_gmt, $user['gmt']);
                            }
                            ?>
                        </td>
                        <td> <?php echo $user['tz_loc']; ?></td>
                        <td> <?php echo $user['tz_name'] . " (" . $user['tz_abb'] . $user['gmt'] . ")"; ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text">Message (Max 255 characters)</span>
                </div>
                <textarea class="form-control" name="message" maxlength="255" aria-label="With textarea"><?php echo $info[0]['message'] ?></textarea>
            </div>
            <input type="submit" style="width: 200px;" value="Save" name="save" />
        </form>
    <?php endif ?>

<?php endif ?>

<script>
    function validate(form) {
        let isValid = true;
        const cb = form.querySelectorAll('input[name="checkbox[]"]');
        const checkedCount = Array.from(cb).filter(cb => cb.checked).length;
        if (checkedCount === 0) {
            flash("At least one attendee must be selected.", "danger");
            isValid = false;
        }
        return isValid;
    }
</script>

<?php
require(__DIR__ . "/../../partials/flash.php");
?>