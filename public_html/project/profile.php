<?php
//ng569 7/7/25 redirect if user is not logged in
require_once(__DIR__ . "/../../partials/nav.php");
if (!is_logged_in()) {
    die(header("Location: login.php"));
}
?>
<?php
//ng569 7/7/25 PHP Validation. It checks to make sure everything input is valid and not empty then if the new email is not the same or the new username is not the same then it calls the DB and inputs the new information
//Then the same is done for passwords, where they are checked and then compared to the DB system, each other and saved if passed.
$user_id = get_user_id(); // get id from session
$email = get_user_email(); // get email from session
$username = get_username(); // get username from session
// handle email/username update
if (isset($_POST["email"], $_POST["username"])) {
    $new_email = se($_POST, "email", null, false);
    $new_username = se($_POST, "username", null, false);
    $hasError = false;
    // validate format
    if (empty($new_email)) {
        //echo "Email must not be empty<br>";
        flash("Email must not be empty.", "danger");
        $hasError = true;
    }
    // Sanitize and validate email
    $new_email = sanitize_email($new_email);
    if (!is_valid_email($new_email)) {
        //echo "Invalid email address<br>";
        flash("Invalid email address.", "danger");
        $hasError = true;
    }
    if (!is_valid_username($new_username)) {
        flash("Username must be lowercase, alphanumerical, and can only contain _ or -", "danger");
        $hasError = true;
    }
    // check for changes
    if (($username != $new_username || $email != $new_email) && !$hasError) {
        $saved = false;
        $params = [":email" => $new_email, ":username" => $new_username, ":id" => $user_id];
        $db = getDB();
        $stmt = $db->prepare("UPDATE Users set email = :email, username = :username where id = :id");
        try {
            $stmt->execute($params);
            $updated_rows = $stmt->rowCount();
            if ($updated_rows === 0) {
                flash("No changes made", "warning");
            } else if ($updated_rows == 1) {
                flash("Profile saved", "success");
                $saved = true;
            } else {
                // this shouldn't happen, but we log it just in case
                error_log("Unexpected number of rows updated: " . $updated_rows);
            }
        } catch (PDOException $e) {
            // handle existing email/username error
            users_check_duplicate($e);
        } catch (Exception $e) {
            flash("An unexpected error occurred, please try again", "danger");
            error_log("Unexpected Error updating user details: " . var_export($e, true));
        }
        if ($saved) {
            //select fresh data from table
            $stmt = $db->prepare("SELECT email, username from Users where id = :id LIMIT 1");
            try {
                $stmt->execute([":id" => $user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    //$_SESSION["user"] = $user; // don't overwrite the entire session data, just update the specific fields
                    $_SESSION["user"]["email"] = $user["email"];
                    $_SESSION["user"]["username"] = $user["username"];
                    // since this comes after the setting of $username and $email at the top, we'll apply the edits to them too
                    $username = $user["username"];
                    $email = $user["email"];
                } else {
                    // This shouldn't happen, but we add logs/notification just in case
                    flash("User doesn't exist", "danger");
                    error_log("User doesn't exist");
                }
            } catch (PDOException $e) {
                flash("An unexpected error occurred, please try again", "danger");
                error_log("DB Error fetching user details: " . var_export($e, true));
            } catch (Exception $e) {
                flash("An unexpected error occurred, please try again", "danger");
                error_log("Unexpected Error fetching user details: " . var_export($e, true));
            }
        }
    }
}
// handle password update
if (isset($_POST["currentPassword"], $_POST["newPassword"], $_POST["confirmPassword"])) {

    //check/update password
    $current_password = se($_POST, "currentPassword", null, false);
    $new_password = se($_POST, "newPassword", null, false);
    $confirm_password = se($_POST, "confirmPassword", null, false);
    // require all 3 to be set before attempting to process
    $can_update = !empty($current_password) && !empty($new_password) && !empty($confirm_password);
    if ($can_update) {
        // check that new matches confirm (i.e., no typos)
        if (!is_valid_confirm($new_password, $confirm_password)) {
            flash("New passwords don't match", "warning");
        } else {
            //validate current password against password rules
            $hasError = false;
            if (!is_valid_password($new_password)) {
                //echo "Password too short<br>";
                flash("Password must be at least 8 characters long.", "danger");
                $hasError = true;
            }
            if (!$hasError) {
                // fetch current hash
                try {
                    $db = getDB();
                    $stmt = $db->prepare("SELECT password from Users where id = :id");
                    // using get_user_id() in this block to ensure we don't mistakenly allow changing someone else's password
                    $stmt->execute([":id" => get_user_id()]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (isset($result["password"])) {
                        // verify current vs hash
                        if (!password_verify($current_password, $result["password"])) {
                            flash("Current password is invalid", "warning");
                        } else {
                            // change password
                            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                            $query = "UPDATE Users set password = :password where id = :id";
                            $stmt = $db->prepare($query);
                            $stmt->execute([
                                ":id" => get_user_id(),
                                ":password" => $new_hash
                            ]);
                            $updated_rows = $stmt->rowCount();
                            if ($updated_rows === 0) {
                                flash("No changes made to password", "warning");
                            } else if ($updated_rows == 1) {
                                flash("Password updated successfully", "success");
                            } else {
                                // this shouldn't happen, but we log it just in case
                                error_log("Unexpected number of rows updated for password change: " . $updated_rows);
                            }
                        }
                    } else {
                        error_log("No password field in result");
                    }
                } catch (Exception $e) {
                    flash("Error processing password change", "danger");
                    error_log("Error processing password change: " . var_export($e, true));
                }
            }
        }
    }
}
//handle location update
if (isset($_POST["location"])) {
    $location = se($_POST, "location", null, false);
    $can_update = !empty($location) && $location != get_user_loc();
    if ($can_update) {
        if (preg_match('/^[A-Za-z\s\/\_]+$/', $location)) {

            $data = ["location" => $location];
            $endpoint = "https://world-time-by-based-api.p.rapidapi.com/v1/worldtime/";
            $isRapidAPI = true;
            $rapidAPIHost = "world-time-by-based-api.p.rapidapi.com";
            $result = get($endpoint, "TIME_API_KEY", $data, $isRapidAPI, $rapidAPIHost);
            //example of cached data to save the quotas, don't forget to comment out the get() if using the cached data for testing
           /*$result = ["status" => 200, "response" => '{
                "datetime":"2025-07-14 99:99:99",
                "timezone_name":"Eastern Daylight Time",
                "timezone_location":"America/New_York",
                "timezone_abbreviation":"EDT",
                "gmt_offset":-4,
                "is_dst":true,
                "requested_location":"New York",
                "latitude":40.7127281,
                "longitude":-74.0060152
            }'];*/

            error_log("Response: " . var_export($result, true));
            if (se($result, "status", 400, false) == 200 && isset($result["response"])) {
                $result = json_decode($result["response"], true);
            } else {
                $result = [];
            }

            if (count($result) > 0) {
                try {
                    $db = getDB();
                    $query = "UPDATE Users SET location = :location, tz_name = :tz_name, tz_loc = :tz_loc, tz_abb = :tz_abb, gmt = :gmt WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ":id" => get_user_id(),
                        ":location" => $location,
                        ":tz_name" => se($result, "timezone_name", null, false),
                        ":tz_loc" => se($result, "timezone_location", null, false),
                        ":tz_abb" => se($result, "timezone_abbreviation", null, false),
                        ":gmt" => se($result, "gmt_offset", null, false),
                    ]);
                    $updated_rows = $stmt->rowCount();
                    if ($updated_rows === 0) {
                        flash("No changes made to password", "warning");
                    } else if ($updated_rows == 1) {
                        flash("Location updated successfully", "success");
                        update_user_info(se($result, "timezone_location", null, false), (int) se($result, "gmt_offset", null, false));
                    } else {
                        // this shouldn't happen, but we log it just in case
                        error_log("Unexpected number of rows updated for location change: " . $updated_rows);
                    }
                } catch (Exception $e) {
                    flash("Error processing location change", "danger");
                    error_log("Error processing location change: " . var_export($e, true));
                }
            } else {
                flash("Location is invalid, please try again.", "danger");
            }
        } else {
            error_log("Location is invalid", "danger");
        }
    }
}
?>

<h3>Profile</h3>
<form method="POST" onsubmit="return validate(this);">
    <!-- ng569 7/7/25
     HTML validation email and username are required and new and confirm password is required and has minimum length.
     The current password is required but there is no minlength just in case somehow they register or change their password without req-->
    <div class="mb-3">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?php se($email); ?>" required />
    </div>
    <div class="mb-3">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="<?php se($username); ?>" required maxlength="30" />
    </div>
    <!-- DO NOT PRELOAD PASSWORD -->
    <div>Password Reset:</div>
    <div class="mb-3">
        <label for="cp">Current Password</label>
        <input type="password" name="currentPassword" id="cp" />
    </div>
    <div class="mb-3">
        <label for="np">New Password</label>
        <input type="password" name="newPassword" id="np" minlength="8" />
    </div>
    <div class="mb-3">
        <label for="conp">Confirm Password</label>
        <input type="password" name="confirmPassword" id="conp" minlength="8" />
    </div>
    <div>Change Location:</div>
    <div class="mb-3">
        <label for="cp">Location</label>
        <input type="text" pattern="^[A-Za-z\s\/\_]+$" value=<?php echo get_user_loc(); ?> title="Letters Only" name="location" id="loc" />
    </div>
    <br>
    <input type="submit" value="Update Profile" name="save" />
</form>

<script>
    //ng569 7/7/25
    //Double checks to make sure that the inputs are NOT empty and checks for valid inputs and matching passwords
    function validate(form) {
        let pw = form.currentPassword.value;
        let npw = form.newPassword.value;
        let cpw = form.confirmPassword.value;
        let email = form.email.value;
        let user = form.username.value;
        let loc = form.location.value;

        let isValid = true;
        let emptyPass = false;
        let emptyLoc = false;
        //TODO add other client side validation....
        if (empty(email)) {
            flash("Email must not be empty.", "danger");
            isValid = false;
        }
        if (!empty(pw) || !empty(npw) || !empty(cpw)) {
            if (empty(pw)) {
                flash("Password must not be empty.", "danger");
                isValid = false;
            }
            if (empty(npw)) {
                flash("New password must not be empty.", "danger");
                isValid = false;
            }
            if (!isValidPassword(pw)) {
                flash("Password must be at least 8 characters", "warning");
                isValid = false;
            }
            if (!isValidPassword(npw)) {
                flash("New password must be at least 8 characters", "warning");
                isValid = false;
            }
            if (!isValidConfirm(npw, cpw)) {
                flash("Passwords must match.", "danger");
                isValid = false;
            }
        } else {
            emptyPass = true;
        }

        if (!isValidUsername(user)) {
            flash("Username must be lowercase, alphanumerical, and can only contain _ or -", "danger");
            $hasError = true;
        }
        if (!isValidEmail(email)) {
            flash("Invalid email address.", "danger");
            isValid = false;
        }
        if (empty(loc)) {
            emptyLoc = true;
        }
        if (emptyLoc && emptyPass) {
            flash("Nothing to update in form.", "danger");
            isValid = false;
        }
        //example of using flash via javascript
        //find the flash container, create a new element, appendChild
        // NOTE: we'll extract the flash code to a function later
        // if (pw !== con) { // first JS validation example
        //     flash("Password and Confirm password must match", "warning");
        //     isValid = false;
        // }
        // returning false will prevent the form from submitting
        return isValid;
    }
</script>
<?php
require_once(__DIR__ . "/../../partials/flash.php");
?>