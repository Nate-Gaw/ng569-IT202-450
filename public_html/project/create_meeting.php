<?php
require(__DIR__ . "/../../partials/nav.php");
error_log("Session: " . var_export($_SESSION, true));

if (!is_logged_in()) {
    die(header("Location: login.php"));
}
?>

<?php
$user_gmt = get_user_gmt();
$users = [];

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
        $meetingId = 0;

        //Creating new meeting
        $db = getDB();
        $sql = "INSERT INTO Meetings(host, message, meetingDate, gmt) VALUES (:username, :message, :meetingDate, :gmt);";
        $stmt = $db->prepare($sql);
        try {
            $stmt->execute([":username" => $username, ":message" => $message, ":meetingDate" => $formattedTime, ":gmt" => $user_gmt]);
            $meetingId = (int)$db->lastInsertId();
            flash("Successfully adding meeting!", "success");
        } catch (PDOException $e) {
            flash("There was an error creating the meeting, please try again later", "danger");
            error_log("Error creating meeting: " . var_export($e->errorInfo, true));
        }

        //assigning new meeting with attendees
        if ($meetingId != 0) {
            $db2 = getDB();
            $sql = "INSERT INTO meeting_attendees(meeting_id, attendee_id) VALUES ";
            foreach ($user_ids as $ids) {
                $sql = $sql . "($meetingId, $ids), ";
            }
            $sql = substr($sql, 0, (strlen($sql) - 2));
            $stmt = $db2->prepare($sql);
            try {
                $stmt->execute();
                flash("Successfully added attendees to meeting!", "success");
            } catch (PDOException $e) {
                flash("There was an error adding attendees, please try again later", "danger");
                error_log("Error adding attendees: " . var_export($e->errorInfo, true));
            }
        }
    }
}
?>

<h1>Create a Meeting</h1>
<p>Meeting date and time is based on your current location of <?php echo get_user_loc() . get_user_gmt() ?></p>
<form method="GET" onsubmit="return validate(this);">
    <div class="mb-3">
        <label for="username">Date and Time</label>
        <input type="datetime-local" value="<?php echo (isset($_GET['timestamp'])) ? $_GET['timestamp'] : ""; ?>" name="timestamp" id="timestamp" required />
        <input type="submit" class="btn btn-info" value="Update Table" name="update" />
    </div>
</form>
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
                    <?php if ($user['id'] == get_user_id()):?>
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
        <textarea class="form-control" name="message" maxlength=255 aria-label="With textarea"></textarea>
    </div>
    <input type="submit" value="Create Meeting" name="save" />
</form>

<script>

</script>

<?php
require(__DIR__ . "/../../partials/flash.php");
?>