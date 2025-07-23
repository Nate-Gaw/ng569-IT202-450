<?php
require(__DIR__ . "/../../partials/nav.php");
error_log("Session: " . var_export($_SESSION, true));

if (!is_logged_in()) {
    die(header("Location: login.php"));
}
?>

<div id="landing-body">
    <?php if (is_logged_in()): ?>
        <?php
        $id = get_user_id();
        $roles = [];

        $db = getDB();
        $stmt = $db->prepare(
            "SELECT r.name FROM UserRoles AS ur 
                JOIN Roles AS r ON ur.role_id = r.id
                WHERE ur.user_id = :id AND ur.is_active = 1;"
        );
        try {
            $stmt->execute([":id" => $id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                $roles = $results;
            } else {
                $roles = [['name' => "You have no outstanding Roles."]];
            }
        } catch (PDOException $e) {
            flash("There was an error finding your Roles, please contact an admin for support", "danger");
            error_log("Error toggling role for user $id" . var_export($e->errorInfo, true));
        }
        ?>
        <?php
        $meeting_id = [];
        $attendees = [];
        $noMeet = false;

        $db2 = getDB();
        $stmt2 = $db2->prepare(
            "SELECT *, m.gmt AS mgmt, u.gmt AS ugmt FROM meeting_attendees AS ma 
                JOIN Meetings AS m ON ma.meeting_id = m.id
                JOIN Users AS u ON ma.attendee_id = u.id
                WHERE ma.attendee_id = :id
                ORDER BY m.meetingDate ASC"
        );
        try {
            $stmt2->execute([":id" => $id]);
            $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            if ($results2) {
                $meeting_id = $results2;
            } else {
                array_push($meeting_id, "You have no meetings.");
                $noMeet = true;
            }
        } catch (PDOException $e) {
            flash("There was an error finding your meetings, please contact an admin for support. Code 1.", "danger");
            error_log("Error toggling role for user $id" . var_export($e->errorInfo, true));
        }
        ?>

        <h1 style="text-align: center;">Home Page</h1>
        <p style="text-align: center;">Welcome, <?php echo get_username() ?>!</p>
        <p style="text-align: center;">Your current location is: <?php echo get_user_loc() ?></p>
        <h2> Your Roles: </h2>
        <?php if (isset($roles)): ?>
            <?php foreach ($roles as $role) { ?>
                <li>
                    <?php 
                    echo $role['name']; 
                    ?>
                    <br>
                </li>
            <?php } ?>
        <?php else:?>
            <li>
                <p>You have no roles currently</p>
                <br>
            </li>
        <?php endif ?>
        <br>
        <h2>Meetings:</h2>
        <p>(Click to check attendees)</p>

        <table class="table table-hover">
            <thead>
                <tr>
                    <th scope="col">Index</th>
                    <th scope="col">Creator</th>
                    <th scope="col">Message</th>
                    <th scope="col">Date & Time</th>
                    <th scope="col">Original Date & Time + GMT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meeting_id as $meeting) { ?>
                    <tr data-href="check_attendees.php?index=<?php echo $meeting['meeting_id'];?>">
                        <th scope="row"><?php echo $meeting['meeting_id']; ?></th>
                        <td><?php echo $meeting['host']; ?></td>
                        <td><?php echo $meeting['message']; ?></td>
                        <td>
                            <?php
                            echo convertTimezone($meeting['meetingDate'], $meeting['mgmt'], $meeting['ugmt']);
                            ?>
                        </td>
                        <td><?php echo $meeting['meetingDate'] . $meeting["mgmt"]; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("tr[data-href]").forEach(row => {
        row.style.cursor = "pointer";
        row.addEventListener("click", () => {
            window.location.href = row.getAttribute("data-href");
        });
    });
});
</script>

<?php
require(__DIR__ . "/../../partials/flash.php");
?>