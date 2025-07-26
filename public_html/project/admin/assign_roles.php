<?php
//note we need to go up 1 more directory
require(__DIR__ . "/../../../partials/nav.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    die(header("Location: " . get_url("landing.php")));
}

$users = [];
$active_roles = [];
$username = "";
$active_roles = [];
$search = false;
$searched_users = [];

$db = getDB();
$stmt = $db->prepare("SELECT id, name, description FROM Roles WHERE is_active = 1 LIMIT 10");
try {
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results) {
        $active_roles = $results;
    }
} catch (PDOException $e) {
    flash(var_export($e->errorInfo, true), "danger");
}
$stmt = $db->prepare("SELECT Users.id, username, 
    (SELECT GROUP_CONCAT(name, ' (' , IF(ur.is_active = 1,'active','inactive') , ')') from 
    UserRoles ur 
    JOIN Roles on ur.role_id = Roles.id 
    WHERE ur.user_id = Users.id) as roles
    from Users");
try {
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results) {
        $users = $results;
    }
} catch (PDOException $e) {
    flash(var_export($e->errorInfo, true), "danger");
}


//attempt to apply
if (isset($_POST["users"], $_POST["roles"])) {
    $user_ids = $_POST["users"]; //se() doesn't like arrays so we'll just do this
    $role_ids = $_POST["roles"]; //se() doesn't like arrays so we'll just do this
    if (empty($user_ids) || empty($role_ids)) {
        flash("Both users and roles need to be selected", "warning");
    } else {
        //for sake of simplicity, this will be a tad inefficient (normally bulk operations should fail/pass together)
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO UserRoles (user_id, role_id, is_active) VALUES (:uid, :rid, 1) 
        ON DUPLICATE KEY UPDATE is_active = !is_active");

        // triggers 1 query per pair, that way an exception will only affect that pair rather than the bulk operation
        foreach ($user_ids as $uid) {
            foreach ($role_ids as $rid) {
                try {
                    $stmt->execute([":uid" => $uid, ":rid" => $rid]);
                    if ($stmt->rowCount() > 0) {
                        flash("Toggled role for user $uid and role $rid", "success");
                    } else {
                        flash("No changes made for user $uid and role $rid", "warning");
                    }
                } catch (PDOException $e) {
                    flash("There was an error toggling the role, please try again later", "danger");
                    error_log("Error toggling role for user $uid and role $rid: " . var_export($e->errorInfo, true));
                }
            }
        }
    }
}



//search for user by username
if (isset($_POST["username"])) {
    $username = trim(se($_POST, "username", "", false));
    if (!empty($username)) {
        $search = true;
        foreach ($users as $user) {
            if ($user['username'] == $username) {
                array_push($searched_users, $user);
            }
        }
    } else {
        flash("Username must not be empty", "warning");
    }
}


?>
<br>
<h3>Assign Roles</h3>
<!-- search form -->
<form method="POST">
    <?php render_input(["type" => "text", "name" => "username", "id" => "username", "label" => "Username search", "value" => $username, "rules" => ["required" => true]]); ?>

    <input type="hidden" name="action" value="fetch">
    <?php render_button(["text" => "Search", "type" => "submit"]); ?>
</form>
<!-- empty toggle form, inputs will use the form attribute to associate with this form -->
<form id="toggleForm" method="POST">
    <?php if (isset($username) && !empty($username)) : ?>
        <input form="toggleForm" type="hidden" name="username" value="<?php se($username, false); ?>" />
    <?php endif; ?>
    <table class="table">
        <thead>
            <th>Users</th>
            <th>Roles to Assign</th>
        </thead>
        <tbody>
            <tr>
                <td>
                    <!-- nested table for users -->
                    <?php if ($search): ?>
                        <table class="table">
                            <?php foreach ($searched_users as $user) : ?>
                                <tr>
                                    <td>

                                        <input form="toggleForm" id="user_<?php se($user, 'id'); ?>" type="checkbox" name="users[]" value="<?php se($user, 'id'); ?>" />
                                        <label form="toggleForm" for="user_<?php se($user, 'id'); ?>"><?php se($user, "username"); ?></label>
                                    </td>
                                    <td><?php se($user, "roles", "No Roles"); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <table class="table">
                            <?php foreach ($users as $user) : ?>
                                <tr>
                                    <td>

                                        <input form="toggleForm" id="user_<?php se($user, 'id'); ?>" type="checkbox" name="users[]" value="<?php se($user, 'id'); ?>" />
                                        <label form="toggleForm" for="user_<?php se($user, 'id'); ?>"><?php se($user, "username"); ?></label>
                                    </td>
                                    <td><?php se($user, "roles", "No Roles"); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif ?>
                </td>
                <td>
                    <!-- nested data for roles -->
                    <?php foreach ($active_roles as $role) : ?>
                        <div>
                            <input form="toggleForm" id="role_<?php se($role, 'id'); ?>" type="checkbox" name="roles[]" value="<?php se($role, 'id'); ?>" />
                            <label form="toggleForm" for="role_<?php se($role, 'id'); ?>"><?php se($role, "name"); ?></label>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php render_button(["text" => "Toggle Roles", "type" => "submit"]); ?>
</form>

<?php
//note we need to go up 1 more directory
require_once(__DIR__ . "/../../../partials/flash.php");
?>