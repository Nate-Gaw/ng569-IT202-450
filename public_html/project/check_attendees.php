<?php
//note we need to go up 1 more directory
require(__DIR__ . "/../../partials/nav.php");

if (!is_logged_in()) {
    die(header("Location: login.php"));
}
$ma = [];

if (isset($_GET["index"])) {
    $index = $_GET["index"];
    $verify = false;

    //verifying user is in meeting (only admin may bipass)
    if (!has_role("Admin") && !empty($index)) {
        $db2 = getDB();
        $stmt = $db2->prepare(
            "SELECT attendee_id FROM meeting_attendees
            WHERE meeting_id = :index"
        );
        try {
            $stmt->execute([":index" => $index]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                foreach ($results as $id) {
                    if ($id['attendee_id'] == get_user_id()) {
                        $verify = true;
                    }
                }
            }
        } catch (PDOException $e) {
            flash("Couldn't verify your status. Code 8." . var_export($e->errorInfo, true), "danger");
        }
    } else if (has_role("Admin")) $verify = true;

    if (!empty($index) && $verify) {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT ma.meeting_id, u.username, u.email, u.tz_loc, m.host, m.message, m.meetingDate, m.gmt FROM meeting_attendees AS ma
        JOIN Users AS u ON ma.attendee_id = u.id 
        JOIN Meetings AS m ON ma.meeting_id = m.id
        WHERE ma.meeting_id = :index"
        );
        try {
            $stmt->execute([":index" => $index]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                $ma = $results;
            } else {
                flash("Couldn't retrieve Users. Code 7.", "danger");
            }
        } catch (PDOException $e) {
            flash("Couldn't find meeting. Code 10." . var_export($e->errorInfo, true), "danger");
        }
    } else {
        if (empty($index)) {
            flash("input cannot be empty", "danger");
        } else if (!$verify) flash("You do not have access to this meeting information", "danger");
        else flash("There is an internal error. Code 11.", "danger");
    }
}

?>

<br>
<h1 style="text-align: center;">Check Meeting Details</h1>
<br>
<form method="GET" onsubmit="return validate(this);">
    <div class="input-group mb-3">
        <div class="input-group-prepend">
            <span class="input-group-text">Enter Meeting Index:</span>
        </div>
        <input type="number" class="form-control" id="index" name="index" required>
        <div class="input-group-append">
            <button class="btn btn-info" type="submit">Search</button>
        </div>
    </div>
</form>
<?php if (!empty($ma)): ?>
    <table class="table table-hover">
        <thead>
            <tr>
                <th scope="col">Meeting ID</th>
                <th scope="col">Creator</th>
                <th scope="col">Message</th>
                <th scope="col">Date & Time</th>
                <th scope="col">Original Date & Time + GMT</th>
            </tr>
        </thead>
        <div class="tbodyScroll">
            <tbody>
                <tr data-href="check_attendees.php?index=<?php echo $ma[0]['meeting_id']; ?>">
                    <th scope="row"><?php echo $ma[0]['meeting_id']; ?></th>
                    <td><?php echo $ma[0]['host']; ?></td>
                    <td><?php echo $ma[0]['message']; ?></td>
                    <td>
                        <?php
                        echo convertTimezone($ma[0]['meetingDate'], $ma[0]['gmt'], get_user_gmt());
                        ?>
                    </td>
                    <td><?php echo $ma[0]['meetingDate'] . $ma[0]["gmt"]; ?></td>
                </tr>
            </tbody>
        </div>
    </table>
    <h4>Attendees: </h4>
    <table class="table">
        <thead class="thead-dark">
            <tr>
                <th scope="col">Username</th>
                <th scope="col">Email</th>
                <th scope="col">Location</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ma as $attendee): ?>
                <tr>
                    <th scope="row"><?php echo $attendee['username'] ?></th>
                    <td><?php echo $attendee['email'] ?></td>
                    <td><?php echo $attendee['tz_loc'] ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<script>
    function validate(form) {
        index = form.index.value;
        isValid = true;
        if (empty(index)) { 
            flash("input cannot be empty", "danger");
            isValid = false;
        }
        return isValid;
    }
</script>

<?php
require(__DIR__ . "/../../partials/flash.php");
?>