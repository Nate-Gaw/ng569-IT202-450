<?php
require(__DIR__ . "/../../partials/nav.php");

if (!is_logged_in()) {
    die(header("Location: login.php"));
}
if(isset($_GET['success'])) {
    flash("Successfully deleted meeting!", "success");
}

$meetings = [];
$username = get_username();

$db = getDB();
$stmt = $db->prepare(
    "SELECT * FROM Meetings AS m WHERE host=:username"
);
try {
    $stmt->execute([":username" => $username]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results) {
        $meetings = $results;
    }
} catch (PDOException $e) {
    flash("There was an error finding meetings, please contact an admin for support Code 5", "danger");
    error_log("Error toggling meetings" . var_export($e->errorInfo, true));
}

//handle delete
//ng569 7/25/2025
//Grab post request as well as delete id sent, and delete the row. DB is cascade so every attendee is removed as well
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {
    $id = $_POST["delete_id"];
    $stmt2 = $db->prepare("DELETE FROM Meetings WHERE id = :id");
    try {
        $stmt2->execute([":id" => $id]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=true"); //reload page
    } catch (PDOException $e) {
        flash("There was an error deleting meetings, please contact an admin for support Code 6", "danger");
        error_log("Error deleting meetings" . var_export($e->errorInfo, true));
    }
}
?>

<br>
<h1 style="text-align: center;">Manage Your Meetings</h1>
<br>
<h2 style="text-align: center;">Search:</h2>

        <table class="table">
            <thead class="thead-dark">
                <tr>
                    <th style="width: 5vw;" scope="col"># of Rows</th>
                    <th scope="col">Index</th>
                    <th scope="col">Message</th>
                    <th scope="col">Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row"> <input type="number" id="rows" style="width: 5vw;" class="form-control"></th>
                    <td> <input type="number" id="index" class="form-control"></td>
                    <td> <input type="text" id="message" class="form-control"></td>
                    <td> <input type="date" id="date" class="form-control"></td>
                </tr>
            </tbody>
        </table>
<table class="table table-hover table-sm" id="meetings-table" style = "width: 100vw;">
    <thead>
        <tr>
            <th scope="col">Index</th>
            <th scope="col">Creator</th>
            <th scope="col">Message</th>
            <th scope="col">Date & Time</th>
            <th scope="col">Original Date & Time + GMT</th>
            <th scope="col">EDIT</th>
            <th scope="col">DELETE</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($meetings as $meeting) { ?>
            <tr data-href="check_attendees.php?index=<?php echo $meeting['id']; ?>">
                <td><?php echo $meeting['id']; ?></td>
                <td><?php echo $meeting['host']; ?></td>
                <td><?php echo $meeting['message']; ?></td>
                <td>
                    <?php
                    echo convertTimezone($meeting['meetingDate'], $meeting['gmt'], get_user_gmt());
                    ?>
                </td>
                <td><?php echo $meeting['meetingDate'] . $meeting["gmt"]; ?></td>
                <td>
                    <button class="btn btn-warning" onclick="event.stopPropagation(); window.location.href='editor.php?id=<?php echo $meeting['id']; ?>&timestamp=<?php echo $meeting['meetingDate']; ?>'">Edit</button>
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
                const matchMessage = !filters.message.value || cells[2].textContent.toLowerCase().includes(filters.message.value.toLowerCase());
                const matchDate = !filters.date.value || cells[3].textContent.includes(filters.date.value);

                const shouldShow = matchIndex && matchMessage && matchDate &&
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