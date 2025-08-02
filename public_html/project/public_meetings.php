<?php
require(__DIR__ . "/../../partials/nav.php");

$meetings = [];
$username = get_username();

$db = getDB();
$stmt = $db->prepare(
    "SELECT *, m.gmt AS mgmt, u.gmt AS ugmt, u.tz_abb AS utz_abb, m.tz_abb AS mtz_abb FROM meeting_attendees AS ma 
        JOIN Meetings AS m ON ma.meeting_id = m.id
        JOIN Users AS u ON ma.attendee_id = u.id
        WHERE ma.attendee_id = :id
        ORDER BY m.meetingDate ASC"
);
try {
    $stmt->execute([":id" => '-1']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results) {
        $meetings = $results;
    }
} catch (PDOException $e) {
    flash("There was an error finding meetings, please contact an admin for support Code 5", "danger");
    error_log("Error toggling meetings" . var_export($e->errorInfo, true));
}
?>

<br>
<h1 style="text-align: center;">Public Meetings</h1>
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
<table class="table table-hover table-sm" id="meetings-table" style="width: 100vw;">
    <thead>
        <tr>
            <th scope="col">Index</th>
            <th scope="col">Requestor</th>
            <th scope="col">Message</th>
            <th scope="col">GMT Time</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($meetings as $meeting) { ?>
            <tr data-href="check_attendees.php?index=<?php echo $meeting['meeting_id']; ?>">
                <td><?php echo $meeting['meeting_id']; ?></td>
                <td><?php echo $meeting['host']; ?></td>
                <td><?php echo $meeting['message']; ?></td>
                <?php if ($meeting['gmt'] >= 0): ?>
                    <td style="width: 200px;" ;><?php echo convertTimezone($meeting['meetingDate'], $meeting['gmt'], 0) . " " . $meeting['utz_abb'] . " (GMT+0)"; ?></td>
                <?php else: ?>
                    <td style="width: 200px;"><?php echo convertTimezone($meeting['meetingDate'], $meeting['gmt'], 0) . " " . $meeting['utz_abb'] . " (GMT+0)"; ?></td>
                <?php endif ?>
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