<?php
//note we need to go up 1 more directory
require(__DIR__ . "/../../../partials/nav.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    die(header("Location: " . get_url("landing.php")));
}
if(isset($_GET['success'])) {
    flash("Successfully deleted meeting!", "success");
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
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=true"); //reload page
}
?>

<br>
<h1 style="text-align: center;">Manage All Meetings</h1>
<br>
<h2 style="text-align: center;">Search:</h2>

<table class="table">
    <thead class="thead-dark">
        <tr>
            <th style="width: 5vw;" scope="col"># of Rows</th>
            <th scope="col">Index</th>
            <th scope="col">Requestor</th>
            <th scope="col">Message</th>
            <th scope="col">Date & Time</th>
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
<table class="table table-hover table-sm" id="meetings-table">
    <thead>
        <tr>
            <th scope="col">Index</th>
            <th scope="col">Requestor</th>
            <th scope="col">Message</th>
            <th scope="col">Local Time</th>
            <th scope="col">Requestor's Time</th>
            <th scope="col">Edit</th>
            <th scope="col">DELETE</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($meetings as $meeting) { ?>
            <tr data-href="/project/check_attendees.php?index=<?php echo $meeting['id']; ?>">
                <td><?php echo $meeting['id']; ?></td>
                <td><?php echo $meeting['host']; ?></td>
                <td><?php echo $meeting['message']; ?></td>
                <?php if (get_user_gmt() >= 0): ?>
                    <td style="width: 200px;" ;><?php echo convertTimezone($meeting['meetingDate'], $meeting['gmt'], get_user_gmt()) . " " . $meeting['tz_abb'] . " (GMT+" . get_user_gmt() . ")"; ?></td>
                <?php else: ?>
                    <td style="width: 200px;"><?php echo convertTimezone($meeting['meetingDate'], $meeting['gmt'], get_user_gmt()) . " " . $meeting['tz_abb'] . " (GMT" . get_user_gmt() . ")"; ?></td>
                <?php endif ?>
                <?php if ($meeting['gmt'] >= 0): ?>
                    <td style="width: 200px;"><?php echo $meeting['meetingDate'] . " " . $meeting['tz_abb'] . " (GMT+" . $meeting["gmt"] . ")"; ?></td>
                <?php else: ?>
                    <td style="width: 200px;"><?php echo $meeting['meetingDate'] . " " . $meeting['tz_abb'] . " (GMT" . $meeting["gmt"] . ")"; ?></td>
                <?php endif ?>
                <td>
                    <button class="btn btn-warning" onclick="event.stopPropagation(); window.location.href='/../project/editor.php?id=<?php echo $meeting['id']; ?>&timestamp=<?php echo $meeting['meetingDate']; ?>'">Edit</button>
                </td>
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
//note we need to go up 1 more directory
require_once(__DIR__ . "/../../../partials/flash.php");
?>