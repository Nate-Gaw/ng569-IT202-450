<?php
require(__DIR__ . "/../../partials/nav.php");
$form = [
    ["type" => "email", "id" => "email", "name" => "email", "label" => "Email", "rules" => ["required" => true]],
    [
        "type" => "text",
        "id" => "username",
        "name" => "username",
        "label" => "Username",
        "rules" => [
            "required" => true,
            "maxlength" => 30,
            "title" => "3-16 lowercase letters, numbers, underscores, or hyphens"
        ]
    ],
    ["type" => "password", "id" => "password", "name" => "password", "label" => "Password", "rules" => ["required" => true, "minlength" => 8]],
    ["type" => "password", "id" => "confirm", "name" => "confirm", "label" => "Confirm Password", "rules" => ["required" => true, "minlength" => 8]],
    ["type" => "text", "id" => "location", "name" => "location", "label" => "Location", "rules" => ["required" => true, "pattern" => "^[A-Za-z\s\/\_]+$"]],
];
?>
<div class="container-fluid">
    <h3>Register</h3>
    <form onsubmit="return validate(this)" method="POST">
        <?php foreach ($form as $field): ?>
            <?php render_input($field); ?>
        <?php endforeach; ?>
        <?php render_button(["text" => "Register", "type" => "submit"]); ?>
    </form>
</div>
<script>
    function validate(form) {
        //TODO 1: implement JavaScript validation (you'll do this on your own towards the end of Milestone1)
        //ensure it returns false for an error and true for success
        //ng569 7/7/25 
        //JS validation. Email and passwords are checked to see if they are empty. 
        //Then everything is checked accordingly to see if everything is valid.
        let pw = form.pw.value;
        let com = form.confirm.value;
        let user = form.username.value;
        let email = form.email.value;
        let isValid = true;
        if (empty(email)) {
            flash("Email/Username must not be empty.", "danger");
            isValid = false;
        }
        if (empty(pw)) {
            flash("Password must not be empty.", "danger");
            isValid = false;
        }
        if (!isValidPassword(pw)) {
            flash("Password must be at least 8 characters", "warning");
            isValid = false;
        }
        if (!isValidEmail(email)) {
            flash("Invalid email address.", "danger");
            isValid = false;
        }
        if (!isValidUsername(user)) {
            flash("Username must be lowercase, alphanumerical, and can only contain _ or -", "danger");
            isValid = false;
        }
        if (!isValidConfirm(pw, com)) {
            flash("Passwords must match.", "danger");
            isValid = false;
        }
        return isValid;
    }
</script>
<?php
//TODO 2: add PHP Code
if (isset($_POST["email"], $_POST["password"], $_POST["confirm"], $_POST["username"], $_POST["location"])) {
    //ng569 7/7/25 
    //Everything is checked very similar to JS
    //Then if there is no error, records are retrieved from DB to check for duplicated
    //and entered and saved if there is no issue

    $email = se($_POST, "email", "", false);
    $password = se($_POST, "password", "", false);
    $confirm = se($_POST, "confirm", "", false);
    $username = se($_POST, "username", "", false);
    $location = se($_POST, "location", "", false);
    // TODO 3: validate/use
    $hasError = false;

    //ng569 7/25/2025
    //locaiton is placed into the API, where it will be checked to see if the API returns anything, if it does then it was a success and will go through an SQL call to be placed into the DB, if not then an error is returned
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
        flash("Location was not recognized.", "danger");
        $hasError = true;
    }

    if (empty($email)) {
        flash("Email must not be empty.", "danger");
        $hasError = true;
    }
    // Sanitize and validate email
    $email = sanitize_email($email);
    if (!is_valid_email($email)) {
        flash("Invalid email address.", "danger");
        $hasError = true;
    }
    if (!is_valid_username($username)) {
        flash("Username must be lowercase, alphanumerical, and can only contain _ or -", "danger");
        $hasError = true;
    }
    if (empty($password)) {
        flash("Password must not be empty.", "danger");
        $hasError = true;
    }

    if (empty($confirm)) {
        flash("Confirm password must not be empty.", "danger");
        $hasError = true;
    }

    if (!is_valid_password($password)) {
        flash("Password must be at least 8 characters long.", "danger");
        $hasError = true;
    }

    if (!is_valid_confirm($password, $confirm)) {
        flash("Passwords must match.", "danger");
        $hasError = true;
    }

    if (!$hasError) {
        // TODO 4: Hash password and store record in DB
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $db = getDB(); // available due to the `require()` of `functions.php`
        // Code for inserting user data into the database
        $stmt = $db->prepare("INSERT INTO Users (email, password, username, location, tz_name, tz_loc, tz_abb, gmt) VALUES (:email, :password, :username, :location, :tz_name, :tz_loc, :tz_abb, :gmt)");
        try {
            $stmt->execute([':email' => $email, ':password' => $hashed_password, ':username' => $username, ":location"=> $location, ":tz_name"=> $result["timezone_name"], ":tz_loc"=> $result["timezone_location"], ":tz_abb"=> $result["timezone_abbreviation"], ":gmt"=> $result["gmt_offset"]]);

            flash("Successfully registered! You can now log in.", "success");
        } catch (PDOException $e) {
            // Handle duplicate email/username
            users_check_duplicate($e);
        } catch (Exception $e) {
            flash("There was an error registering. Please try again.", "danger");
            error_log("Registration Error: " . var_export($e, true)); // log the technical error for debugging
        }
    }
}
?>
<?php
require(__DIR__ . "/../../partials/flash.php");
reset_session();
?>