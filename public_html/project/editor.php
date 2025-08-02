<?php
require(__DIR__ . "/../../partials/nav.php");

if (!is_logged_in()) {
    die(header("Location: login.php"));
}
if (isset($_GET['success'])) {
    flash("Successfully updated meeting!", "success");
}
//ng569 7/25/2025
//This page is a hidden one. he only way to access this page is through redirect. I didn't want anyone to be able to access this page to reduce clutter on the nav
//The meeting id is passed through GET and the page does an SQL call to grab all relevent information and output it

$access = true;
$users = [];
$info = [];
$attendees_id = [];
$attending_role_id = [];
$message = "";
$user_gmt = get_user_gmt();

if (isset($_GET["id"])) {
    $access = true;
    $id = $_GET["id"];
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT ma.attendee_id, m.host, m.message FROM meeting_attendees AS ma
        JOIN Meetings AS m ON ma.meeting_id = m.id
        WHERE ma.meeting_id = :id"
    );
    try {
        $stmt->execute([":id" => $id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $info = $results;
            if ($info[0]["host"] != get_username() && !has_role("Admin")) {
                $access = false;
                flash("You do not have access to modify this meeting Code 13", "danger");
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

    $db2 = getDB();
    $stmt2 = $db2->prepare(
        "SELECT * FROM meeting_roles
        WHERE meeting_id = :id"
    );
    try {
        $stmt2->execute([":id" => $id]);
        $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        if ($results2) {
            $meeting_role_id = $results2;
            foreach ($meeting_role_id as $attendee) {
                array_push($attending_role_id, $attendee["role_id"]);
            }
        } else {
            $noRoleMeeting = true;
        }
    } catch (PDOException $e) {
        flash("There was an error finding your meetings, please contact an admin for support. Code 1.", "danger");
        error_log("Error toggling role for user $id" . var_export($e->errorInfo, true));
        $noRoleMeeting = true;
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

    //getting all roles
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM Roles"
    );
    try {
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $roles = $results;
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

if (isset($_GET["timestamp"]) && isset($_POST['checkbox_users'])) {
    $user_ids = $_POST['checkbox_users'];
    $role_ids = $_POST['checkbox_roles'];
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

        //Update new meeting
        $db = getDB();
        $sql = "UPDATE Meetings SET message = :message, meetingDate = :meetingDate, gmt = :gmt, tz_abb = :tz_abb WHERE id = :id;";
        $stmt = $db->prepare($sql);
        try {
            $stmt->execute([":message" => $message, ":meetingDate" => $formattedTime, ":gmt" => $user_gmt, ":id" => $id, ":tz_abb" => get_user_abb()]);
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

        $add_id = array_diff($role_ids, $attending_role_id);
        $delete_id = array_diff($attending_role_id, $role_ids);

        //adding new roles
        if (!empty($add_id)) {
            $placeholders = implode(',', array_fill(0, count($add_id), "(?, ?)"));
            $params = [];
            foreach ($add_id as $uid) {
                $params[] = $id;
                $params[] = $uid;
            }
            $stmt = $db->prepare("INSERT INTO meeting_roles (meeting_id, role_id) VALUES $placeholders");
            try {
                $stmt->execute($params);
                $success = true;
            } catch (PDOException $e) {
                flash("Error adding roles.", "danger");
                error_log("Add error: " . var_export($e->errorInfo, true));
                $success = false;
            }
        }

        // Delete roles
        if (!empty($delete_id)) {
            $conditions = [];
            $params = [];
            foreach ($delete_id as $uid) {
                $conditions[] = "(meeting_id = ? AND role_id = ?)";
                $params[] = $id;
                $params[] = $uid;
            }
            $sql = "DELETE FROM meeting_roles WHERE " . implode(" OR ", $conditions);
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute($params);
                $success = true;
            } catch (PDOException $e) {
                flash("Error deleting roles.", "danger");
                error_log("Delete error: " . var_export($e->errorInfo, true));
                $success = false;
            }
        }
    }
    if ($success) header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id . "&timestamp=" . $datetime . "&success=" . $success);
}

?>

<?php if ($access): ?>
    <p style="margin: 10px;">Meeting date and time is based on your current location of <?php echo get_user_loc() ?></p>
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
            <h2>List of Users</h2>
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
                                <input type="checkbox" class="form-check-input" checked name="checkbox_users[]" value="<?php echo $user["id"]; ?>" id="checkbox">
                            <?php else: ?>
                                <input type="checkbox" class="form-check-input" name="checkbox_users[]" value="<?php echo $user["id"]; ?>" id="checkbox">
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
            <h2>List of Roles</h2>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <?php if ($role['is_active']): ?>
                            <tr>
                                <?php if (in_array($role['id'], $attending_role_id)): ?>
                                    <th scope="row">
                                        <input type="checkbox" class="form-check-input" checked name="checkbox_roles[]" value="<?php echo $role["id"]; ?>" id="checkbox_roles">
                                    </th>
                                <?php else: ?>
                                    <th scope="row">
                                        <input type="checkbox" class="form-check-input" name="checkbox_roles[]" value="<?php echo $role["id"]; ?>" id="checkbox_roles">
                                    </th>
                                <?php endif ?>
                                <td> <?php echo $role['name']; ?></td>
                                <td> <?php echo $role['description']; ?></td>
                            </tr>
                        <?php endif ?>
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
        const cb = form.querySelectorAll('input[name="checkbox_users[]"]');
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