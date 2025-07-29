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
        //ng569 7/25/2025
        //this page outputs 2 tables. One is the active and disabled roles that a user has. the other one is all the meetings the user is a apart of
        //The filtering works through js since I was tired of having the page reload every dearch, so by using JS I was able to have he table update automatically
        $id = get_user_id();
        $roles = [];
        $noRoleMeeting = false;

        $db = getDB();
        $stmt = $db->prepare(
            "SELECT r.name, r.is_active FROM UserRoles AS ur 
                JOIN Roles AS r ON ur.role_id = r.id
                WHERE ur.user_id = :id;"
        );
        try {
            $stmt->execute([":id" => $id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                $roles = $results;
            } else {
                $roles = [['name' => "You have no outstanding Roles.", 'is_active' => 0]];
            }
        } catch (PDOException $e) {
            flash("There was an error finding your Roles, please contact an admin for support", "danger");
            $noRoleMeeting = true;
        }
        ?>
        <?php
        $meeting_id = [];

        $db2 = getDB();
        $stmt2 = $db2->prepare(
            "SELECT *, m.gmt AS mgmt, u.gmt AS ugmt, u.tz_abb AS utz_abb, m.tz_abb AS mtz_abb FROM meeting_attendees AS ma 
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
                $noRoleMeeting = true;
            }
        } catch (PDOException $e) {
            flash("There was an error finding your meetings, please contact an admin for support. Code 1.", "danger");
            error_log("Error toggling role for user $id" . var_export($e->errorInfo, true));
            $noRoleMeeting = true;
        }

        $meeting_role_id = [];

        $db2 = getDB();
        $stmt2 = $db2->prepare(
            "SELECT *, m.gmt AS mgmt, u.gmt AS ugmt, u.tz_abb AS utz_abb, m.tz_abb AS mtz_abb FROM meeting_roles AS mr 
                JOIN Meetings AS m ON mr.meeting_id = m.id
                JOIN UserRoles AS ur ON mr.role_id = ur.role_id
                JOIN Users AS u ON ur.user_id = u.id
                WHERE u.id = :id
                ORDER BY m.meetingDate ASC"
        );
        try {
            $stmt2->execute([":id" => $id]);
            $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            if ($results2) {
                $meeting_role_id = $results2;
            } else {
                array_push($meeting_role_id, "You have no meetings.");
                $noRoleMeeting = true;
            }
        } catch (PDOException $e) {
            flash("There was an error finding your meetings, please contact an admin for support. Code 1.", "danger");
            error_log("Error toggling role for user $id" . var_export($e->errorInfo, true));
            $noRoleMeeting = true;
        }
        if (!$noRoleMeeting) {
            $meeting_id = array_merge($meeting_id, $meeting_role_id);
            $date = array_column($meeting_id, 'meetingDate');
            array_multisort($date, SORT_ASC, $meeting_id);

            //double check prev for doubling issues
            $old_meeting_id = 0;
            $index = 0;
            foreach ($meeting_id AS $meeting) {
                if ($meeting['meeting_id'] == $old_meeting_id) {
                    unset($meeting_id[$index]);
                }
                $old_meeting_id = $meeting['meeting_id'];
                $index++;
            }
        }
        ?>

        <h1 style="text-align: center;">Home Page</h1>
        <p style="text-align: center;">Welcome, <?php echo get_username() ?>!</p>
        <p style="text-align: center;">Your current location is: <?php echo get_user_loc() ?></p>
        <h2> Your Roles: </h2>
        <ul class="list-group" style="width: 10vw;">
            <?php if (!empty($roles)): ?>
                <?php foreach ($roles as $role): ?>
                    <?php if ($role["is_active"] == 0): ?>
                        <li class="list-group-item" title="Disabled" style="background-color: red; color: lightgray;">
                            <?php
                            echo $role['name'];
                            ?>
                            <br>
                        </li>
                    <?php else: ?>
                        <li class="list-group-item" title="Enabled" style="background-color: lightgreen;">
                            <?php
                            echo $role['name'];
                            ?>
                            <br>
                        </li>
                    <?php endif ?>
                <?php endforeach ?>
            <?php else: ?>
                <li>
                    <p>You have no roles currently</p>
                    <br>
                </li>
            <?php endif ?>
        </ul>
        <br>
        <h2>Meetings:</h2>
        <p>(Click to check attendees)</p>
        <h2 style="text-align: center;">Search:</h2>

        <table class="table">
            <thead class="thead-dark">
                <tr>
                    <th style="width: 5vw;" scope="col"># of Rows</th>
                    <th scope="col">Index</th>
                    <th scope="col">Requestor</th>
                    <th scope="col">Message</th>
                    <th scope="col">Local Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row"> <input type="number" id="rows" style="width: 5vw;" class="form-control"></th>
                    <td> <input type="number" id="index" class="form-control"></td>
                    <td> <input type="text" id="creator" class="form-control"></td>
                    <td> <input type="text" id="message" class="form-control"></td>
                    <td> <input type="date" id="date" class="form-control"></td>
                </tr>
            </tbody>
        </table>
        <table class="table table-hover" id="meetings-table">
            <thead>
                <tr>
                    <th scope="col">Index</th>
                    <th scope="col">Requestor</th>
                    <th scope="col">Message</th>
                    <th scope="col">Local Time</th>
                    <th scope="col">Requestor's Time</th>
                </tr>
            </thead>
            <div class="tbodyScroll">
                <tbody>
                    <?php foreach ($meeting_id as $meeting) { ?>
                        <tr data-href="check_attendees.php?index=<?php echo $meeting['meeting_id']; ?>">
                            <td><?php echo $meeting['meeting_id']; ?></td>
                            <td><?php echo $meeting['host']; ?></td>
                            <td><?php echo $meeting['message']; ?></td>
                            <?php if ($meeting['ugmt'] >= 0): ?>
                                <td style="width: 200px;" ;><?php echo convertTimezone($meeting['meetingDate'], $meeting['mgmt'], $meeting['ugmt']) . " " . $meeting['utz_abb'] . " (GMT+" . $meeting['ugmt'] . ")"; ?></td>
                            <?php else: ?>
                                <td style="width: 200px;"><?php echo convertTimezone($meeting['meetingDate'], $meeting['mgmt'], $meeting['ugmt']) . " " . $meeting['utz_abb'] . " (GMT" . $meeting['ugmt'] . ")"; ?></td>
                            <?php endif ?>
                            <?php if ($meeting['mgmt'] >= 0): ?>
                                <td style="width: 200px;"><?php echo $meeting['meetingDate'] . " " . $meeting['mtz_abb'] . " (GMT+" . $meeting["mgmt"] . ")"; ?></td>
                            <?php else: ?>
                                <td style="width: 200px;"><?php echo $meeting['meetingDate'] . " " . $meeting['mtz_abb'] . " (GMT" . $meeting["mgmt"] . ")"; ?></td>
                            <?php endif ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </div>
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

    document.addEventListener("DOMContentLoaded", function() {
        const filters = {
            rows: document.getElementById("rows"),
            index: document.getElementById("index"),
            creator: document.getElementById("creator"),
            message: document.getElementById("message"),
            date: document.getElementById("date")
        };

        const table = document.getElementById("meetings-table");
        const rows = table.querySelectorAll("tbody tr");

        function filterTable() {
            let count = 0;
            rows.forEach(row => {
                const cells = row.querySelectorAll("td");
                const matchIndex = !filters.index.value || cells[0].textContent.includes(filters.index.value);
                const matchCreator = !filters.creator.value || cells[1].textContent.toLowerCase().includes(filters.creator.value.toLowerCase());
                const matchMessage = !filters.message.value || cells[2].textContent.toLowerCase().includes(filters.message.value.toLowerCase());
                const matchDate = !filters.date.value || cells[3].textContent.includes(filters.date.value);

                const shouldShow = matchIndex && matchCreator && matchMessage && matchDate &&
                    (!filters.rows.value || count < parseInt(filters.rows.value));

                row.style.display = shouldShow ? "" : "none";
                if (shouldShow) count++;
            });
        }

        // Add event listeners
        Object.values(filters).forEach(input => {
            input.addEventListener("input", filterTable);
        });
    });
</script>

<?php
require(__DIR__ . "/../../partials/flash.php");
?>